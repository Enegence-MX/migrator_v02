<?php
/**
 * Measurements repository file.
 *
 * PHP Version 7.4
 *
 * @category Repositories
 * @package  Repositories
 * @author   MCK <developer@mck.agency>
 * @license  Apache 2 https://www.apache.org/licenses/LICENSE-2.0
 * @link     ''
 */

namespace App\Http\Repositories;

use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Http\Helpers\DaylightSavingHelper;
use App\Http\Traits\GetBlockValues;
use Illuminate\Support\Facades\Log;

/**
 * Class service for Measruements from xml and csv file.
 *
 * @category Repositories
 * @package  Repositories
 * @author   Developer <programacion@mck.agency>
 * @license  Apache 2 https://www.apache.org/licenses/LICENSE-2.0
 * @link     ''
 */
class MeasurementsRepo
{
    
    use GetBlockValues;

    /**
     * Function to read data from csv file in storage dir.
     *
     * @param $content File measurement content.
     * @param $rpu RPU id.
     * @param $WhOrVAR Type of energy (kwhe | kwhr | kvarh).
     * @param $sistema RPU system SIM, BCN, BCS.
     *
     * @return string
     */
    public function parseMesurementsData(
        $content,
        $rpu,
        $WhOrVAR = 'Wh',
        $sistema, //phpcs:ignore
        $canBeNull = false
    ) {
        if ($WhOrVAR == 'Wh' || $WhOrVAR == 'kwhe') {
            $measurementIndex = 2;
        } elseif ($WhOrVAR == 'kwhr') {
            $measurementIndex = 3;
        } elseif ($WhOrVAR == 'kvarh') {
            $measurementIndex = 4;
        } else {
            $measurementIndex = 4;
        }

        $rpuMesuarementsByDate = [];

        $lines     = explode(PHP_EOL, $content);
        $fieldsRow = 0;

        foreach ($lines as $key => $line) {
            if (($line != '') && ($key > $fieldsRow)) {
                $row               = str_getcsv($line);
                $dateTime          = $row[1];
                list($date, $time) = explode(' ', $dateTime);
                $dayligthSavings   = DaylightSavingHelper::checkDaylightSavingsDay($date, $sistema);
                $hour              = substr($time, 0, 2);
                $minute            = substr($time, 3, 2);

                //to remove ledding zero
                $intHour   = intval($hour);
                $intMinute = intval($minute);

                if ($dayligthSavings != false) {
                    //if daylight saving is summer related (summer OR summerOneDayAfter)
                    if ($dayligthSavings[0] == 's') {
                        if ($dayligthSavings == 'summer') {
                            //mesurents from 02:05 to 03:00 should be removed
                            $hourThreeNotInclude = ($intHour == 3 && $intMinute == 0);
                            if ($intHour <= 2 || $hourThreeNotInclude) {
                                //to skip mesurents from 02:05 to 03:00
                                $hourTwoNotInclude = $intHour == 2 && $intMinute != 0;

                                if ($hourTwoNotInclude || $hourThreeNotInclude) {
                                    //to exclude this mesurement range: 02:05 to 03:00
                                    continue;
                                }

                                if ($minute != '00') {
                                    $intHour = $intHour + 1;
                                }
                            } else { //time from 03:05
                                if ($minute == '00') {
                                    $intHour = $intHour - 1;
                                }
                            }
                                
                            //if time is 00:00 set date a day before
                            if ($time == '00:00:00' || $time == '00:00') {
                                $date    = date("Y-m-d", strtotime($date. ' -1 day'));
                                $intHour = '24';
                            }
                        } else {
                            //summer daylight saving date one day after
                            if ($time == '00:00:00' || $time == '00:00') {
                                $date    = date("Y-m-d", strtotime($date. ' -1 day'));
                                $intHour = '23';
                                /**
                                 * As to not consider hour 24 of summer dalylight
                                 * savings date, to test to know if to keep or not
                                 **/
                            }

                            //place register hour to comming calcuted hour
                            if ($minute != '00' &&  $intHour != 24) {
                                $intHour = $intHour + 1;
                            }
                        }
                    } else {//winter dayligth savings
                        if ($dayligthSavings == 'winter') {
                            $registeredTime         = date(
                                'Y-m-d H:i',
                                strtotime($date.' '.$time)
                            );
                            $validateEndTime        = date(
                                'Y-m-d H:i',
                                strtotime($date.' 02:00')
                            );
                            $validateStartTime      = date(
                                'Y-m-d H:i',
                                strtotime($date.' 00:55')
                            );
                            $beforeValidateEndTime  = $registeredTime < $validateEndTime;
                            $afterValidateStartTime = $registeredTime = $validateStartTime;

                            //before 02:00, 01:55 and lower
                            if ($beforeValidateEndTime) {
                                if ($minute != '00') {
                                    $intHour = $intHour + 1;
                                }
                                //hours between 01:00 and 01:55, which are the repeating values
                                if ($afterValidateStartTime) {

                                    /**
                                     * if data hour mesurements have been set
                                     * (repeated date hour values) add another hour to
                                     * calulated hour key
                                     **/
                                    if (isset(
                                        $rpuMesuarementsByDate[$rpu][$date][$intHour][$dateTime]
                                    )) {
                                        $intHourRepetead = $intHour + 1;
                                        $rpuMesuarementsByDate[$rpu][$date]
                                        [$intHourRepetead][$dateTime] = ('' !== $row[$measurementIndex] || !$canBeNull) //phpcs:ignore
                                        ? floatval($row[$measurementIndex])
                                        : null;
                                        continue;
                                    }
                                }
                            } else { //time from 02:05 and up
                                if ($minute != '00') {
                                    $intHour = $intHour + 2;
                                } else {
                                    $intHour = $intHour + 1;
                                }
                            }

                            //if time is 00:00 set date a day before
                            if ($time == '00:00:00' || $time == '00:00') {
                                $date    = date("Y-m-d", strtotime($date. ' -1 day'));
                                $intHour = '24';
                            }
                        } else {
                            //winter daylight saving date one day after
                            //if time is 00:00 set date a day before
                            if ($time == '00:00:00' || $time == '00:00') {
                                $date    = date("Y-m-d", strtotime($date. ' -1 day'));
                                $intHour = '25';
                            }
                            //place register hour to comming calcuted hour
                            if ($minute != '00' &&  $intHour != 24) {
                                $intHour = $intHour + 1;
                            }
                        }
                    }
                } else { //non dalylight savings day
                    //if time is 00:00 set date a day before
                    if ($time == '00:00:00' || $time == '00:00') {
                        $date    = date("Y-m-d", strtotime($date. ' -1 day'));
                        $intHour = '24';
                    }

                    //place register hour to comming calcuted hour
                    if ($minute != '00' &&  $intHour != 24) {
                        $intHour = $intHour + 1;
                    }
                }

                $rpuMesuarementsByDate[$rpu][$date][$intHour][$dateTime] = ('' !== $row[$measurementIndex] || !$canBeNull) //phpcs:ignore
                ? floatval($row[$measurementIndex])
                : null;
            }
        }

        $this->orderMesurementsKeys($rpuMesuarementsByDate);

        return $rpuMesuarementsByDate;
    }

