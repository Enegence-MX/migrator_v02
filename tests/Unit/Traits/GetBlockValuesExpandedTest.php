<?php

namespace Tests\Unit\Traits;

use PHPUnit\Framework\TestCase;
use App\Http\Traits\GetBlockValues;

// Concrete class to test the trait
class GetBlockValuesConcrete2
{
    use GetBlockValues;
}

/**
 * Expanded tests for GetBlockValues trait
 * Goal: Achieve 90%+ code coverage
 */
class GetBlockValuesExpandedTest extends TestCase
{
    protected $concrete;

    protected function setUp(): void
    {
        parent::setUp();
        $this->concrete = new GetBlockValuesConcrete2();
    }

    // ==================== Tests for setDayToInt() ====================

    public function test_set_day_to_int_monday()
    {
        $this->assertEquals(1, $this->concrete->setDayToInt('Monday'));
    }

    public function test_set_day_to_int_tuesday()
    {
        $this->assertEquals(2, $this->concrete->setDayToInt('Tuesday'));
    }

    public function test_set_day_to_int_wednesday()
    {
        $this->assertEquals(3, $this->concrete->setDayToInt('Wednesday'));
    }

    public function test_set_day_to_int_thursday()
    {
        $this->assertEquals(4, $this->concrete->setDayToInt('Thursday'));
    }

    public function test_set_day_to_int_friday()
    {
        $this->assertEquals(5, $this->concrete->setDayToInt('Friday'));
    }

    public function test_set_day_to_int_saturday()
    {
        $this->assertEquals(6, $this->concrete->setDayToInt('Saturday'));
    }

    public function test_set_day_to_int_sunday()
    {
        $this->assertEquals(7, $this->concrete->setDayToInt('Sunday'));
    }

    // ==================== Tests for isDateBewteenRange() ====================

    public function test_is_date_between_range_within()
    {
        $result = $this->concrete->isDateBewteenRange('2025-06-15', '2025-06-01', '2025-06-30');
        $this->assertTrue($result);
    }

    public function test_is_date_between_range_before()
    {
        $result = $this->concrete->isDateBewteenRange('2025-05-31', '2025-06-01', '2025-06-30');
        $this->assertFalse($result);
    }

    public function test_is_date_between_range_after()
    {
        $result = $this->concrete->isDateBewteenRange('2025-07-01', '2025-06-01', '2025-06-30');
        $this->assertFalse($result);
    }

    public function test_is_date_between_range_start_boundary()
    {
        $result = $this->concrete->isDateBewteenRange('2025-06-01', '2025-06-01', '2025-06-30');
        $this->assertTrue($result);
    }

    public function test_is_date_between_range_end_boundary()
    {
        $result = $this->concrete->isDateBewteenRange('2025-06-30', '2025-06-01', '2025-06-30');
        $this->assertTrue($result);
    }

    // ==================== BCA GDMTH Tests ====================

