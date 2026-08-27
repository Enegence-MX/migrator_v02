<?php namespace App\Http\Helpers;

use DateInterval;
use DateTime;
use Exception;
use InvalidArgumentException;

/**
 * Class helper to handle with formating or ranges.
 */
class DateHelper
{

    /**
     * Function to get dates range in string list.
     *
     * @param $start String String initial date.
     * @param $end   String String final date.
     *
     * @return Array
     */
    public static function getDatesRange($start, $end)
    {
        $datesRange = [];
        $initial    = new DateTime($start);
        $final      = new DateTime($end);

        if ($final < $initial) {
            $initial = new DateTime($end);
            $final   = new DateTime($start);
        }

        while ($initial <= $final) {
            $datesRange[] = $initial->format('Y-m-d');
            $initial->modify('+1 day');
        }
    
        return $datesRange;
    }

    /**
     * Function to output a date in a gived format.
     *
     * @param $dateString   String Input date.
     * @param $outputFormat String Desired output format.
     * @param $inputFormat String Date string input format from being parsed.
     *
     * @return string
     */
    public static function parseDateFormat(
        $dateString,
        $outputFormat = 'YYYY-MM-DD',
        $inputFormat = null
    ) {
        $monthsNumber = [
            'ene' => '01',
            'feb' => '02',
            'mar' => '03',
            'abr' => '04',
            'may' => '05',
            'jun' => '06',
            'jul' => '07',
            'ago' => '08',
            'sep' => '09',
            'oct' => '10',
            'nov' => '11',
            'dic' => '12',
            'jan' => '01',
            'feb' => '02',
            'mar' => '03',
            'apr' => '04',
            'may' => '05',
            'jun' => '06',
            'jul' => '07',
            'aug' => '08',
            'sept'=> '09',
            'oct' => '10',
            'nov' => '11',
            'dec' => '12'
        ];

        switch (strtoupper((string)$inputFormat)) {
            case 'DD/MM/YYYY':
                $date = DateTime::createFromFormat('d/m/Y', $dateString);
                break;
            case 'D/M./Y':
                list($day, $monthAbbr, $year) = explode('/', $dateString);
                $monthAbbr = str_replace('.', '', strtolower($monthAbbr));
                $month = isset($monthsNumber[$monthAbbr]) ? $monthsNumber[$monthAbbr] : null;

                if (null == $month) {
                    throw new Exception(
                        "Date input month $monthAbbr not found.",
                        1
                    );
                }

                $date = new DateTime(
                    sprintf(
                        "%s/%s/%s",
                        $year,
                        $month,
                        $day
                    )
                );
                break;
            default:
                $date = new DateTime($dateString);
        }

        $monthsName = [
            1 => 'ene',
            2 => 'feb',
            3 => 'mar',
            4 => 'abr',
            5 => 'may',
            6 => 'jun',
            7 => 'jul',
            8 => 'ago',
            9 => 'sep',
            10 => 'oct',
            11 => 'nov',
            12 => 'dic'
        ];
        switch (strtoupper((string)$outputFormat)) {
            case 'DD/MM/YYYY':
                return $date->format('d/m/Y');
            case 'YYYY-MM-DD':
                return $date->format('Y-m-d');
            case 'DD-MM-YYYY':
                return $date->format('d-m-Y');
            case 'LLL YYYY':
                $month = (int)$date->format('n');
                $year = $date->format('Y');
                return $monthsName[$month] . ' ' . $year;
            case 'YYYY':
                return $date->format('Y');
            default:
                throw new Exception("Date output format $outputFormat not implemented", 1);
        }
    }

    /**
     * Calculate the operation date based on the FUF (Folio Único de Factura).
     *
     * The operation date is derived from the FUF date by subtracting a specific number
     * of days based on the operation type character at the end of the FUF.
     *
     * @param string $fuf The FUF string from which to calculate the operation date.
     * @return string The calculated operation date in 'Y-m-d' format.
     */
    public static function calculateFufOperationDate($fuf)
    {
        // Extracting FUF date.
        $ecDate = sprintf(
            '%s-%s-%s',
            substr($fuf, 0, 4),
            substr($fuf, 4, 2),
            substr($fuf, 6, 2),
        );
        // Extracting operation type char.
        $operationType = substr($fuf, -1);
        $daysToSubtract = [
            '0' => 7,
            '1' => 49,
            '2' => 105,
            '3' => 210
        ];

        // Subtracting days based on operation type.
        $operationDate = date(
            'Y-m-d',
            strtotime($ecDate . ' -' . $daysToSubtract[$operationType] . ' days')
        );

        return $operationDate;
    }