    /**
     * Function to read data from csv file in storage dir.
     *
     * @param $rpuRawMesuarements Array data.
     *
     * @return void
     */
    public function orderMesurementsKeys(&$rpuRawMesuarements)
    {
        ksort($rpuRawMesuarements);

        foreach ($rpuRawMesuarements as $rpuKey => &$nestedLevelOne) {
            //rpu key
            ksort($nestedLevelOne);

            foreach ($nestedLevelOne as $dateOrHourKeyA => &$nestedLevelTwo) {
                //date or hour key Alpha
                ksort($nestedLevelTwo);

                foreach ($nestedLevelTwo as $dateOrHourKeyB => &$nestedLevelThree) {
                    //date or hour key Beta
                    ksort($nestedLevelThree);
                }
            }
        }

        return;
    }

    /**
     * Function to read data from csv file in storage dir.
     *
     * @param $mesurementsData Json array data.
     *
     * @return array
     */
    public function setHourlySumVariables($mesurementsData, $canBeNull = false)
    {
        foreach ($mesurementsData as $rpuKey => $dateMesurements) {
            foreach ($dateMesurements as $dateKey => $hourlyMesurements) {
                foreach ($hourlyMesurements as $hourKey => $registeredMesurements) {
                    $isNullArray = array_reduce($registeredMesurements, function ($carry, $item) {
                        return $carry && $item === null;
                    }, true);
                    $hourlySum = ($isNullArray && $canBeNull)
                    ? null
                    : array_sum($registeredMesurements);

                    $rpuDateHourSums[$rpuKey][$dateKey][$hourKey] = $hourlySum;
                }
            }
        }

        return $rpuDateHourSums;
    }

