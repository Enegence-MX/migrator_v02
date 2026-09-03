<?php
/**
 * MediMEM repository file.
 *
 * PHP Version 7.4 / 8.2
 *
 * @category Repositories
 * @package Repositories
 * @author MCK <desarrollo@mck.agency>
 * @license Apache 2 https://www.apache.org/licenses/LICENSE-2.0
 * @link ''
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
 * @package Repositories
 * @author MCK <desarrollo@mck.agency>
 * @license Apache 2 https://www.apache.org/licenses/LICENSE-2.0
 * @link ''
 */
class MediMEMRepo
{
    use GetBlockValues;
    use MeasurementsRepoTrait;

    protected $mediMEMService;
    protected $measurementsRepo;

    /**
     * @var array
     */
    protected array $retryQueue = [];

    /**
     * Construct function.
     *
     * @param $mediMEMService Instance of MediMEMService.
     * @param $measurementsRepo Instance of MeasurementsRepo.
     *
     * @return void
     */
    public function __construct(
        MediMEMService $mediMEMService,
        MeasurementsRepo $measurementsRepo
    ) {
        $this->mediMEMService = $mediMEMService;
        $this->measurementsRepo = $measurementsRepo;
    }

    protected function sendGoogleChatNotification($title, $message, $rpuOrContext = 'N/A')
    {
        $webhookUrl = config('services.google_chat.webhook');
        if (empty($webhookUrl)) {
            $webhookUrl = 'https://chat.googleapis.com/v1/spaces/AAQAD3rf7Zs/messages?key=AIzaSyDdI0hCZtE6vySjMm-WEfRq3CPzqKqqsHI&token=ZaVhXYMwP0o1jDWkg7VpGU_7mWzuu--nBEw0eHYE7EM';
        }
        $tz = new \DateTimeZone('-0600');
        $date = new \DateTime('now', $tz);
        $fecha = $date->format('Y-m-d H:i:s');
        $payload = [
            'text' => "🚨 *ERROR EN TAREA MEDIMEM*\n*Contexto/RPU/RMU:* `{$rpuOrContext}`\n*Título:* {$title}\n*Detalle:* {$message}\n*Fecha:* " . $fecha
        ];
        
        try {
            if (class_exists('\Illuminate\Support\Facades\Http')) {
                Http::timeout(5)->post($webhookUrl, $payload);
            } else {
                $ch = curl_init($webhookUrl);
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

    private function roundRobinByTeamId($collection)
    {
        $grouped = $collection->groupBy('teamId');
        $interleaved = collect();
        while ($grouped->flatten()->isNotEmpty()) {
            foreach ($grouped as $teamId => $meters) {
                if ($meters->isNotEmpty()) {
                    $interleaved->push($meters->shift());
                    $grouped[$teamId] = $meters;
                }
            }
        }
        return $interleaved;
    }

    /**
     * Synchronizes measurement data for both electrical centers (Centrales Electricas)
     * and load centers (Centros de Carga).
     *
     * @param array $rpusParam Array of RPU identifiers for filtering load centers
     * @param array $rmusParam Array of RMU identifiers for filtering electrical centers
     * @param string $startDateParam Optional start date for data sync (Y-m-d format)
     * @param string $endDateParam Optional end date for data sync (Y-m-d format)
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
        $this->retryQueue = [];
        
        try {
            $yesterday = new DateTime();
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
            
            $dateRanges = DateHelper::splitDateRangeInto30DayChunks($fechaInicio, $fechaFin);
            
            $diasFestivos = Festivo::distinct()
                ->select(['Fecha'])
                ->get()
                ->pluck('Fecha')
                ->toArray();
                
            $centralesElectricasQuery = CentralElectrica::where('useMedicionesMediMEM', 1)
                ->leftJoin(
                    'medicionesCentralElectrica',
                    'centralElectrica.name',
                    '=',
                    'medicionesCentralElectrica.nombre'
                )
                ->select('centralElectrica.*')
                ->groupBy('centralElectrica.id')
                ->orderBy(DB::raw('MAX(medicionesCentralElectrica.fecha) IS NULL'), 'desc')
                ->orderBy(DB::raw('MAX(medicionesCentralElectrica.fecha)'), 'asc');
                
            if (count($rmusParam) > 0) {
                $centralesElectricasQuery->whereIn('centralElectrica.rmu', $rmusParam);
            }
            
            $centralesElectricas = $this->roundRobinByTeamId($centralesElectricasQuery->get());
            
            $centrosDeCargaQuery = CentroCarga::where('useMedicionesMediMEM', 1)
                ->leftJoin('measurements', 'centrosCarga.rpu', '=', 'measurements.rpu')
                ->select('centrosCarga.*')
                ->groupBy('centrosCarga.id')
                ->orderBy(DB::raw('MAX(measurements.date) IS NULL'), 'desc')
                ->orderBy(DB::raw('MAX(measurements.date)'), 'asc');
                
            if (count($rpusParam) > 0) {
                $centrosDeCargaQuery->whereIn('centrosCarga.rpu', $rpusParam);
            }
            
            $centrosDeCarga = $this->roundRobinByTeamId($centrosDeCargaQuery->get());
            $sendEmail = false;
            $centrosDeCargaSavedById = array();
            $centralesElectricaSavedById = array();
            
            foreach ($centralesElectricas as $centralElectrica) {
                array_push($centralesElectricaSavedById, $centralElectrica->id);
                $exito = $this->processCentralElectrica(
                    $centralElectrica,
                    $dateRanges,
                    $fechaInicio,
                    $fechaFin,
                    $diasFestivos,
                    $sendEmail,
                    $totalRecordsSynced,
                    $processedMeters,
                    $centrosDeCargaSavedById,
                    false
                );
                
                if (!$exito) {
                    $this->retryQueue[] = [
                        'tipo' => 'CE',
                        'entidad' => $centralElectrica,
                        'failed_at' => now()->toDateTimeString()
                    ];
                }
            }
            
            foreach ($centrosDeCarga as $centroDeCarga) {
                if (in_array($centroDeCarga->id, $centrosDeCargaSavedById)) {
                    continue;
                }
                $exito = $this->processCentroCarga(
                    $centroDeCarga,
                    $dateRanges,
                    $fechaInicio,
                    $fechaFin,
                    $diasFestivos,
                    $sendEmail,
                    $totalRecordsSynced,
                    $processedMeters,
                    $centralesElectricaSavedById,
                    false
                );
                
                if (!$exito) {
                    $this->retryQueue[] = [
                        'tipo' => 'CC',
                        'entidad' => $centroDeCarga,
                        'failed_at' => now()->toDateTimeString()
                    ];
                }
            }
            
            if (!empty($this->retryQueue)) {
                $totalFallidos = count($this->retryQueue);
                Log::warning(" [MediMEMRepo]: Se detectaron {$totalFallidos} medidores rechazados. Iniciando tiempo de enfriamiento de red (30s)...");

                sleep(30);
                Log::info(" [MediMEMRepo]: Ejecutando Reprocesamiento Final para los {$totalFallidos} medidores pendientes.");
                
                foreach ($this->retryQueue as $key => $item) {
                    $recuperado = false;
                    if ($item['tipo'] === 'CE') {
                        $recuperado = $this->processCentralElectrica(
                            $item['entidad'],
                            $dateRanges,
                            $fechaInicio,
                            $fechaFin,
                            $diasFestivos,
                            false,
                            $totalRecordsSynced,
                            $processedMeters,
                            $centrosDeCargaSavedById,
                            false
                        );
                    } else {
                        $recuperado = $this->processCentroCarga(
                            $item['entidad'],
                            $dateRanges,
                            $fechaInicio,
                            $fechaFin,
                            $diasFestivos,
                            false,
                            $totalRecordsSynced,
                            $processedMeters,
                            $centralesElectricaSavedById,
                            false
                        );
                    }
                    if ($recuperado) {
                        Log::info(" [MediMEMRepo]: Medidor recuperado con éxito durante el Reprocesamiento Final.");
                        unset($this->retryQueue[$key]); // Se retira de fallos definitivos
                    } else {
                        Log::error(" [MediMEMRepo]: Medidor volvió a fallar en el Reprocesamiento Final.");
                    }
                    usleep(300000);
                }
                $this->retryQueue = array_values($this->retryQueue);
            }
            
            if (!empty($this->retryQueue)) {
                foreach ($this->retryQueue as $caidoDefinitivo) {
                    $entidad = $caidoDefinitivo['entidad'];
                    $rpuOrRmu = $caidoDefinitivo['tipo'] === 'CE' ? $entidad->rmu : $entidad->rpu;
                    $nombreTipo = $caidoDefinitivo['tipo'] === 'CE' ? 'Central Eléctrica' : 'Centro de Carga';
                    $this->sendGoogleChatNotification(
                        "Respuesta nula o 401 tras Reprocesamiento Final ({$nombreTipo})",
                        "TeamID: {$entidad->teamId} | Medidor rechazado tras 2 vueltas completas de sincronización.",
                        $rpuOrRmu
                    );
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

    private function processCentralElectrica(
        $centralElectrica,
        $dateRanges,
        $fechaInicio,
        $fechaFin,
        $diasFestivos,
        $sendEmail,
        &$totalRecordsSynced,
        &$processedMeters,
        &$centrosDeCargaSavedById,
        $sendAlertImmediately = true
    ): bool {
        $dataFromChargeCenter = $centralElectrica->toArray();
        $allRmu5MinutalJsonDataList = [];
        $hasApiError = false;
        
        foreach ($dateRanges as $range) {
            $formatedRRMU = str_replace([' ', '-'], '', $centralElectrica->rmu);
            $rmu5MinutalJsonDataList = null;
            $maxApiAttempts = 3;
            $apiAttempt = 0;
            
            while ($apiAttempt < $maxApiAttempts) {
                $apiAttempt++;
                try {
                    $rmu5MinutalJsonDataList = $this->mediMEMService->getRPUMeasurements(
                        $formatedRRMU,
                        $range['start'],
                        $range['end'],
                        $centralElectrica->tokenMediMEM,
                        $sendEmail
                    );
                    if (null !== $rmu5MinutalJsonDataList) {
                        break;
                    }
                } catch (Throwable $apiEx) {
                    if ($apiAttempt >= $maxApiAttempts) {
                        if ($sendAlertImmediately) {
                            $this->sendGoogleChatNotification(
                                "Falla HTTP/API (Central Eléctrica tras {$maxApiAttempts} reintentos)",
                                "TeamID: {$centralElectrica->teamId} | Nombre: {$centralElectrica->name} | Error: " . $apiEx->getMessage(),
                                $centralElectrica->rmu
                            );
                        }
                        $hasApiError = true;
                        break 2;
                    }
                }
                $backoffSeconds = pow(2, $apiAttempt);
                sleep($backoffSeconds);
            }
            
            if (null === $rmu5MinutalJsonDataList) {
                if ($sendAlertImmediately) {
                    $this->sendGoogleChatNotification(
                        "Respuesta nula o 401 (Central Eléctrica tras {$maxApiAttempts} reintentos)",
                        "TeamID: {$centralElectrica->teamId} | Nombre: {$centralElectrica->name} | Rango: {$range['start']} a {$range['end']}.",
                        $centralElectrica->rmu
                    );
                }
                $hasApiError = true;
                break;
            }
            
            if (empty($rmu5MinutalJsonDataList)) {
                $this->sendGoogleChatNotification(
                    "API CFE devolvió 0 registros (Central Eléctrica)",
                    "TeamID: {$centralElectrica->teamId} | La API respondió sin lecturas (arreglo vacío []) para el rango {$range['start']} a {$range['end']}.",
                    $centralElectrica->rmu
                );
            }
            
            $allRmu5MinutalJsonDataList = array_merge($allRmu5MinutalJsonDataList, $rmu5MinutalJsonDataList);
            usleep(300000);
        }
        
        if ($hasApiError || empty($allRmu5MinutalJsonDataList)) {
            return false;
        }
        
        $datesRange = DateHelper::getDatesRange($fechaInicio, $fechaFin);
        $filledUpRpu5MinutalJsonData = $this->fillUpGapMeasurements($datesRange, $allRmu5MinutalJsonDataList);
        $csvContent = $this->parseJsonToCsvFile($filledUpRpu5MinutalJsonData);
        $sistema = $dataFromChargeCenter['sistema'];
        $registroUnico = $centralElectrica->rmu;
        $mesurementsTypeData = $this->parseMesurementsType($csvContent, $registroUnico, $sistema);
        $measurementsTypeAndMissingDatesHourly = $this->measurementsRepo->setHourlyMeasureTypeAndMissingDatesVariables($mesurementsTypeData);
        $mesurementsKwheData = $this->measurementsRepo->parseMesurementsData($csvContent, $registroUnico, 'kwhe', $sistema, true);
        $measurementskwheHourlySums = $this->measurementsRepo->setHourlySumVariables($mesurementsKwheData, true);
        $mesurementsKwhrData = $this->measurementsRepo->parseMesurementsData($csvContent, $registroUnico, 'kwhr', $sistema, true);
        $measurementKwhrHourlySums = $this->measurementsRepo->setHourlySumVariables($mesurementsKwhrData, true);
        $mesurementsKvarData = $this->measurementsRepo->parseMesurementsData($csvContent, $registroUnico, 'kvarh', $sistema, true);
        $measurementKvarHourlySums = $this->measurementsRepo->setHourlySumVariables($mesurementsKvarData, true);
        $demandaRoladaHourlyMax = $this->measurementsRepo->setHourlyDemandaRolada($mesurementsKwheData);
        $horarioDataArray = $this->mergeDataArrays(
            $demandaRoladaHourlyMax,
            $measurementskwheHourlySums,
            $measurementKwhrHourlySums,
            $measurementKvarHourlySums,
            $measurementsTypeAndMissingDatesHourly
        );
        $rowsToInsertCE = [];
        
        foreach ($horarioDataArray as $rmu => $rmuData) {
            foreach ($rmuData as $date => $rmuDateData) {
                foreach ($rmuDateData as $hour => $rmuHourData) {
                    $blockValue = $this->getBlock($date, $hour, $centralElectrica->name, $dataFromChargeCenter, $diasFestivos);
                    $rowsToInsertCE[] = [
                        'nombre' => $centralElectrica->name,
                        'rmu' => $rmu,
                        'unidad' => '',
                        'userId' => $centralElectrica->userId,
                        'teamId' => $centralElectrica->teamId,
                        'fileName' => 'MEDIMEM mediciones Distribución',
                        'fecha' => $date,
                        'hora' => $hour,
                        'energiakWh' => $rmuHourData['kwhr'],
                        'KVArh' => $rmuHourData['kvar'],
                        'blockCE' => $blockValue ?? '',
                        'tipo' => $rmuHourData['tipo'],
                        'claveNodo' => sprintf("getNodoPByCentralElectrica('%s', %d)", $centralElectrica->name, $centralElectrica->teamId),
                        'createdAt' => date('Y-m-d H:i:s'),
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
                Log::channel('task_errors')->error($e);
                return false;
            }
        }
        
        if (null !== $dataFromChargeCenter['ccAsociado']) {
            $centroDeCarga = CentroCarga::where('teamId', $dataFromChargeCenter['teamId'])
                ->where('rpu', $dataFromChargeCenter['ccAsociado'])
                ->first();
                
            if ($centroDeCarga) {
                $dataFromChargeCenterCC = $centroDeCarga->toArray();
                array_push($centrosDeCargaSavedById, $centroDeCarga->id);
                $rowsToInsertCC = [];
                foreach ($horarioDataArray as $rmu => $rmuData) {
                    foreach ($rmuData as $date => $rmuDateData) {
                        foreach ($rmuDateData as $hour => $rmuHourData) {
                            $blockValue = $this->measurementsRepo->getBlock($date, $hour, $centroDeCarga->rpu, $dataFromChargeCenterCC, $diasFestivos);
                            $rowsToInsertCC[] = [
                                'rpu' => $centroDeCarga->rpu,
                                'rmu' => $rmu,
                                'userId' => $centroDeCarga->userId,
                                'teamId' => $centroDeCarga->teamId,
                                'fileName' => 'MEDIMEM mediciones Distribución',
                                'date' => $date,
                                'hour' => $hour,
                                'energy' => $rmuHourData['kwhe'],
                                'kvarh' => $rmuHourData['kvar'],
                                'block' => $blockValue,
                                'rolledDemand' => $rmuHourData['rolledDemand'],
                                'missingRecordAdded' => $rmuHourData['missingRecordAdded'],
                                'tipo' => $rmuHourData['tipo'],
                                'createdAt' => date('Y-m-d H:i:s'),
                                'claveNodo' => sprintf("getNodoPByRpu('%s', %d)", $centroDeCarga->rpu, $centroDeCarga->teamId),
                            ];
                        }
                    }
                }
                
                if (!empty($rowsToInsertCC)) {
                    try {
                        $countCC = $this->measurementsRepo->bulkUpsertCCMeasurements($rowsToInsertCC);
                        $totalRecordsSynced += $countCC;
                    } catch (Throwable $e) {
                        Log::channel('task_errors')->error($e);
                    }
                }
            }
        }
        return true;
    }

    private function processCentroCarga(
        $centroDeCarga,
        $dateRanges,
        $fechaInicio,
        $fechaFin,
        $diasFestivos,
        $sendEmail,
        &$totalRecordsSynced,
        &$processedMeters,
        &$centralesElectricaSavedById,
        $sendAlertImmediately = true
    ): bool {
        $dataFromChargeCenter = $centroDeCarga->toArray();
        $allRpu5MinutalJsonDataList = [];
        $hasApiError = false;
        
        foreach ($dateRanges as $range) {
            $formatedRRMU = str_replace([' ', '-'], '', $centroDeCarga->rmu);
            $rpu5MinutalJsonDataList = null;
            $maxApiAttempts = 3;
            $apiAttempt = 0;
            while ($apiAttempt < $maxApiAttempts) {
                $apiAttempt++;
                try {
                    $rpu5MinutalJsonDataList = $this->mediMEMService->getRPUMeasurements(
                        $formatedRRMU,
                        $range['start'],
                        $range['end'],
                        $centroDeCarga->tokenMediMEM,
                        $sendEmail
                    );
                    if (null !== $rpu5MinutalJsonDataList) {
                        break;
                    }
                } catch (Throwable $apiEx) {
                    if ($apiAttempt >= $maxApiAttempts) {
                        if ($sendAlertImmediately) {
                            $this->sendGoogleChatNotification(
                                "Falla HTTP/API (Centro de Carga tras {$maxApiAttempts} reintentos)",
                                "TeamID: {$centroDeCarga->teamId} | Error: " . $apiEx->getMessage(),
                                $centroDeCarga->rpu
                            );
                        }
                        $hasApiError = true;
                        break 2;
                    }
                }
                $backoffSeconds = pow(2, $apiAttempt);
                sleep($backoffSeconds);
            }
            if (null === $rpu5MinutalJsonDataList) {
                if ($sendAlertImmediately) {
                    $this->sendGoogleChatNotification(
                        "Respuesta nula o 401 (Centro de Carga tras {$maxApiAttempts} reintentos)",
                        "TeamID: {$centroDeCarga->teamId} | Rango: {$range['start']} a {$range['end']}.",
                        $centroDeCarga->rpu
                    );
                }
                $hasApiError = true;
                break;
            }
            
            if (empty($rpu5MinutalJsonDataList)) {
                $this->sendGoogleChatNotification(
                    "API CFE devolvió 0 registros (Centro de Carga)",
                    "TeamID: {$centroDeCarga->teamId} | La API respondió sin lecturas (arreglo vacío []) para el rango {$range['start']} a {$range['end']}.",
                    $centroDeCarga->rpu
                );
            }
            
            $allRpu5MinutalJsonDataList = array_merge($allRpu5MinutalJsonDataList, $rpu5MinutalJsonDataList);
            usleep(300000);
        }
        if ($hasApiError || empty($allRpu5MinutalJsonDataList)) {
            return false;
        }
        
        $datesRange = DateHelper::getDatesRange($fechaInicio, $fechaFin);
        $filledUpRpu5MinutalJsonData = $this->fillUpGapMeasurements($datesRange, $allRpu5MinutalJsonDataList);
        $csvContent = $this->parseJsonToCsvFile($filledUpRpu5MinutalJsonData);
        $sistema = $dataFromChargeCenter['sistema'];
        $registroUnico = $centroDeCarga->rmu;
        $mesurementsTypeData = $this->parseMesurementsType($csvContent, $registroUnico, $sistema);
        $measurementsTypeAndMissingDatesHourly = $this->measurementsRepo->setHourlyMeasureTypeAndMissingDatesVariables($mesurementsTypeData);
        $mesurementsKwheData = $this->measurementsRepo->parseMesurementsData($csvContent, $registroUnico, 'kwhe', $sistema, true);
        $measurementskwheHourlySums = $this->measurementsRepo->setHourlySumVariables($mesurementsKwheData, true);
        $mesurementsKwhrData = $this->measurementsRepo->parseMesurementsData($csvContent, $registroUnico, 'kwhr', $sistema, true);
        $measurementKwhrHourlySums = $this->measurementsRepo->setHourlySumVariables($mesurementsKwhrData, true);
        $mesurementsKvarData = $this->measurementsRepo->parseMesurementsData($csvContent, $registroUnico, 'kvarh', $sistema, true);
        $measurementKvarHourlySums = $this->measurementsRepo->setHourlySumVariables($mesurementsKvarData, true);
        $demandaRoladaHourlyMax = $this->measurementsRepo->setHourlyDemandaRolada($mesurementsKwheData);
        $horarioDataArray = $this->mergeDataArrays(
            $demandaRoladaHourlyMax,
            $measurementskwheHourlySums,
            $measurementKwhrHourlySums,
            $measurementKvarHourlySums,
            $measurementsTypeAndMissingDatesHourly
        );
        $rowsToInsertCC = [];
        foreach ($horarioDataArray as $rmu => $rmuData) {
            foreach ($rmuData as $date => $rmuDateData) {
                foreach ($rmuDateData as $hour => $rmuHourData) {
                    $blockValue = $this->measurementsRepo->getBlock($date, $hour, $centroDeCarga->rpu, $dataFromChargeCenter, $diasFestivos);
                    $rowsToInsertCC[] = [
                        'rpu' => $centroDeCarga->rpu,
                        'rmu' => $rmu,
                        'userId' => $centroDeCarga->userId,
                        'teamId' => $centroDeCarga->teamId,
                        'fileName' => 'MEDIMEM mediciones Distribución',
                        'date' => $date,
                        'hour' => $hour,
                        'energy' => $rmuHourData['kwhe'],
                        'kvarh' => $rmuHourData['kvar'],
                        'block' => $blockValue,
                        'rolledDemand' => $rmuHourData['rolledDemand'],
                        'missingRecordAdded' => $rmuHourData['missingRecordAdded'],
                        'tipo' => $rmuHourData['tipo'],
                        'createdAt' => date('Y-m-d H:i:s'),
                        'claveNodo' => sprintf("getNodoPByRpu('%s', %d)", $centroDeCarga->rpu, $centroDeCarga->teamId),
                    ];
                }
            }
        }
        if (!empty($rowsToInsertCC)) {
            try {
                $countCC = $this->measurementsRepo->bulkUpsertCCMeasurements($rowsToInsertCC);
                $totalRecordsSynced += $countCC;
                $processedMeters++;
            } catch (Throwable $e) {
                Log::channel('task_errors')->error($e);
                return false;
            }
        }
        
        if (null !== $dataFromChargeCenter['ceAsociada']) {
            $centralElectrica = CentralElectrica::where('teamId', $dataFromChargeCenter['teamId'])
                ->where('name', $dataFromChargeCenter['ceAsociada'])
                ->first();
            if ($centralElectrica && !in_array($centralElectrica->id, $centralesElectricaSavedById)) {
                $rowsToInsertCE = [];
                foreach ($horarioDataArray as $rmu => $rmuData) {
                    foreach ($rmuData as $date => $rmuDateData) {
                        foreach ($rmuDateData as $hour => $rmuHourData) {
                            $rowsToInsertCE[] = [
                                'nombre' => $centralElectrica->name,
                                'rmu' => $rmu,
                                'unidad' => '',
                                'userId' => $centralElectrica->userId,
                                'teamId' => $centralElectrica->teamId,
                                'fileName' => 'MEDIMEM mediciones Distribución',
                                'fecha' => $date,
                                'hora' => $hour,
                                'energiakWh' => $rmuHourData['kwhr'],
                                'KVArh' => $rmuHourData['kvar'],
                                'tipo' => $rmuHourData['tipo'],
                                'claveNodo' => sprintf("getNodoPByCentralElectrica('%s', %d)", $centralElectrica->name, $centralElectrica->teamId),
                                'createdAt' => date('Y-m-d H:i:s'),
                            ];
                        }
                    }
                }
                if (!empty($rowsToInsertCE)) {
                    try {
                        $countCE = $this->measurementsRepo->bulkUpsertCEMeasurements($rowsToInsertCE);
                        $totalRecordsSynced += $countCE;
                    } catch (Throwable $e) {
                        Log::channel('task_errors')->error($e);
                    }
                }
            }
        }
        return true;
    }
}
