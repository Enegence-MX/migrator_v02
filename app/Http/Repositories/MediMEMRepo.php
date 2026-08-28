<?php
/**
 * MediMEM repository file.
 *
 * PHP Version 7.4
 *
 * @category Repositories
 * @package  Repositories
 * @author   MCK <desarrollo@mck.agency>
 * @license  Apache 2 https://www.apache.org/licenses/LICENSE-2.0
 * @link     ''
 */

namespace App\Http\Repositories;

use App\Http\Helpers\DateHelper;
use App\Http\Services\MediMEMService;
use App\Http\Traits\GetBlockValues;
use App\Http\Traits\MeasurementsRepoTrait;
use App\Models\CentralElectrica;
use App\Models\CentroCarga;
use App\Models\Festivo;
use Illuminate\Support\Facades\DB;
use DateTime;
use Exception;
use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * Class service for CFE Distribucion endpoint consuming.
 *
 * @category Repositories
 * @package  Repositories
 * @author   MCK <desarrollo@mck.agency>
 * @license  Apache 2 https://www.apache.org/licenses/LICENSE-2.0
 * @link     ''
 */
class MediMEMRepo
{
    use GetBlockValues;
    use MeasurementsRepoTrait;

    protected $mediMEMService;
    protected $measurementsRepo;
    protected $webhookUrl = 'https://chat.googleapis.com/v1/spaces/AAQAD3rf7Zs/messages?key=AIzaSyDdI0hCZtE6vySjMm-WEfRq3CPzqKqqsHI&token=ZaVhXYMwP0o1jDWkg7VpGU_7mWzuu--nBEw0eHYE7EM';

    /**
     * Construct function.
     *
     * @param $mediMEMService   Instance of MediMEMService.
     * @param $measurementsRepo Instance of MeasurementsRepo.
     *
     * @return void
     */
    public function __construct(
        MediMEMService $mediMEMService,
        MeasurementsRepo $measurementsRepo
    ) {
        $this->mediMEMService   = $mediMEMService;
        $this->measurementsRepo = $measurementsRepo;
    }

    protected function sendGoogleChatNotification($title, $message, $rpuOrContext = 'N/A')
    {
        $tz = new \DateTimeZone('-0600');
        $date = new \DateTime('now', $tz);
        $fecha = $date->format('Y-m-d H:i:s');

        $payload = [
            'text' => "🚨 *ERROR EN TAREA MEDIMEM*\n*Contexto/RPU/RMU:* `{$rpuOrContext}`\n*Título:* {$title}\n*Detalle:* {$message}\n*Fecha:* " . $fecha
        ];

        try {
            if (class_exists('\Illuminate\Support\Facades\Http')) {
                Http::timeout(5)->post($this->webhookUrl, $payload);
            } else {
                $ch = curl_init($this->webhookUrl);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                curl_exec($ch);
                curl_close($ch);
            }
        } catch (Throwable $e) {
            Log::channel('task_errors')->error("No se pudo enviar la notificación a Google Chat: " . $e->getMessage());
        }
    }