    /**
     * This function processes measurement data to find the most common measurement type
     * for each hour of each day for each RPU (Remote Processing Unit).
     *
     * @param array $measurementsData An associative array containing measurements data.
     *
     * @return array An associative array containing the most common measurement type
    */
    public function setHourlyMeasureTypeAndMissingDatesVariables($mesurementsData)
    {
        foreach ($mesurementsData as $rpuKey => $dateMesurements) {
            foreach ($dateMesurements as $dateKey => $hourlyMesurements) {
                foreach ($hourlyMesurements as $hourKey => $registeredMesurements) {
                    // Count the occurrences of each value
                    $valueCounts = array_count_values($registeredMesurements);
                    $type = array_search(max($valueCounts), $valueCounts);

                    $totalRows = count($registeredMesurements);
                    $missingRecords = ($totalRows < 12) ? 'yes' : null;

                    $rpuDateHourMeasureType[$rpuKey][$dateKey][$hourKey] = array(
                        'type' => $type,
                        'missingRecords' => $missingRecords
                    );
                }
            }
        }

        return $rpuDateHourMeasureType;
    }

    /**
     * Function to read data from csv file in storage dir.
     *
     * @param $mesurementsData Json array data.
     *
     * @return array
     */
    public function setHourlyDemandaRolada($mesurementsData)
    {
        $measurementsDataWithAddededColums = [];
        $measurementsDataDemandaRolada     = [];

        //# add temp cell values to get hourly demanda rolada
        foreach ($mesurementsData as $rpuKey => $dateMesurements) {
            $sequentialDemadaValues = [];
            foreach ($dateMesurements as $dateKey => $hourlyMesurements) {
                foreach ($hourlyMesurements as $hourKey => $registeredMesurements) {
                    foreach ($registeredMesurements as $fiveMinuteKey => $fiveMinuteValue) {
                        $indexProxy               = count($sequentialDemadaValues);
                        $demanda                  = $fiveMinuteValue * 12;
                        $sequentialDemadaValues[] = $demanda;
                        $demandaRolada            = 0;

                        if (count($sequentialDemadaValues) > 2) {
                            $current            = $sequentialDemadaValues[$indexProxy];
                            $previous           = $sequentialDemadaValues[$indexProxy-1];
                            $previousOfprevious = $sequentialDemadaValues[$indexProxy-2];
                            $demandaRolada      = ($current + $previous + $previousOfprevious) / 3;
                        }

                        $measurementsDataWithAddededColums[$rpuKey][$dateKey]
                        [$hourKey][$fiveMinuteKey] = [
                            'energy'        => $fiveMinuteValue,
                            'demanda'       => $demanda,
                            'demandaRolada' => $demandaRolada,
                        ];

                        $measurementsDataDemandaRolada[$rpuKey][$dateKey]
                        [$hourKey][$fiveMinuteKey] = $demandaRolada;
                    }
                }
            }
        }

        foreach ($measurementsDataDemandaRolada as $rpuKey => $dateMesurements) {
            foreach ($dateMesurements as $dateKey => $hourlyMesurements) {
                foreach ($hourlyMesurements as $hourKey => $registeredMesurements) {
                    $hourlyMax = max(
                        $registeredMesurements
                    );
                    $rpuDateHourMaxs[$rpuKey][$dateKey][$hourKey] = $hourlyMax;
                }
            }
        }

        return $rpuDateHourMaxs;
    }

