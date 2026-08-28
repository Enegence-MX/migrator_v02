<?php
namespace App\Http\Helpers;

use DateTime;
use DateInterval;
use DatePeriod;

class DaylightSavingHelper
{
    /**
     * Generate daylight saving transition dates for a date range
     *
     * @param string $startDate
     * @param string $endDate
     * @param string $timezone
     * @return array
     */
    public static function generateDaylightSavingDates(
        $startDate = '2016-01-01',
        $endDate = '2028-12-31',
        $timezone = 'America/Mexico_City'
    ) {
        $begin = new DateTime($startDate, new \DateTimeZone($timezone));
        $end = new DateTime($endDate, new \DateTimeZone($timezone));
        
        $interval = new DateInterval('P1D');
        $daterange = new DatePeriod($begin, $interval, $end);
        
        $toggleDaylightSavings = false;
        $daylightSavingsDates = [];
        
        foreach ($daterange as $date) {
            $isDST = (bool) $date->format('I');
            
            if ($isDST && !$toggleDaylightSavings) {
                // Entering DST (spring forward)
                $dayBefore = clone $date;
                $dayBefore->modify('-1 day');
                $daylightSavingsDates[] = $dayBefore->format('Y-m-d');
                $toggleDaylightSavings = true;
            } elseif (!$isDST && $toggleDaylightSavings) {
                // Exiting DST (fall back)
                $dayBefore = clone $date;
                $dayBefore->modify('-1 day');
                $daylightSavingsDates[] = $dayBefore->format('Y-m-d');
                $toggleDaylightSavings = false;
            }
        }
        
        return $daylightSavingsDates;
    }
    
    /**
     * Cache daylight saving dates with a specific cache key
     *
     * @param string $sistema
     * @return array
     */
    public static function getCachedDaylightSavingDates($sistema)
    {
        static $runtimeCache = [];

        if (isset($runtimeCache[$sistema])) {
            return $runtimeCache[$sistema];
        }

        $cacheKey = "daylight_saving_dates_{$sistema}";
        $cacheTime = 86400 * 30; // Cache for 30 days

        $dates = cache()->remember($cacheKey, $cacheTime, function () use ($sistema) {
            $timezone = ($sistema == 'BCA') ? 'America/Los_Angeles' : 'America/Mexico_City';
            return self::generateDaylightSavingDates('2016-01-01', '2028-12-31', $timezone);
        });

        $runtimeCache[$sistema] = $dates;

        return $dates;
    }
    
    /**
     * Check if a date is a daylight saving transition date
     *
     * @param string $date Date in Y-m-d format
     * @param string $sistema RPU sistem SIM, BCN, BCS, BCA
     * @return string|false Returns 'summer', 'winter', 'summerOneDayAfter',
     *                              'winterOneDayAfter', or false
     */
    public static function checkDaylightSavingsDay($date, $sistema)
    {
        $daylightSavingDates = self::getCachedDaylightSavingDates($sistema);
        
        if ($sistema == 'BCA') {
            $summerMonth = '03';
            $winterMonth = '11';
        } else {
            $summerMonth = '04';
            $winterMonth = '10';
        }
        
        $summerDaylightSavingDates = [];
        $winterDaylightSavingDates = [];
        $summerDaylightDatesOneDayAfter = [];
        $winterDaylightDatesOneDayAfter = [];
        
        foreach ($daylightSavingDates as $item) {
            $monthPart = date('m', strtotime($item));
            
            if ($monthPart == $summerMonth) {
                $summerDaylightSavingDates[] = $item;
                $summerDaylightDatesOneDayAfter[] = date('Y-m-d', strtotime($item . ' +1 day'));
            }
            
            if ($monthPart == $winterMonth) {
                $winterDaylightSavingDates[] = $item;
                $winterDaylightDatesOneDayAfter[] = date('Y-m-d', strtotime($item . ' +1 day'));
            }
        }
        
        if (in_array($date, $summerDaylightSavingDates)) {
            return 'summer';
        } elseif (in_array($date, $winterDaylightSavingDates)) {
            return 'winter';
        }
        
        if (in_array($date, $summerDaylightDatesOneDayAfter)) {
            return 'summerOneDayAfter';
        } elseif (in_array($date, $winterDaylightDatesOneDayAfter)) {
            return 'winterOneDayAfter';
        }
        
        return false;
    }
}