    /**
     * Adds or subtracts a specified number of months to/from a date string.
     *
     * @param string|null $date         The input date in 'YYYY-MM-DD' format
     * @param int|string  $offsetMonths Number of months to add (positive) or subtract (negative)
     *
     * @return string|null Returns the resulting date in 'YYYY-MM-DD' format, or null if fails
     */
    public static function modifyDateByOffsetMonths($date, $offsetMonths)
    {

        if (empty($date)) {
            return null;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        try {
            // Parse the date components
            list($year, $month, $day) = explode('-', $date);
            
            // Create DateTime object from first day of the month
            $dateTime = DateTime::createFromFormat('Y-m-d', $date);
            
            // Validate if the date is valid
            if (!$dateTime || $dateTime->format('Y-m-d') !== $date) {
                return null;
            }

            if (!is_numeric($offsetMonths)) {
                return null;
            }

            // Get the last day of the current month
            $lastDayOfMonth = $day == $dateTime->format('t');
            
            // Add months
            $dateTime->modify('first day of this month');
            $dateTime->modify("{$offsetMonths} months");
            
            // If original date was last day of month, set to last day of new month
            if ($lastDayOfMonth) {
                $dateTime->modify('last day of this month');
            } else {
                // Try to set to the same day, but don't exceed month length
                $lastDayOfTargetMonth = $dateTime->format('t');
                $targetDay = min((int)$day, (int)$lastDayOfTargetMonth);
                $dateTime->setDate($dateTime->format('Y'), $dateTime->format('m'), $targetDay);
            }
            
            return $dateTime->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Calculates the total number of hours in a month based on the given date string.
     *
     * @param string $dateString A date string in the format 'day-month-year'.
     * @return int The total number of hours in the month.
     */
    public static function getTotalMonthHours($dateString)
    {
        $dateParts = explode('-', $dateString);
        $day = $dateParts[0];
        $month = $dateParts[1];
        $year = $dateParts[2];
        
        // Create a DateTime object for the first day of the given month
        $date = new DateTime("$year-$month-01");
        
        // Get the number of days in the month
        $daysInMonth = (int)$date->format('t');
        
        // Calculate total hours (days × 24)
        $totalHours = $daysInMonth * 24;
        
        return $totalHours;
    }

    /**
     * Adds a specified number of days to a given date string.
     *
     * @param string $dateString A date string in the format 'day-month-year'.
     * @param int $daysToAdd The total number of dates to add.
     * @return int The date string in the format YYYY-MM-DD after add dates.
     */
    public static function addDates($dateString, $daysToAdd)
    {
        // Create DateTime object from the given date string with expected format
        $date = DateTime::createFromFormat('Y-m-d', $dateString);
    
        // Check if date parsing succeeded
        if (!$date) {
            throw new InvalidArgumentException(
                'Invalid date format. Expected format: YYYY-MM-DD (e.g., 2025-12-31).'
            );
        }
    
        // Add the specified number of days
        $date->modify("{$daysToAdd} days");
    
        // Return the new date in Y-m-d format
        return $date->format('Y-m-d');
    }

    /**
     * Extracts the month number from a given date string.
     *
     * @param string $dateString A date string in the format 'YYYY-MM-DD'.
     *
     * @return int The month number (1-12).
     * @throws InvalidArgumentException If the date format is invalid or month is out of range.
     */
    public static function getMonthNumberFromDate($dateString)
    {
        $dateParts = explode('-', $dateString);
        if (count($dateParts) !== 3) {
            throw new InvalidArgumentException(
                'Invalid date format. Expected format: YYYY-MM-DD (e.g., 2025-12-31).'
            );
        }
        $month = (int)$dateParts[1];
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException('Month must be between 1 and 12.');
        }
        return $month;
    }
    /**
     * Get the last day of the monthd from the given date.
     *
     * @param string $dateString A date string in the format 'YYYY-MM-DD'.
     *
     * @return string The date string of the las month date.
     */
    public static function getLastMonthDate($dateString)
    {
        $dateTime = DateTime::createFromFormat('Y-m-d', $dateString);
        if (!$dateTime) {
            throw new InvalidArgumentException(
                'Invalid date format. Expected format: YYYY-MM-DD (e.g., 2025-12-31).'
            );
        }
        $lastDay = $dateTime->modify('last day of this month');

        return $lastDay->format('Y-m-d');
    }

    /**
     * Split a date range into 30-day chunks
     * @param string $startDate The start date for the range.
     * @param string $endDate The end date for the range.
     */
    public static function splitDateRangeInto30DayChunks($startDate, $endDate)
    {
        $ranges = [];
        $current = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        while ($current <= $end) {
            $rangeEnd = clone $current;
            $rangeEnd->add(new DateInterval('P30D')); // Add 30 days
            
            if ($rangeEnd > $end) {
                $rangeEnd = $end;
            }
            
            $ranges[] = [
                'start' => $current->format('Y-m-d'),
                'end' => $rangeEnd->format('Y-m-d')
            ];
            
            $current->add(new DateInterval('P31D')); // Move to next 31-day period
        }
        
        return $ranges;
    }

    /**
     * Converts a Spanish date format (DD/MM/YYYY or DD-MM-YYYY) to MySQL format (YYYY-MM-DD)
     *
     * Takes a date string in Spanish format where day comes first, followed by month and year,
     * and transforms it into the MySQL/ISO standard format where year comes first.
     *
     * @param string $date The date string in Spanish format (e.g., "25/12/2023" or "25-12-2023")
     *
     * @return string The date in MySQL format (e.g., "2023-12-25")
     *
     * @example
     * formatSpanishDateFormatToMysqlFormat("31/12/2023") // Returns "2023-12-31"
     * formatSpanishDateFormatToMysqlFormat("01-06-2023") // Returns "2023-06-01"
     */
    public static function formatSpanishDateFormatToMysqlFormat($date)
    {
        $date = trim($date);
        $date = str_replace('/', '-', $date);
        list($day, $month, $year) = explode('-', $date);

        return "$year-$month-$day";
    }
}