    /**
     * Function to save measurements data from CFE WS service.
     *
     * @param $arrayData Array of table values.
     *
     * @return array
     */
    public function updateOrInsertCCMeasurement($arrayData)
    {

        DB::connection('mysql_dev_2')->beginTransaction();
        try {
            DB::connection('mysql_dev_2')->table('measurements')
            ->updateOrInsert(
                [
                    'teamId' => $arrayData['teamId'],
                    'date'   => $arrayData['date'],
                    'hour'   => $arrayData['hour'],
                    'rpu'    => $arrayData['rpu'],
                ],
                $arrayData
            );
            DB::connection('mysql_dev_2')->commit();
        } catch (Exception $e) {
            DB::connection('mysql_dev_2')->rollback();
            throw $e;
        }
    }

    /**
     * Resilient function to save measurements data.
     * Similar to updateOrInsertCCMeasurement but if a deadlock is found tryagin.
     *
     * @param $arrayData Array of table values.
     *
     * @return array
     */
    public function updateOrInsertCCMeasurementWithRetry($arrayData, $maxRetries = 3)
    {
        $retries = 0;
        
        while ($retries < $maxRetries) {
            try {
                DB::beginTransaction();
                
                DB::connection('mysql_dev_2')->table('measurements')
                ->updateOrInsert(
                    [
                        'teamId' => $arrayData['teamId'],
                        'date'   => $arrayData['date'],
                        'hour'   => $arrayData['hour'],
                        'rpu'    => $arrayData['rpu'],
                    ],
                    $arrayData
                );
                
                DB::commit();
                return true; // Success
            } catch (QueryException $e) {
                DB::rollBack();
                
                if ($e->getCode() == '40001') { // Deadlock error code
                    $retries++;
                    if ($retries >= $maxRetries) {
                        throw $e; // Max retries reached, re-throw the exception
                    }
                    usleep(500000); // Sleep of 0.5 seconds to await for unlock data.
                    Log::channel('task_errors')->error("40001 Deadlock on mysql_dev_2 measurements connection");
                } else {
                    throw $e; // If it's not a deadlock error, re-throw the exception
                }
            }
        }
    }

    /**
     * Function to save measurements data from CFE WS service.
     *
     * @param $arrayData Array of table values.
     *
     * @return array
     */
    public function updateOrInsertCEMeasurement($arrayData)
    {
        DB::connection('mysql_dev_2')->beginTransaction();
        try {
            DB::connection('mysql_dev_2')->table('medicionesCentralElectrica')
            ->updateOrInsert(
                [
                    'nombre' => $arrayData['nombre'],
                    'rmu'    => $arrayData['rmu'],
                    'teamId' => $arrayData['teamId'],
                    'fecha'  => $arrayData['fecha'],
                    'hora'   => $arrayData['hora'],
                ],
                $arrayData
            );
            DB::connection('mysql_dev_2')->commit();
        } catch (QueryException $e) {
            DB::connection('cloud_sql')->rollback();

            // Skip SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry
            if ($e->getCode() == '23000') {
                return true; // Record already exists, take as success
            }
            Log::channel('task_errors')->error($e);
            throw $e;
        } catch (Exception $e) {
            DB::connection('mysql_dev_2')->rollback();
            Log::channel('task_errors')->error($e);
            throw $e;
        }
    }

