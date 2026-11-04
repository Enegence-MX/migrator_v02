<?php

namespace Tests\Unit\Helpers;

use App\Http\Helpers\DaylightSavingHelper;
use Tests\TestCase;

class DaylightSavingHelperTest extends TestCase
{
    /**
     * Test generateDaylightSavingDates returns array.
     */
    public function test_generate_daylight_saving_dates_returns_array()
    {
        $result = DaylightSavingHelper::generateDaylightSavingDates(
            '2024-01-01',
            '2024-12-31',
            'America/Mexico_City'
        );

        $this->assertIsArray($result);
    }

    /**
     * Test generateDaylightSavingDates with small range.
     */
    public function test_generate_daylight_saving_dates_small_range()
    {
        $result = DaylightSavingHelper::generateDaylightSavingDates(
            '2024-04-01',
            '2024-04-10',
            'America/Mexico_City'
        );

        $this->assertIsArray($result);
    }

    /**
     * Test generateDaylightSavingDates with different timezone.
     */
    public function test_generate_daylight_saving_dates_different_timezone()
    {
        $result = DaylightSavingHelper::generateDaylightSavingDates(
            '2024-01-01',
            '2024-12-31',
            'America/Los_Angeles'
        );

        $this->assertIsArray($result);
    }

    /**
     * Test generateDaylightSavingDates default parameters.
     */
    public function test_generate_daylight_saving_dates_defaults()
    {
        $result = DaylightSavingHelper::generateDaylightSavingDates();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test checkDaylightSavingsDay with non-DST date returns false.
     */
    public function test_check_daylight_savings_day_regular_date()
    {
        $result = DaylightSavingHelper::checkDaylightSavingsDay('2024-01-15', 'SIM');

        $this->assertFalse($result);
    }

    /**
     * Test checkDaylightSavingsDay with BCA system.
     */
    public function test_check_daylight_savings_day_bca_system()
    {
        $result = DaylightSavingHelper::checkDaylightSavingsDay('2024-01-15', 'BCA');

        // Can be false or a string depending on if it's a DST date
        $this->assertTrue(
            $result === false ||
            in_array($result, ['summer', 'winter', 'summerOneDayAfter', 'winterOneDayAfter'])
        );
    }

    /**
     * Test getCachedDaylightSavingDates returns array.
     */
    public function test_get_cached_daylight_saving_dates_returns_array()
    {
        $result = DaylightSavingHelper::getCachedDaylightSavingDates('SIM');

        $this->assertIsArray($result);
    }

    /**
     * Test getCachedDaylightSavingDates with BCA system.
     */
    public function test_get_cached_daylight_saving_dates_bca()
    {
        $result = DaylightSavingHelper::getCachedDaylightSavingDates('BCA');

        $this->assertIsArray($result);
    }

    /**
     * Test getCachedDaylightSavingDates caches results.
     */
    public function test_get_cached_daylight_saving_dates_caching()
    {
        $result1 = DaylightSavingHelper::getCachedDaylightSavingDates('BCN');
        $result2 = DaylightSavingHelper::getCachedDaylightSavingDates('BCN');

        $this->assertEquals($result1, $result2);
    }
}