    public function test_bca_gdmth_summer_weekday_hour_1()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 1, 'RPU', $data, []));
    }

    public function test_bca_gdmth_summer_weekday_hour_14()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 14, 'RPU', $data, []));
    }

    public function test_bca_gdmth_summer_weekday_hour_15()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-06-02', 15, 'RPU', $data, []));
    }

    public function test_bca_gdmth_summer_weekday_hour_18()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-06-02', 18, 'RPU', $data, []));
    }

    public function test_bca_gdmth_summer_weekday_hour_19()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 19, 'RPU', $data, []));
    }

    public function test_bca_gdmth_summer_weekday_hour_25()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 25, 'RPU', $data, []));
    }

    public function test_bca_gdmth_summer_saturday()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-07', 12, 'RPU', $data, []));
    }

    public function test_bca_gdmth_summer_sunday()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-08', 12, 'RPU', $data, []));
    }

    public function test_bca_gdmth_winter_holiday()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-03', 10, 'RPU', $data, ['2025-11-03']));
    }

    public function test_bca_gdmth_winter_weekday_hour_17()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-03', 17, 'RPU', $data, []));
    }

    public function test_bca_gdmth_winter_weekday_hour_18()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-03', 18, 'RPU', $data, []));
    }

    public function test_bca_gdmth_winter_weekday_hour_22()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-03', 22, 'RPU', $data, []));
    }

    public function test_bca_gdmth_winter_weekday_hour_23()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-03', 23, 'RPU', $data, []));
    }

    public function test_bca_gdmth_winter_saturday_hour_19()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-08', 19, 'RPU', $data, []));
    }

    public function test_bca_gdmth_winter_saturday_hour_21()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-08', 21, 'RPU', $data, []));
    }

    public function test_bca_gdmth_winter_saturday_hour_22()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-08', 22, 'RPU', $data, []));
    }

    public function test_bca_gdmth_winter_sunday()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-15', 12, 'RPU', $data, []));
    }

    // ==================== BCA DIST Tests ====================

    public function test_bca_dist_summer_holiday()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 10, 'RPU', $data, ['2025-06-02']));
    }

    public function test_bca_dist_summer_weekday_hour_12()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 12, 'RPU', $data, []));
    }

    public function test_bca_dist_summer_weekday_hour_14()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('S', $this->concrete->getBlock('2025-06-02', 14, 'RPU', $data, []));
    }

    public function test_bca_dist_summer_weekday_hour_17()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-06-02', 17, 'RPU', $data, []));
    }

    public function test_bca_dist_summer_weekday_hour_19()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('S', $this->concrete->getBlock('2025-06-02', 19, 'RPU', $data, []));
    }

    public function test_bca_dist_summer_weekday_hour_22()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('S', $this->concrete->getBlock('2025-06-02', 22, 'RPU', $data, []));
    }

    public function test_bca_dist_summer_weekday_hour_23()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 23, 'RPU', $data, []));
    }

    public function test_bca_dist_summer_saturday()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-07', 12, 'RPU', $data, []));
    }

    public function test_bca_dist_summer_sunday()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-08', 12, 'RPU', $data, []));
    }

    public function test_bca_dist_winter_holiday()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-03', 10, 'RPU', $data, ['2025-11-03']));
    }

    public function test_bca_dist_winter_weekday_hour_18()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-03', 18, 'RPU', $data, []));
    }

    public function test_bca_dist_winter_saturday_hour_20()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-08', 20, 'RPU', $data, []));
    }

    public function test_bca_dist_winter_sunday()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-15', 12, 'RPU', $data, []));
    }

    // ==================== BCA DIT Tests ====================

    public function test_bca_dit_summer_holiday()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 10, 'RPU', $data, ['2025-06-02']));
    }

    public function test_bca_dit_summer_weekday_hour_13()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 13, 'RPU', $data, []));
    }

    public function test_bca_dit_summer_weekday_hour_14()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-06-02', 14, 'RPU', $data, []));
    }

    public function test_bca_dit_summer_weekday_hour_18()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('S', $this->concrete->getBlock('2025-06-02', 18, 'RPU', $data, []));
    }

    public function test_bca_dit_summer_weekday_hour_24()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 24, 'RPU', $data, []));
    }

    public function test_bca_dit_summer_saturday()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-07', 12, 'RPU', $data, []));
    }

    public function test_bca_dit_winter_holiday()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-03', 10, 'RPU', $data, ['2025-11-03']));
    }

    public function test_bca_dit_winter_weekday_hour_19()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-03', 19, 'RPU', $data, []));
    }

    public function test_bca_dit_winter_saturday_hour_20()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-08', 20, 'RPU', $data, []));
    }

    public function test_bca_invalid_grupo_tarifario()
    {
        $data = ['sistema' => 'BCA', 'grupoTarifario' => 'INVALID'];
        $this->assertNull($this->concrete->getBlock('2025-06-02', 10, 'RPU', $data, []));
    }

    // ==================== BCS GDMTH Tests ====================

    public function test_bcs_gdmth_summer_holiday()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 10, 'RPU', $data, ['2025-06-02']));
    }

    public function test_bcs_gdmth_summer_weekday_hour_12()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 12, 'RPU', $data, []));
    }

    public function test_bcs_gdmth_summer_weekday_hour_13()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-06-02', 13, 'RPU', $data, []));
    }

    public function test_bcs_gdmth_summer_weekday_hour_22()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-06-02', 22, 'RPU', $data, []));
    }

    public function test_bcs_gdmth_summer_weekday_hour_23()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 23, 'RPU', $data, []));
    }

    public function test_bcs_gdmth_summer_saturday_hour_19()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-07', 19, 'RPU', $data, []));
    }

    public function test_bcs_gdmth_summer_saturday_hour_20()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-06-07', 20, 'RPU', $data, []));
    }

    public function test_bcs_gdmth_summer_sunday()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-08', 12, 'RPU', $data, []));
    }

    public function test_bcs_gdmth_winter_holiday_hour_19()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-03', 19, 'RPU', $data, ['2025-11-03']));
    }

    public function test_bcs_gdmth_winter_holiday_hour_20()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-03', 20, 'RPU', $data, ['2025-11-03']));
    }

    public function test_bcs_gdmth_winter_holiday_hour_22()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-03', 22, 'RPU', $data, ['2025-11-03']));
    }

    public function test_bcs_gdmth_winter_weekday_hour_18()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-03', 18, 'RPU', $data, []));
    }

    public function test_bcs_gdmth_winter_weekday_hour_19()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-03', 19, 'RPU', $data, []));
    }

    public function test_bcs_gdmth_winter_saturday_hour_20()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-08', 20, 'RPU', $data, []));
    }

    public function test_bcs_gdmth_winter_sunday_hour_19()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-15', 19, 'RPU', $data, []));
    }

    public function test_bcs_gdmth_winter_sunday_hour_21()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-15', 21, 'RPU', $data, []));
    }

    public function test_bcs_gdmth_winter_sunday_hour_25()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-15', 25, 'RPU', $data, []));
    }

    // ==================== BCS DIST Tests ====================

    public function test_bcs_dist_summer_holiday()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 10, 'RPU', $data, ['2025-06-02']));
    }

    public function test_bcs_dist_summer_weekday_hour_15()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-06-02', 15, 'RPU', $data, []));
    }

    public function test_bcs_dist_summer_saturday_hour_21()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-06-07', 21, 'RPU', $data, []));
    }

    public function test_bcs_dist_winter_holiday_hour_21()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-03', 21, 'RPU', $data, ['2025-11-03']));
    }

    public function test_bcs_dist_winter_weekday_hour_20()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-03', 20, 'RPU', $data, []));
    }

    public function test_bcs_dist_winter_sunday_hour_20()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-15', 20, 'RPU', $data, []));
    }

    // ==================== BCS DIT Tests ====================

    public function test_bcs_dit_summer_holiday()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 10, 'RPU', $data, ['2025-06-02']));
    }

    public function test_bcs_dit_summer_weekday_hour_13()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 13, 'RPU', $data, []));
    }

    public function test_bcs_dit_summer_weekday_hour_14()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-06-02', 14, 'RPU', $data, []));
    }

    public function test_bcs_dit_summer_weekday_hour_24()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 24, 'RPU', $data, []));
    }

    public function test_bcs_dit_summer_saturday_hour_20()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-07', 20, 'RPU', $data, []));
    }

    public function test_bcs_dit_summer_saturday_hour_22()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-06-07', 22, 'RPU', $data, []));
    }

    public function test_bcs_dit_winter_holiday_hour_20()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-03', 20, 'RPU', $data, ['2025-11-03']));
    }

    public function test_bcs_dit_winter_weekday_hour_21()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-03', 21, 'RPU', $data, []));
    }

    public function test_bcs_dit_winter_sunday_hour_21()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-15', 21, 'RPU', $data, []));
    }

    public function test_bcs_invalid_grupo_tarifario()
    {
        $data = ['sistema' => 'BCS', 'grupoTarifario' => 'INVALID'];
        $this->assertNull($this->concrete->getBlock('2025-06-02', 10, 'RPU', $data, []));
    }

    // ==================== SIN GDMTH Tests ====================

    public function test_sin_gdmth_summer_holiday_hour_19()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-06-02', 19, 'RPU', $data, ['2025-06-02']));
    }

    public function test_sin_gdmth_summer_holiday_hour_20()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 20, 'RPU', $data, ['2025-06-02']));
    }

    public function test_sin_gdmth_summer_weekday_hour_6()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-06-02', 6, 'RPU', $data, []));
    }

    public function test_sin_gdmth_summer_weekday_hour_7()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 7, 'RPU', $data, []));
    }

    public function test_sin_gdmth_summer_weekday_hour_21()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-06-02', 21, 'RPU', $data, []));
    }

    public function test_sin_gdmth_summer_weekday_hour_23()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 23, 'RPU', $data, []));
    }

    public function test_sin_gdmth_summer_saturday_hour_7()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-06-07', 7, 'RPU', $data, []));
    }

    public function test_sin_gdmth_summer_saturday_hour_8()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-07', 8, 'RPU', $data, []));
    }

    public function test_sin_gdmth_summer_sunday_hour_19()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-06-08', 19, 'RPU', $data, []));
    }

    public function test_sin_gdmth_summer_sunday_hour_20()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-08', 20, 'RPU', $data, []));
    }

    public function test_sin_gdmth_winter_holiday_hour_18()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-03', 18, 'RPU', $data, ['2025-11-03']));
    }

    public function test_sin_gdmth_winter_holiday_hour_19()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-03', 19, 'RPU', $data, ['2025-11-03']));
    }

    public function test_sin_gdmth_winter_weekday_hour_18()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-03', 18, 'RPU', $data, []));
    }

    public function test_sin_gdmth_winter_weekday_hour_19()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-11-03', 19, 'RPU', $data, []));
    }

    public function test_sin_gdmth_winter_saturday_hour_8()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-08', 8, 'RPU', $data, []));
    }

    public function test_sin_gdmth_winter_saturday_hour_9()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-08', 9, 'RPU', $data, []));
    }

    public function test_sin_gdmth_winter_saturday_hour_20()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-11-08', 20, 'RPU', $data, []));
    }

    public function test_sin_gdmth_winter_sunday_hour_18()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'GDMTH'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-15', 18, 'RPU', $data, []));
    }
}