    /**
     * Realiza un Upsert masivo idempotente compatible con Laravel 6 para Centros de Carga.
     * Tolerante a Deadlocks (código 40001).
     *
     * @param array $rows Array de datos a insertar.
     * @param int $maxRetries Cantidad de reintentos por deadlock.
     * @return int Cantidad total de registros procesados.
     */
    public function bulkUpsertCCMeasurements(array $rows, $maxRetries = 3)
    {
        if (empty($rows)) {
            return 0;
        }

        $chunks = array_chunk($rows, 500);
        $totalProcessed = 0;
        $conn = DB::connection('mysql_dev_2');
        $pdo = $conn->getPdo();

        foreach ($chunks as $chunk) {
            $values = [];

            foreach ($chunk as $row) {
                $rpu       = $pdo->quote($row['rpu']);
                $rmu       = isset($row['rmu']) ? $pdo->quote($row['rmu']) : 'NULL';
                $userId    = (int) $row['userId'];
                $teamId    = (int) $row['teamId'];
                $fileName  = isset($row['fileName']) ? $pdo->quote($row['fileName']) : 'NULL';
                $date      = $pdo->quote($row['date']);
                $hour      = (int) $row['hour'];
                $energy    = isset($row['energy']) && $row['energy'] !== '' ? (float) $row['energy'] : 'NULL';
                $kvarh     = isset($row['kvarh']) && $row['kvarh'] !== '' ? $pdo->quote($row['kvarh']) : 'NULL';
                $block     = isset($row['block']) && $row['block'] !== '' ? $pdo->quote($row['block']) : 'NULL';
                $rolled    = isset($row['rolledDemand']) && $row['rolledDemand'] !== '' ? (float) $row['rolledDemand'] : 'NULL';
                $missing   = isset($row['missingRecordAdded']) ? $pdo->quote($row['missingRecordAdded']) : 'NULL';
                $tipo      = isset($row['tipo']) ? $pdo->quote($row['tipo']) : 'NULL';
                $createdAt = $pdo->quote($row['createdAt']);
                
                // Expresión SQL cruda
                $claveNodo = $row['claveNodo'];

                $values[] = "($rpu, $rmu, $userId, $teamId, $fileName, $claveNodo, $date, $hour, $energy, $kvarh, $block, $rolled, $missing, $tipo, $createdAt)";
            }

            $valuesString = implode(', ', $values);

            $sql = "INSERT INTO `measurements` 
                    (`rpu`, `rmu`, `userId`, `teamId`, `fileName`, `claveNodo`, `date`, `hour`, `energy`, `KVARh`, `block`, `rolledDemand`, `missingRecordAdded`, `tipo`, `createdAt`) 
                    VALUES {$valuesString}
                    ON DUPLICATE KEY UPDATE 
                        `rmu` = VALUES(`rmu`),
                        `userId` = VALUES(`userId`),
                        `fileName` = VALUES(`fileName`),
                        `claveNodo` = VALUES(`claveNodo`),
                        `energy` = VALUES(`energy`),
                        `KVARh` = VALUES(`KVARh`),
                        `block` = VALUES(`block`),
                        `rolledDemand` = VALUES(`rolledDemand`),
                        `missingRecordAdded` = VALUES(`missingRecordAdded`),
                        `tipo` = VALUES(`tipo`);";

            $retries = 0;
            while ($retries < $maxRetries) {
                try {
                    $conn->statement($sql);
                    $totalProcessed += count($chunk);
                    break; // Éxito, salir del loop de reintentos
                } catch (QueryException $e) {
                    if ($e->getCode() == '40001') {
                        $retries++;
                        if ($retries >= $maxRetries) {
                            Log::channel('task_errors')->error("Bulk Upsert Deadlock on CC Measurements after max retries", ['error' => $e]);
                            throw $e;
                        }
                        usleep(500000);
                        Log::channel('task_errors')->error("40001 Deadlock on mysql_dev_2 measurements connection during bulk CC upsert, retrying...");
                    } else {
                        Log::channel('task_errors')->error("Bulk Upsert Error on CC Measurements", ['error' => $e]);
                        throw $e;
                    }
                }
            }
        }

        return $totalProcessed;
    }

