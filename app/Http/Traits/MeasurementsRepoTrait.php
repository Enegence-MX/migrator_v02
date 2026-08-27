<?php

namespace App\Http\Traits;

use DateTime;
use DateInterval;
use App\Http\Helpers\DaylightSavingHelper;

trait MeasurementsRepoTrait
{

    /**
     * Function to merge kwhe,kwhr, kvar and rolled demand into single array.
     * This function assums that all arrays have save rpus,
     * dates and hours, and matches with this structure:
     * [
     *   "RPU or RMU" => [
     *      'date' => [
     *          'hour 1' => float,
     *          ...,
     *          'hour 24' => float
     *      ],
     *      ...
     *      'date n' => [
     *          'hour 1' => float,
     *          ...,
     *          'hour 24' => float
     *      ],
     *   ],
     *   ...
     * ]
     *
     * @param $demandaRoladaHourlyMax array of rolled demand retieved from
     *          measurementsRepo->setHourlyDemandaRolada.
     * @param $measurementskwheHourlySums array of kwhe values from
     *          measurementsRepo->setHourlySumVariables.
     * @param $measurementKwhrHourlySums array of kwhr values from
     *          measurementsRepo->setHourlySumVariables.
     * @param $measurementKvarHourlySums array of kvar values from
     *          measurementsRepo->setHourlySumVariables.
     *
     * @return array
     */
    public function mergeDataArrays(
        $demandaRoladaHourlyMax,
        $measurementskwheHourlySums,
        $measurementKwhrHourlySums,
        $measurementKvarHourlySums,
        $measurementsTypeAndMissingDatesHourly
    ) {
        $outputArray = [];
        foreach ($demandaRoladaHourlyMax as $rpuOrRMU => $rpuOrRMUDates) {
            foreach ($rpuOrRMUDates as $date => $dateHours) {
                foreach ($dateHours as $hour => $value) {
                    $rolledDemand = $value;
                    $kwhe = $measurementskwheHourlySums[$rpuOrRMU][$date][$hour];
                    $kwhr = $measurementKwhrHourlySums[$rpuOrRMU][$date][$hour];
                    $kvar = $measurementKvarHourlySums[$rpuOrRMU][$date][$hour];
                    $missingRecordAdded = $measurementsTypeAndMissingDatesHourly[$rpuOrRMU][$date][$hour]['missingRecords'];// phpcs:ignore
                    $tipo = $measurementsTypeAndMissingDatesHourly[$rpuOrRMU][$date][$hour]['type'];
                    $outputArray[$rpuOrRMU][$date][$hour] = array(
                        'rolledDemand' => $rolledDemand,
                        'kwhe' => $kwhe,
                        'kwhr' => $kwhr,
                        'kvar' => $kvar,
                        'tipo' => $tipo,
                        'missingRecordAdded' => $missingRecordAdded,
                    );
                }
            }
        }
        return $outputArray;
    }

    /**
     * Function to format from Json Object List to format for 5 minutal measurements file,
     * in order to reuse measurementsRepo parseMesurementsData function.
     *
     * @param $json5MinDataList Json list of 5 minutal measurements.
     *
     * @return string
     */
    protected function parseJsonToCsvFile($json5MinDataList)
    {
        $output = 'No.,Fecha,kWh E,kWh R,kVARh,Tipo' . PHP_EOL;
        foreach ($json5MinDataList as $json5MinDataIndex => $json5MinData) {
            $output .= sprintf(
                "%s,%s %s,%s,%s,%s,%s". PHP_EOL,
                $json5MinDataIndex,
                $json5MinData->fecha,
                $json5MinData->hora,
                $json5MinData->kwhe ?? null,
                $json5MinData->kwhr ?? null,
                $json5MinData->kvarh ?? null,
                $json5MinData->tipo,
            );
        }
        return $output;
    }

