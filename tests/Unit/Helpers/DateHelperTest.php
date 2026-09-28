<?php

namespace Tests\Unit\Helpers;

use App\Http\Helpers\DateHelper;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class DateHelperTest extends TestCase
{
    /**
     * Test getDatesRange with normal date range.
     */
    public function test_get_dates_range_normal()
    {
        $result = DateHelper::getDatesRange('2025-01-01', '2025-01-03');
        $expected = ['2025-01-01', '2025-01-02', '2025-01-03'];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test getDatesRange with reversed dates.
     */
    public function test_get_dates_range_reversed()
    {
        $result = DateHelper::getDatesRange('2025-01-03', '2025-01-01');
        $expected = ['2025-01-01', '2025-01-02', '2025-01-03'];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test getDatesRange with same date.
     */
    public function test_get_dates_range_same_date()
    {
        $result = DateHelper::getDatesRange('2025-01-01', '2025-01-01');
        $expected = ['2025-01-01'];
        $this->assertEquals($expected, $result);
    }

    /**
     * Test parseDateFormat with DD/MM/YYYY input format.
     */
    public function test_parse_date_format_dd_mm_yyyy_input()
    {
        $result = DateHelper::parseDateFormat('25/12/2025', 'YYYY-MM-DD', 'DD/MM/YYYY');
        $this->assertEquals('2025-12-25', $result);
    }

    /**
     * Test parseDateFormat with YYYY-MM-DD output format.
     */
    public function test_parse_date_format_default_output()
    {
        $result = DateHelper::parseDateFormat('2025-12-25', 'YYYY-MM-DD');
        $this->assertEquals('2025-12-25', $result);
    }

    /**
     * Test parseDateFormat with DD/MM/YYYY output format.
     */
    public function test_parse_date_format_dd_mm_yyyy_output()
    {
        $result = DateHelper::parseDateFormat('2025-12-25', 'DD/MM/YYYY');
        $this->assertEquals('25/12/2025', $result);
    }

    /**
     * Test parseDateFormat with DD-MM-YYYY output format.
     */
    public function test_parse_date_format_dd_dash_mm_yyyy_output()
    {
        $result = DateHelper::parseDateFormat('2025-12-25', 'DD-MM-YYYY');
        $this->assertEquals('25-12-2025', $result);
    }

    /**
     * Test parseDateFormat with LLL YYYY output format.
     */
    public function test_parse_date_format_month_year_output()
    {
        $result = DateHelper::parseDateFormat('2025-01-15', 'LLL YYYY');
        $this->assertEquals('ene 2025', $result);
    }

    /**
     * Test parseDateFormat with YYYY output format.
     */
    public function test_parse_date_format_year_only_output()
    {
        $result = DateHelper::parseDateFormat('2025-12-25', 'YYYY');
        $this->assertEquals('2025', $result);
    }

    /**
     * Test calculateFufOperationDate with type 0.
     */
    public function test_calculate_fuf_operation_date_type_0()
    {
        $fuf = '202501150'; // 2025-01-15, type 0 (7 days)
        $result = DateHelper::calculateFufOperationDate($fuf);
        $this->assertEquals('2025-01-08', $result);
    }

    /**
     * Test calculateFufOperationDate with type 1.
     */
    public function test_calculate_fuf_operation_date_type_1()
    {
        $fuf = '202503011'; // 2025-03-01, type 1 (49 days)
        $result = DateHelper::calculateFufOperationDate($fuf);
        $this->assertEquals('2025-01-11', $result);
    }

    /**
     * Test calculateFufOperationDate with type 2.
     */
    public function test_calculate_fuf_operation_date_type_2()
    {
        $fuf = '202506012'; // 2025-06-01, type 2 (105 days)
        $result = DateHelper::calculateFufOperationDate($fuf);
        $this->assertEquals('2025-02-16', $result);
    }

    /**
     * Test calculateFufOperationDate with type 3.
     */
    public function test_calculate_fuf_operation_date_type_3()
    {
        $fuf = '202512013'; // 2025-12-01, type 3 (210 days)
        $result = DateHelper::calculateFufOperationDate($fuf);
        $this->assertEquals('2025-05-05', $result);
    }

    /**
     * Test modifyDateByOffsetMonths adding months.
     */
    public function test_modify_date_by_offset_months_add()
    {
        $result = DateHelper::modifyDateByOffsetMonths('2025-01-15', 2);
        $this->assertEquals('2025-03-15', $result);
    }

    /**
     * Test modifyDateByOffsetMonths subtracting months.
     */
    public function test_modify_date_by_offset_months_subtract()
    {
        $result = DateHelper::modifyDateByOffsetMonths('2025-03-15', -2);
        $this->assertEquals('2025-01-15', $result);
    }

    /**
     * Test modifyDateByOffsetMonths with empty date.
     */
    public function test_modify_date_by_offset_months_empty_date()
    {
        $result = DateHelper::modifyDateByOffsetMonths('', 2);
        $this->assertNull($result);
    }

    /**
     * Test modifyDateByOffsetMonths with invalid date format.
     */
    public function test_modify_date_by_offset_months_invalid_format()
    {
        $result = DateHelper::modifyDateByOffsetMonths('2025/01/15', 2);
        $this->assertNull($result);
    }

    /**
     * Test modifyDateByOffsetMonths with non-numeric offset.
     */
    public function test_modify_date_by_offset_months_non_numeric_offset()
    {
        $result = DateHelper::modifyDateByOffsetMonths('2025-01-15', 'abc');
        $this->assertNull($result);
    }

    /**
     * Test getTotalMonthHours for January.
     */
    public function test_get_total_month_hours_january()
    {
        $result = DateHelper::getTotalMonthHours('15-01-2025');
        $this->assertEquals(744, $result); // 31 days * 24 hours
    }

    /**
     * Test getTotalMonthHours for February non-leap year.
     */
    public function test_get_total_month_hours_february_non_leap()
    {
        $result = DateHelper::getTotalMonthHours('15-02-2025');
        $this->assertEquals(672, $result); // 28 days * 24 hours
    }

    /**
     * Test getTotalMonthHours for February leap year.
     */
    public function test_get_total_month_hours_february_leap()
    {
        $result = DateHelper::getTotalMonthHours('15-02-2024');
        $this->assertEquals(696, $result); // 29 days * 24 hours
    }

    /**
     * Test addDates adding days.
     */
    public function test_add_dates()
    {
        $result = DateHelper::addDates('2025-01-15', 10);
        $this->assertEquals('2025-01-25', $result);
    }

    /**
     * Test addDates crossing month boundary.
     */
    public function test_add_dates_cross_month()
    {
        $result = DateHelper::addDates('2025-01-25', 10);
        $this->assertEquals('2025-02-04', $result);
    }

    /**
     * Test addDates with invalid format throws exception.
     */
    public function test_add_dates_invalid_format_throws_exception()
    {
        $this->expectException(InvalidArgumentException::class);
        DateHelper::addDates('25/01/2025', 10);
    }

    /**
     * Test getMonthNumberFromDate.
     */
    public function test_get_month_number_from_date()
    {
        $result = DateHelper::getMonthNumberFromDate('2025-03-15');
        $this->assertEquals(3, $result);
    }

    /**
     * Test getMonthNumberFromDate with invalid format throws exception.
     */
    public function test_get_month_number_from_date_invalid_format()
    {
        $this->expectException(InvalidArgumentException::class);
        DateHelper::getMonthNumberFromDate('25/03/2025');
    }

    /**
     * Test getMonthNumberFromDate with invalid month throws exception.
     */
    public function test_get_month_number_from_date_invalid_month()
    {
        $this->expectException(InvalidArgumentException::class);
        DateHelper::getMonthNumberFromDate('2025-13-15');
    }

    /**
     * Test getLastMonthDate.
     */
    public function test_get_last_month_date()
    {
        $result = DateHelper::getLastMonthDate('2025-01-15');
        $this->assertEquals('2025-01-31', $result);
    }

    /**
     * Test getLastMonthDate for February.
     */
    public function test_get_last_month_date_february()
    {
        $result = DateHelper::getLastMonthDate('2025-02-10');
        $this->assertEquals('2025-02-28', $result);
    }

    /**
     * Test getLastMonthDate with invalid format throws exception.
     */
    public function test_get_last_month_date_invalid_format()
    {
        $this->expectException(InvalidArgumentException::class);
        DateHelper::getLastMonthDate('15/01/2025');
    }

    /**
     * Test splitDateRangeInto30DayChunks.
     */
    public function test_split_date_range_into_30_day_chunks()
    {
        $result = DateHelper::splitDateRangeInto30DayChunks('2025-01-01', '2025-02-15');

        $this->assertCount(2, $result);
        $this->assertEquals('2025-01-01', $result[0]['start']);
        $this->assertEquals('2025-01-31', $result[0]['end']);
        $this->assertEquals('2025-02-01', $result[1]['start']);
        $this->assertEquals('2025-02-15', $result[1]['end']);
    }

    /**
     * Test formatSpanishDateFormatToMysqlFormat with slash separator.
     */
    public function test_format_spanish_date_format_to_mysql_slash()
    {
        $result = DateHelper::formatSpanishDateFormatToMysqlFormat('25/12/2025');
        $this->assertEquals('2025-12-25', $result);
    }

    /**
     * Test formatSpanishDateFormatToMysqlFormat with dash separator.
     */
    public function test_format_spanish_date_format_to_mysql_dash()
    {
        $result = DateHelper::formatSpanishDateFormatToMysqlFormat('25-12-2025');
        $this->assertEquals('2025-12-25', $result);
    }

    /**
     * Test formatSpanishDateFormatToMysqlFormat with spaces.
     */
    public function test_format_spanish_date_format_to_mysql_with_spaces()
    {
        $result = DateHelper::formatSpanishDateFormatToMysqlFormat(' 25/12/2025 ');
        $this->assertEquals('2025-12-25', $result);
    }
}