    /**
     * Realiza un Upsert masivo idempotente compatible con Laravel 6 para Centrales Eléctricas.
     * Tolerante a Deadlocks (código 40001).
     *
     * @param array $rows Array de datos a insertar.
     * @param int $maxRetries Cantidad de reintentos por deadlock.
     * @return int Cantidad total de registros procesados.
     */
    public function bulkUpsertCEMeasurements(array $rows, $maxRetries = 3)
    {
        if (empty($rows)) {
            return 0;
        }

        $chunks = array_chunk($rows, 500);
        $totalProcessed = 0;
        $conn = DB::connection('mysql_dev_2');
        $pdo = $conn->getPdo();

        foreach ($chunks as $chunk) {
            $values = [];

            foreach ($chunk as $row) {
                $nombre     = $pdo->quote($row['nombre']);
                $rmu        = isset($row['rmu']) ? $pdo->quote($row['rmu']) : 'NULL';
                $unidad     = isset($row['unidad']) ? $pdo->quote($row['unidad']) : 'NULL';
                $userId     = (int) $row['userId'];
                $teamId     = (int) $row['teamId'];
                $fileName   = isset($row['fileName']) ? $pdo->quote($row['fileName']) : 'NULL';
                $fecha      = $pdo->quote($row['fecha']);
                $hora       = (int) $row['hora'];
                $energiakWh = isset($row['energiakWh']) && $row['energiakWh'] !== '' ? (float) $row['energiakWh'] : 'NULL';
                $kvarh      = isset($row['KVArh']) && $row['KVArh'] !== '' ? $pdo->quote($row['KVArh']) : 'NULL';
                $blockCE    = isset($row['blockCE']) && $row['blockCE'] !== '' ? $pdo->quote($row['blockCE']) : 'NULL';
                $tipo       = isset($row['tipo']) && $row['tipo'] !== '' ? $pdo->quote($row['tipo']) : 'NULL';
                $createdAt  = $pdo->quote($row['createdAt']);
                
                // Expresión SQL cruda
                $claveNodo  = $row['claveNodo'];

                $values[] = "($nombre, $rmu, $unidad, $userId, $teamId, $fileName, $fecha, $hora, $energiakWh, $kvarh, $blockCE, $tipo, $claveNodo, $createdAt)";
            }

            $valuesString = implode(', ', $values);

            $sql = "INSERT INTO `medicionesCentralElectrica` 
                    (`nombre`, `rmu`, `unidad`, `userId`, `teamId`, `fileName`, `fecha`, `hora`, `energiakWh`, `KVArh`, `blockCE`, `tipo`, `claveNodo`, `createdAt`) 
                    VALUES {$valuesString}
                    ON DUPLICATE KEY UPDATE 
                        `rmu` = VALUES(`rmu`),
                        `unidad` = VALUES(`unidad`),
                        `userId` = VALUES(`userId`),
                        `fileName` = VALUES(`fileName`),
                        `energiakWh` = VALUES(`energiakWh`),
                        `KVArh` = VALUES(`KVArh`),
                        `blockCE` = VALUES(`blockCE`),
                        `tipo` = VALUES(`tipo`),
                        `claveNodo` = VALUES(`claveNodo`);";

            $retries = 0;
            while ($retries < $maxRetries) {
                try {
                    $conn->statement($sql);
                    $totalProcessed += count($chunk);
                    break;
                } catch (QueryException $e) {
                    // Skip SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry
                    if ($e->getCode() == '23000') {
                        $totalProcessed += count($chunk); // We assume it succeeded or is acceptable based on old logic
                        break;
                    }
                    if ($e->getCode() == '40001') {
                        $retries++;
                        if ($retries >= $maxRetries) {
                            Log::channel('task_errors')->error("Bulk Upsert Deadlock on CE Measurements after max retries", ['error' => $e]);
                            throw $e;
                        }
                        usleep(500000);
                        Log::channel('task_errors')->error("40001 Deadlock on mysql_dev_2 measurements connection during bulk CE upsert, retrying...");
                    } else {
                        Log::channel('task_errors')->error("Bulk Upsert Error on CE Measurements", ['error' => $e]);
                        throw $e;
                    }
                }
            }
        }

        return $totalProcessed;
    }
}