    /**
     * This function  fills up missing measurements in 5 minutes json array data.
     * taking dates from $datesRange
     *
     * @param array $datesRange An array containing the date range for which hourly
     *              data is expected.
     * @param array $json5MinuteDataList An associative array containing 5 minute
     *              data retrieved from API.
     *
     * @return array The modified 5 minutes data list with missing dates and minutes filled.
     */
    public function fillUpGapMeasurements($datesRange, $json5MinuteDataList)
    {
        $filledOutputArray = [];
        $interval    = new DateInterval('PT5M'); // 5-minute interval
        $dateFormat  = 'H:i:s';

        // Group data by date.
        $groupedByDate = [];
        foreach ($json5MinuteDataList as $entry) {
            $groupedByDate[$entry->fecha][] = $entry;
        }

        // Iterates each required date.
        foreach ($datesRange as $date) {
            $entries = isset($groupedByDate[$date]) ? $groupedByDate[$date] : [];

            // Create a DateTime object at the start of the day
            $currentTime = new DateTime("$date 00:00:00");
                
            // Add hour index to validate sub date array
            $timeIndexedEntries = [];
            foreach ($entries as $entry) {
                $timeIndexedEntries[$entry->hora] = $entry;
            }

            // Generate all 5-minute intervals for the day
            // For 24 hours * 12 intervals per hour = 288 intervals
            for ($i = 0; $i < 288; $i++) {
                $timeStr = $currentTime->format($dateFormat);
                if (isset($timeIndexedEntries[$timeStr])) {
                    $filledOutputArray[] = $timeIndexedEntries[$timeStr];
                } else {
                    $firstItem = (null !== array_key_first($json5MinuteDataList))
                        ? $json5MinuteDataList[array_key_first($json5MinuteDataList)]
                        : (object) array();
                    $missingItem = clone $firstItem;
                    $missingItem->fecha = $date;
                    $missingItem->hora = $timeStr;
                    $missingItem->tipo = 'Estimada';
                    $missingItem->kvarh = null;
                    $missingItem->kwhe = null;
                    $missingItem->kwhr = null;
                    $missingItem->rolledDemand = null;
                    $filledOutputArray[] = $missingItem;
                }
                $currentTime->add($interval);
            }
        }

        // Remove first itme because always data retrieved starts in 00:05:00 and
        // this fucntions fill upd from 00:00:00.
        array_shift($filledOutputArray);

        $lastFilledOutputArray = end($filledOutputArray);
        $lastJson5MinuteDataList = end($json5MinuteDataList);

        // Add last measure out from dates range because it belongs to last 5 mintutes of hour 24.
        if ($lastJson5MinuteDataList) {
            if ($lastFilledOutputArray->fecha !== $lastJson5MinuteDataList->fecha) {
                array_push($filledOutputArray, $lastJson5MinuteDataList);
            }
        }

        return $filledOutputArray;
    }

    /**
     * Parses measurement data from the provided content and processes
     * it according to daylight savings rules.
     * NOTE: this is a copy of function MeaurementsRepo->parseMesurementsData()
     *
     * @param string $content The content containing measurement data in CSV format.
     * @param string $rpu The RPU (Remote Processing Unit) identifier.
     * @param string $sistema The system identifier used to check daylight savings.
     *
     * @return array An associative array of parsed measurements indexed by RPU, date, and hour.
     */
    public function parseMesurementsType($content, $rpu, $sistema)
    {
        $measurementIndex = 5;

        $rpuMesuarementsByDate = [];

        $lines     = explode(PHP_EOL, $content);
        $fieldsRow = 0;

        foreach ($lines as $key => $line) {
            if (($line != '') && ($key > $fieldsRow)) {
                $row               = str_getcsv($line);
                $dateTime          = $row[1];
                list($date, $time) = explode(' ', $dateTime);
                $dayligthSavings   = DaylightSavingHelper::checkDaylightSavingsDay(
                    $date,
                    $sistema
                );
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
                                        [$intHourRepetead][$dateTime] = $row[$measurementIndex] ?? '';//phpcs:ignore
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
    
                $rpuMesuarementsByDate[$rpu][$date][$intHour][$dateTime] = $row[$measurementIndex] ?? '';//phpcs:ignore
            }
        }

        $this->measurementsRepo->orderMesurementsKeys($rpuMesuarementsByDate);

        return $rpuMesuarementsByDate;
    }
}