    /**
     * Synchronizes measurement data for both electrical centers (Centrales Electricas)
     * and load centers (Centros de Carga).
     *
     * This function performs the following operations:
     * 1. Retrieves measurement data from CFE API for a specified date range
     * 2. Fills gaps in measurement data for completeness
     * 3. Processes and transforms raw data into hourly measurements
     * 4. Stores or updates measurements in the database
     *
     * The process includes handling various types of measurements:
     * - kWh energy consumption (kwhe)
     * - kWh energy generation (kwhr)
     * - Reactive power (kvarh)
     * - Rolling demand calculations
     *
     * @param array  $rpusParam      Array of RPU identifiers for filtering load centers
     * @param array  $rmusParam      Array of RMU identifiers for filtering electrical centers
     * @param string $startDateParam Optional start date for data sync (Y-m-d format)
     * @param string $endDateParam   Optional end date for data sync (Y-m-d format)
     *
     * @throws Exception Database operations may throw exceptions (currently caught and ignored)
     *
     * @return void
     */
    public function syncronizeMeasurements(
        $rpusParam = [],
        $rmusParam = [],
        $startDateParam = null,
        $endDateParam = null
    ) {
        $totalRecordsSynced = 0;
        $processedMeters = 0;

        try {
            $yesterday      = new DateTime();
        $yesterday->modify('-8 day');
        $yesterdayMinus61 = new DateTime();
        $yesterdayMinus61->modify('-61 days');
        $fechaInicio = $yesterdayMinus61->format('Y-m-d');
        $fechaFin = $yesterday->format('Y-m-d');

        if ($startDateParam !== null) {
            $fechaInicio = $startDateParam;
        }
        if ($endDateParam !== null) {
            $fechaFin = $endDateParam;
        }

        // Split date range into 30-day chunks
        $dateRanges = DateHelper::splitDateRangeInto30DayChunks($fechaInicio, $fechaFin);
        $diasFestivos = Festivo::distinct()
        ->select(['Fecha'])
        ->get(
        )->pluck('Fecha')
        ->toArray();

        $centrosDeCargaQuery = CentroCarga::where('useMedicionesMediMEM', 1)
        ->leftJoin('measurements', 'centrosCarga.rpu', '=', 'measurements.rpu')
        ->select('centrosCarga.*')
        ->groupBy('centrosCarga.id') // Group by the primary key of centrosCarga
        ->orderBy(DB::raw('MAX(measurements.date) IS NULL'), 'desc')
        ->orderBy(DB::raw('MAX(measurements.date)'), 'asc');

        if (count($rpusParam) > 0) {
            $centrosDeCargaQuery->whereIn('centrosCarga.rpu', $rpusParam);
        }
        $centrosDeCarga = $centrosDeCargaQuery->get();

        $centralesElectricasQuery = CentralElectrica::where('useMedicionesMediMEM', 1)
        ->leftJoin(
            'medicionesCentralElectrica',
            'centralElectrica.name',
            '=',
            'medicionesCentralElectrica.nombre'
        )
        ->select('centralElectrica.*')
        ->groupBy('centralElectrica.id') // Group by the primary key of CentralElectrica
        ->orderBy(DB::raw('MAX(medicionesCentralElectrica.fecha) IS NULL'), 'desc')
        ->orderBy(DB::raw('MAX(medicionesCentralElectrica.fecha)'), 'asc');

        if (count($rmusParam) > 0) {
            $centralesElectricasQuery->whereIn('centralElectrica.rmu', $rmusParam);
        }

        $centralesElectricas = $centralesElectricasQuery->get();
        $sendEmail = true;
        $centrosDeCargaSavedById = array();
        $centralesElectricaSavedById = array();

        // Load measurments for Centrales Electricas
        foreach ($centralesElectricas as $centralElectrica) {
            array_push($centralesElectricaSavedById, $centralElectrica->id);
            $dataFromChargeCenter = $centralElectrica->toArray();
            // Collect all measurements from multiple API calls
            $allRmu5MinutalJsonDataList = [];
            $hasApiError = false;
            
            foreach ($dateRanges as $range) {
                $formatedRRMU = str_replace(' ', '', $centralElectrica->rmu);
                $formatedRRMU = str_replace('-', '', $formatedRRMU);
                
                try {
                    $rmu5MinutalJsonDataList = $this->mediMEMService->getRPUMeasurements(
                        $formatedRRMU,
                        $range['start'],
                        $range['end'],
                        $centralElectrica->tokenMediMEM,
                        $sendEmail,
                    );
                } catch (Throwable $apiEx) {
                    $this->sendGoogleChatNotification(
                        "Falla HTTP/API (Central Eléctrica)",
                        "TeamID: {$centralElectrica->teamId} | Nombre: {$centralElectrica->name} | Error: " . $apiEx->getMessage(),
                        $centralElectrica->rmu
                    );
                    $hasApiError = true;
                    break;
                }

                if (null === $rmu5MinutalJsonDataList) {
                    $this->sendGoogleChatNotification(
                        "Respuesta nula o 401 (Central Eléctrica)",
                        "TeamID: {$centralElectrica->teamId} | Nombre: {$centralElectrica->name} | Rango: {$range['start']} a {$range['end']}.",
                        $centralElectrica->rmu
                    );
                    $hasApiError = true;
                    $sendEmail = false;
                    break;
                }

                // Merge the results
                $allRmu5MinutalJsonDataList = array_merge($allRmu5MinutalJsonDataList, $rmu5MinutalJsonDataList);
            }

            if ($hasApiError || empty($allRmu5MinutalJsonDataList)) {
                continue;
            }

            // Second step: Fill up missing data
            $datesRange = DateHelper::getDatesRange(
                $fechaInicio,
                $fechaFin
            );
            $filledUpRpu5MinutalJsonData = $this->fillUpGapMeasurements(
                $datesRange,
                $allRmu5MinutalJsonDataList
            );
            
            $csvContent = $this->parseJsonToCsvFile(
                $filledUpRpu5MinutalJsonData
            );
            
            $sistema      = $dataFromChargeCenter['sistema'];
            $diasFestivos = Festivo::distinct()
            ->select(['Fecha'])
            ->get()->pluck('Fecha')
            ->toArray();
            $registroUnico = $centralElectrica->rmu;

            $mesurementsTypeData = $this->parseMesurementsType(
                $csvContent,
                $registroUnico,
                $sistema
            );
            $measurementsTypeAndMissingDatesHourly = $this->measurementsRepo->setHourlyMeasureTypeAndMissingDatesVariables($mesurementsTypeData);// phpcs:ignore

            $mesurementsKwheData = $this->measurementsRepo->parseMesurementsData(
                $csvContent,
                $registroUnico,
                'kwhe',
                $sistema,
                true
            );

            $measurementskwheHourlySums = $this->measurementsRepo->setHourlySumVariables(
                $mesurementsKwheData,
                true
            );

            $mesurementsKwhrData = $this->measurementsRepo->parseMesurementsData(
                $csvContent,
                $registroUnico,
                'kwhr',
                $sistema,
                true
            );
            $measurementKwhrHourlySums = $this->measurementsRepo->setHourlySumVariables(
                $mesurementsKwhrData,
                true
            );

            $mesurementsKvarData  = $this->measurementsRepo->parseMesurementsData(
                $csvContent,
                $registroUnico,
                'kvarh',
                $sistema,
                true
            );
            $measurementKvarHourlySums = $this->measurementsRepo->setHourlySumVariables(
                $mesurementsKvarData,
                true
            );

            $demandaRoladaHourlyMax = $this->measurementsRepo->setHourlyDemandaRolada(
                $mesurementsKwheData
            );

            $horarioDataArray = $this->mergeDataArrays(
                $demandaRoladaHourlyMax,
                $measurementskwheHourlySums,
                $measurementKwhrHourlySums,
                $measurementKvarHourlySums,
                $measurementsTypeAndMissingDatesHourly
            );

            // Fourth step: add or insert data retrieved to database
            $rowsToInsertCE = [];
            foreach ($horarioDataArray as $rmu => $rmuData) {
                foreach ($rmuData as $date => $rmuDateData) {
                    foreach ($rmuDateData as $hour => $rmuHourData) {
                        $blockValue = $this->getBlock(
                            $date,
                            $hour,
                            $centralElectrica->name,
                            $dataFromChargeCenter,
                            $diasFestivos
                        );
                        $rowsToInsertCE[] = [
                            'nombre'     => $centralElectrica->name,
                            'rmu'        => $rmu,
                            'unidad'     => '',
                            'userId'     => $centralElectrica->userId,
                            'teamId'     => $centralElectrica->teamId,
                            'fileName'   => 'MEDIMEM mediciones Distribución',
                            'fecha'      => $date,
                            'hora'       => $hour,
                            'energiakWh' => $rmuHourData['kwhr'],
                            'KVArh'      => $rmuHourData['kvar'],
                            'blockCE'    => $blockValue ?? '',
                            'tipo'       => $rmuHourData['tipo'],
                            'claveNodo'  => sprintf(
                                "getNodoPByCentralElectrica('%s', %d)",
                                $centralElectrica->name,
                                $centralElectrica->teamId
                            ),
                            'createdAt'  => date('Y-m-d H:i:s'),
                        ];
                    }
                }
            }
            if (!empty($rowsToInsertCE)) {
                try {
                    $count = $this->measurementsRepo->bulkUpsertCEMeasurements($rowsToInsertCE);
                    $totalRecordsSynced += $count;
                    $processedMeters++;
                } catch (Throwable $e) {
                    $this->sendGoogleChatNotification(
                        "Excepción en BD al realizar Bulk Upsert (CE)",
                        $e->getMessage(),
                        $centralElectrica->rmu
                    );
                    Log::channel('task_errors')->error($e);
                }
            }

            // Save related CC measurer.
            if (null !== $dataFromChargeCenter['ccAsociado']) {
                $centroDeCarga = CentroCarga::where('teamId', $dataFromChargeCenter['teamId'])
                ->where('rpu', $dataFromChargeCenter['ccAsociado'])
                ->first();
                $dataFromChargeCenter = $centroDeCarga->toArray();

                array_push($centrosDeCargaSavedById, $centroDeCarga->id);

                // add or insert data retrieved to database
                $rowsToInsertCC = [];
                foreach ($horarioDataArray as $rmu => $rmuData) {
                    foreach ($rmuData as $date => $rmuDateData) {
                        foreach ($rmuDateData as $hour => $rmuHourData) {
                            $blockValue = $this->measurementsRepo->getBlock(
                                $date,
                                $hour,
                                $centroDeCarga->rpu,
                                $dataFromChargeCenter,
                                $diasFestivos
                            );
                            $rowsToInsertCC[] = [
                                'rpu'                => $centroDeCarga->rpu,
                                'rmu'                => $rmu,
                                'userId'             => $centroDeCarga->userId,
                                'teamId'             => $centroDeCarga->teamId,
                                'fileName'           => 'MEDIMEM mediciones Distribución',
                                'date'               => $date,
                                'hour'               => $hour,
                                'energy'             => $rmuHourData['kwhe'],
                                'kvarh'              => $rmuHourData['kvar'],
                                'block'              => $blockValue,
                                'rolledDemand'       => $rmuHourData['rolledDemand'],
                                'missingRecordAdded' => $rmuHourData['missingRecordAdded'],
                                'tipo'               => $rmuHourData['tipo'],
                                'createdAt'          => date('Y-m-d H:i:s'),
                                'claveNodo'          => sprintf(
                                    "getNodoPByRpu('%s', %d)",
                                    $centroDeCarga->rpu,
                                    $centroDeCarga->teamId
                                ),
                            ];
                        }
                    }
                }
                if (!empty($rowsToInsertCC)) {
                    try {
                        $count = $this->measurementsRepo->bulkUpsertCCMeasurements($rowsToInsertCC);
                        $totalRecordsSynced += $count;
                    } catch (Throwable $e) {
                        $this->sendGoogleChatNotification(
                            "Excepción en BD al realizar Bulk Upsert (CC Asociado)",
                            $e->getMessage(),
                            $centroDeCarga->rpu
                        );
                        Log::channel('task_errors')->error($e);
                    }
                }
            }
        }

        // Load measurments for Centros De Carga
        foreach ($centrosDeCarga as $centroDeCarga) {
            $dataFromChargeCenter = $centroDeCarga->toArray();
            if (in_array($centroDeCarga->id, $centrosDeCargaSavedById)) {
                // This CC was saved by Central electrica ccAsociado field
                continue;
            }

            // Collect all measurements from multiple API calls
            $allRpu5MinutalJsonDataList = [];
            foreach ($dateRanges as $range) {
                $formatedRRMU = str_replace(' ', '', $centroDeCarga->rmu);
                $formatedRRMU = str_replace('-', '', $formatedRRMU);
                try {
                    $rpu5MinutalJsonDataList = $this->mediMEMService->getRPUMeasurements(
                        $formatedRRMU,
                        $range['start'],
                        $range['end'],
                        $centroDeCarga->tokenMediMEM,
                        $sendEmail,
                    );
                } catch (Throwable $apiEx) {
                    $this->sendGoogleChatNotification(
                        "Falla HTTP/API (Centro de Carga)",
                        "TeamID: {$centroDeCarga->teamId} | Error: " . $apiEx->getMessage(),
                        $centroDeCarga->rpu
                    );
                    $sendEmail = false;
                    continue;
                }

                if (null === $rpu5MinutalJsonDataList) {
                    $this->sendGoogleChatNotification(
                        "Respuesta nula o 401 (Centro de Carga)",
                        "TeamID: {$centroDeCarga->teamId} | Rango: {$range['start']} a {$range['end']}.",
                        $centroDeCarga->rpu
                    );
                    $sendEmail = false;
                    continue;
                }

                // Merge the results
                $allRpu5MinutalJsonDataList = array_merge($allRpu5MinutalJsonDataList, $rpu5MinutalJsonDataList);
            }

            // Second step: Fill up missing data
            $datesRange = DateHelper::getDatesRange(
                $fechaInicio,
                $fechaFin
            );
            $filledUpRpu5MinutalJsonData = $this->fillUpGapMeasurements(
                $datesRange,
                $allRpu5MinutalJsonDataList
            );

            $csvContent = $this->parseJsonToCsvFile(
                $filledUpRpu5MinutalJsonData
            );

            $sistema = $dataFromChargeCenter['sistema'];
            $diasFestivos = Festivo::distinct()
            ->select(['Fecha'])
            ->get()->pluck('Fecha')
            ->toArray();
            $registroUnico = $centroDeCarga->rmu;

            $mesurementsTypeData= $this->parseMesurementsType(
                $csvContent,
                $registroUnico,
                $sistema
            );
            $measurementsTypeAndMissingDatesHourly = $this->measurementsRepo->setHourlyMeasureTypeAndMissingDatesVariables($mesurementsTypeData);// phpcs:ignore

            $mesurementsKwheData = $this->measurementsRepo->parseMesurementsData(
                $csvContent,
                $registroUnico,
                'kwhe',
                $sistema,
                true
            );

            $measurementskwheHourlySums = $this->measurementsRepo->setHourlySumVariables(
                $mesurementsKwheData,
                true
            );

            $mesurementsKwhrData = $this->measurementsRepo->parseMesurementsData(
                $csvContent,
                $registroUnico,
                'kwhr',
                $sistema,
                true
            );
            $measurementKwhrHourlySums = $this->measurementsRepo->setHourlySumVariables(
                $mesurementsKwhrData,
                true
            );

            $mesurementsKvarData  = $this->measurementsRepo->parseMesurementsData(
                $csvContent,
                $registroUnico,
                'kvarh',
                $sistema,
                true
            );
            $measurementKvarHourlySums = $this->measurementsRepo->setHourlySumVariables(
                $mesurementsKvarData,
                true
            );

            $demandaRoladaHourlyMax = $this->measurementsRepo->setHourlyDemandaRolada(
                $mesurementsKwheData
            );

            $horarioDataArray = $this->mergeDataArrays(
                $demandaRoladaHourlyMax,
                $measurementskwheHourlySums,
                $measurementKwhrHourlySums,
                $measurementKvarHourlySums,
                $measurementsTypeAndMissingDatesHourly
            );

            // Fourth step: add or insert data retrieved to database
            $rowsToInsertCC = [];
            foreach ($horarioDataArray as $rmu => $rmuData) {
                foreach ($rmuData as $date => $rmuDateData) {
                    foreach ($rmuDateData as $hour => $rmuHourData) {
                        $blockValue = $this->measurementsRepo->getBlock(
                            $date,
                            $hour,
                            $centroDeCarga->rpu,
                            $dataFromChargeCenter,
                            $diasFestivos
                        );

                        $rowsToInsertCC[] = [
                            'rpu'                => $centroDeCarga->rpu,
                            'rmu'                => $rmu,
                            'userId'             => $centroDeCarga->userId,
                            'teamId'             => $centroDeCarga->teamId,
                            'fileName'           => 'MEDIMEM mediciones Distribución',
                            'date'               => $date,
                            'hour'               => $hour,
                            'energy'             => $rmuHourData['kwhe'],
                            'kvarh'              => $rmuHourData['kvar'],
                            'block'              => $blockValue,
                            'rolledDemand'       => $rmuHourData['rolledDemand'],
                            'missingRecordAdded' => $rmuHourData['missingRecordAdded'],
                            'tipo'               => $rmuHourData['tipo'],
                            'createdAt'          => date('Y-m-d H:i:s'),
                            'claveNodo'          => sprintf(
                                "getNodoPByRpu('%s', %d)",
                                $centroDeCarga->rpu,
                                $centroDeCarga->teamId
                            ),
                        ];
                    }
                }
            }
            if (!empty($rowsToInsertCC)) {
                try {
                    $this->measurementsRepo->bulkUpsertCCMeasurements($rowsToInsertCC);
                } catch (Throwable $e) {
                    $this->sendGoogleChatNotification(
                        "Excepción en BD al realizar Bulk Upsert (CC Principal)",
                        $e->getMessage(),
                        $centroDeCarga->rpu
                    );
                    Log::channel('task_errors')->error($e);
                }
            }

            if (null !== $dataFromChargeCenter['ceAsociada']) {
                $centralElectrica = CentralElectrica::where(
                    'teamId',
                    $dataFromChargeCenter['teamId']
                )
                ->where('name', $dataFromChargeCenter['ceAsociada'])
                ->first();

                if ($centralElectrica === null) {
                    continue;
                }
                
                if (in_array($centralElectrica->id, $centralesElectricaSavedById)) {
                    // This CE was saved as main measurer.
                    continue;
                }

                // Fourth step: add or insert data retrieved to database
                $rowsToInsertCE = [];
                foreach ($horarioDataArray as $rmu => $rmuData) {
                    foreach ($rmuData as $date => $rmuDateData) {
                        foreach ($rmuDateData as $hour => $rmuHourData) {
                            $rowsToInsertCE[] = [
                                'nombre'     => $centralElectrica->name,
                                'rmu'        => $rmu,
                                'unidad'     => '',
                                'userId'     => $centralElectrica->userId,
                                'teamId'     => $centralElectrica->teamId,
                                'fileName'   => 'MEDIMEM mediciones Distribución',
                                'fecha'      => $date,
                                'hora'       => $hour,
                                'energiakWh' => $rmuHourData['kwhr'],
                                'KVArh'      => $rmuHourData['kvar'],
                                'tipo'       => $rmuHourData['tipo'],
                                'claveNodo'  => sprintf(
                                    "getNodoPByCentralElectrica('%s', %d)",
                                    $centralElectrica->name,
                                    $centralElectrica->teamId
                                ),
                                'createdAt'  => date('Y-m-d H:i:s'),
                            ];
                        }
                    }
                }
                if (!empty($rowsToInsertCE)) {
                    try {
                        $count = $this->measurementsRepo->bulkUpsertCEMeasurements($rowsToInsertCE);
                        $totalRecordsSynced += $count;
                    } catch (Throwable $e) {
                        $this->sendGoogleChatNotification(
                            "Excepción en BD al realizar Bulk Upsert (CE Asociada)",
                            $e->getMessage(),
                            $centralElectrica->name
                        );
                        Log::channel('task_errors')->error($e);
                    }
                }
            }
        }
        
        Log::info("INFO [MediMEMRepo]: Proceso de sincronización finalizado exitosamente. Medidores procesados: {$processedMeters}. Total registros upserted: {$totalRecordsSynced}.");

        } catch (Throwable $globalEx) {
            $this->sendGoogleChatNotification(
                "Falla Crítica Global en syncronizeMeasurements",
                $globalEx->getMessage() . " en línea " . $globalEx->getLine(),
                "GLOBAL"
            );
            Log::channel('task_errors')->error("ERROR CRÍTICO [MediMEMRepo]: " . $globalEx->getMessage());
        }
    }
}
