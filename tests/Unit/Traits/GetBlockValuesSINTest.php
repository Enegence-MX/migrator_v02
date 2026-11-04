<?php

namespace Tests\Unit\Traits;

use PHPUnit\Framework\TestCase;
use App\Http\Traits\GetBlockValues;

// Concrete class to test the trait
class GetBlockValuesConcrete3
{
    use GetBlockValues;
}

/**
 * Tests for SIN system (DIST and DIT with 4 seasons)
 * Goal: Achieve 90%+ code coverage for GetBlockValues
 */
class GetBlockValuesSINTest extends TestCase
{
    protected $concrete;

    protected function setUp(): void
    {
        parent::setUp();
        $this->concrete = new GetBlockValuesConcrete3();
    }

    // ==================== SIN DIST Spring (Primavera) Tests ====================

    public function test_sin_dist_spring_holiday_hour_19()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-02-15', 19, 'RPU', $data, ['2025-02-15']));
    }

    public function test_sin_dist_spring_holiday_hour_20()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-02-15', 20, 'RPU', $data, ['2025-02-15']));
    }

    public function test_sin_dist_spring_holiday_hour_24()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-02-15', 24, 'RPU', $data, ['2025-02-15']));
    }

    public function test_sin_dist_spring_weekday_hour_6()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-02-17', 6, 'RPU', $data, []));
    }

    public function test_sin_dist_spring_weekday_hour_7()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-02-17', 7, 'RPU', $data, []));
    }

    public function test_sin_dist_spring_weekday_hour_20()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-02-17', 20, 'RPU', $data, []));
    }

    public function test_sin_dist_spring_weekday_hour_23()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-02-17', 23, 'RPU', $data, []));
    }

    public function test_sin_dist_spring_saturday_hour_7()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-02-22', 7, 'RPU', $data, []));
    }

    public function test_sin_dist_spring_saturday_hour_8()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-02-22', 8, 'RPU', $data, []));
    }

    public function test_sin_dist_spring_sunday_hour_19()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-02-23', 19, 'RPU', $data, []));
    }

    public function test_sin_dist_spring_sunday_hour_23()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-02-23', 23, 'RPU', $data, []));
    }

    // ==================== SIN DIST Summer (Verano) Tests ====================

    public function test_sin_dist_summer_holiday_hour_19()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-06-02', 19, 'RPU', $data, ['2025-06-02']));
    }

    public function test_sin_dist_summer_holiday_hour_20()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 20, 'RPU', $data, ['2025-06-02']));
    }

    public function test_sin_dist_summer_weekday_hour_1()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 1, 'RPU', $data, []));
    }

    public function test_sin_dist_summer_weekday_hour_2()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-06-02', 2, 'RPU', $data, []));
    }

    public function test_sin_dist_summer_weekday_hour_21()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-06-02', 21, 'RPU', $data, []));
    }

    public function test_sin_dist_summer_saturday_hour_1()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-07', 1, 'RPU', $data, []));
    }

    public function test_sin_dist_summer_saturday_hour_2()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-06-07', 2, 'RPU', $data, []));
    }

    public function test_sin_dist_summer_sunday_hour_19()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-06-08', 19, 'RPU', $data, []));
    }

    // ==================== SIN DIST Autumn (Otoño) Tests ====================

    public function test_sin_dist_autumn_holiday_hour_19()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-08-15', 19, 'RPU', $data, ['2025-08-15']));
    }

    public function test_sin_dist_autumn_holiday_hour_20()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-08-15', 20, 'RPU', $data, ['2025-08-15']));
    }

    public function test_sin_dist_autumn_weekday_hour_6()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-08-18', 6, 'RPU', $data, []));
    }

    public function test_sin_dist_autumn_weekday_hour_7()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-08-18', 7, 'RPU', $data, []));
    }

    public function test_sin_dist_autumn_weekday_hour_20()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-08-18', 20, 'RPU', $data, []));
    }

    public function test_sin_dist_autumn_saturday_hour_8()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-08-23', 8, 'RPU', $data, []));
    }

    public function test_sin_dist_autumn_sunday_hour_24()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-08-24', 24, 'RPU', $data, []));
    }

    // ==================== SIN DIST Winter (Invierno) Tests ====================

    public function test_sin_dist_winter_holiday_hour_18()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-03', 18, 'RPU', $data, ['2025-11-03']));
    }

    public function test_sin_dist_winter_holiday_hour_19()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-03', 19, 'RPU', $data, ['2025-11-03']));
    }

    public function test_sin_dist_winter_weekday_hour_18()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-03', 18, 'RPU', $data, []));
    }

    public function test_sin_dist_winter_weekday_hour_19()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-11-03', 19, 'RPU', $data, []));
    }

    public function test_sin_dist_winter_saturday_hour_8()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-08', 8, 'RPU', $data, []));
    }

    public function test_sin_dist_winter_saturday_hour_9()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-08', 9, 'RPU', $data, []));
    }

    public function test_sin_dist_winter_saturday_hour_21()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-11-08', 21, 'RPU', $data, []));
    }

    public function test_sin_dist_winter_sunday_hour_18()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIST'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-15', 18, 'RPU', $data, []));
    }

    // ==================== SIN DIT Spring (Primavera) Tests ====================

    public function test_sin_dit_spring_holiday_hour_19()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-02-15', 19, 'RPU', $data, ['2025-02-15']));
    }

    public function test_sin_dit_spring_holiday_hour_20()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-02-15', 20, 'RPU', $data, ['2025-02-15']));
    }

    public function test_sin_dit_spring_holiday_hour_24()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-02-15', 24, 'RPU', $data, ['2025-02-15']));
    }

    public function test_sin_dit_spring_weekday_hour_6()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-02-17', 6, 'RPU', $data, []));
    }

    public function test_sin_dit_spring_weekday_hour_7()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-02-17', 7, 'RPU', $data, []));
    }

    public function test_sin_dit_spring_weekday_hour_21()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-02-17', 21, 'RPU', $data, []));
    }

    public function test_sin_dit_spring_weekday_hour_24()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-02-17', 24, 'RPU', $data, []));
    }

    public function test_sin_dit_spring_saturday_hour_8()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-02-22', 8, 'RPU', $data, []));
    }

    public function test_sin_dit_spring_sunday_hour_19()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-02-23', 19, 'RPU', $data, []));
    }

    public function test_sin_dit_spring_sunday_hour_23()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-02-23', 23, 'RPU', $data, []));
    }

    // ==================== SIN DIT Summer (Verano) Tests ====================

    public function test_sin_dit_summer_holiday_hour_19()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-06-02', 19, 'RPU', $data, ['2025-06-02']));
    }

    public function test_sin_dit_summer_holiday_hour_20()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 20, 'RPU', $data, ['2025-06-02']));
    }

    public function test_sin_dit_summer_weekday_hour_1()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 1, 'RPU', $data, []));
    }

    public function test_sin_dit_summer_weekday_hour_2()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-06-02', 2, 'RPU', $data, []));
    }

    public function test_sin_dit_summer_weekday_hour_21()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-02', 21, 'RPU', $data, []));
    }

    public function test_sin_dit_summer_weekday_hour_22()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-06-02', 22, 'RPU', $data, []));
    }

    public function test_sin_dit_summer_saturday_hour_1()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-06-07', 1, 'RPU', $data, []));
    }

    public function test_sin_dit_summer_saturday_hour_2()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-06-07', 2, 'RPU', $data, []));
    }

    public function test_sin_dit_summer_sunday_hour_19()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-06-08', 19, 'RPU', $data, []));
    }

    // ==================== SIN DIT Autumn (Otoño) Tests ====================

    public function test_sin_dit_autumn_holiday_hour_19()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-08-15', 19, 'RPU', $data, ['2025-08-15']));
    }

    public function test_sin_dit_autumn_holiday_hour_20()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-08-15', 20, 'RPU', $data, ['2025-08-15']));
    }

    public function test_sin_dit_autumn_weekday_hour_6()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-08-18', 6, 'RPU', $data, []));
    }

    public function test_sin_dit_autumn_weekday_hour_7()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-08-18', 7, 'RPU', $data, []));
    }

    public function test_sin_dit_autumn_weekday_hour_21()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-08-18', 21, 'RPU', $data, []));
    }

    public function test_sin_dit_autumn_weekday_hour_24()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-08-18', 24, 'RPU', $data, []));
    }

    public function test_sin_dit_autumn_saturday_hour_8()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-08-23', 8, 'RPU', $data, []));
    }

    public function test_sin_dit_autumn_sunday_hour_24()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-08-24', 24, 'RPU', $data, []));
    }

    // ==================== SIN DIT Winter (Invierno) Tests ====================

    public function test_sin_dit_winter_holiday_hour_18()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-03', 18, 'RPU', $data, ['2025-11-03']));
    }

    public function test_sin_dit_winter_holiday_hour_19()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-03', 19, 'RPU', $data, ['2025-11-03']));
    }

    public function test_sin_dit_winter_weekday_hour_6()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-03', 6, 'RPU', $data, []));
    }

    public function test_sin_dit_winter_weekday_hour_7()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-03', 7, 'RPU', $data, []));
    }

    public function test_sin_dit_winter_weekday_hour_20()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-11-03', 20, 'RPU', $data, []));
    }

    public function test_sin_dit_winter_saturday_hour_8()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('B', $this->concrete->getBlock('2025-11-08', 8, 'RPU', $data, []));
    }

    public function test_sin_dit_winter_saturday_hour_9()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-08', 9, 'RPU', $data, []));
    }

    public function test_sin_dit_winter_saturday_hour_21()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('P', $this->concrete->getBlock('2025-11-08', 21, 'RPU', $data, []));
    }

    public function test_sin_dit_winter_saturday_hour_24()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-08', 24, 'RPU', $data, []));
    }

    public function test_sin_dit_winter_sunday_hour_18()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'DIT'];
        $this->assertEquals('I', $this->concrete->getBlock('2025-11-15', 18, 'RPU', $data, []));
    }

    public function test_sin_invalid_grupo_tarifario()
    {
        $data = ['sistema' => 'SIN', 'grupoTarifario' => 'INVALID'];
        $this->assertNull($this->concrete->getBlock('2025-06-02', 10, 'RPU', $data, []));
    }

    // ==================== checkYearStation edge cases ====================

    public function test_check_year_station_two_season_varient_boundary_start_summer()
    {
        $result = $this->concrete->checkYearStation('2025-05-01', 'twoSeasonVarient');
        $this->assertEquals(2, $result);
    }

    public function test_check_year_station_two_season_boundary_end_winter()
    {
        $result = $this->concrete->checkYearStation('2025-01-15', 'twoSeason');
        $this->assertEquals(4, $result);
    }

    public function test_check_year_station_four_season_spring_start()
    {
        $result = $this->concrete->checkYearStation('2025-02-01', 'fourSeason');
        $this->assertEquals(1, $result);
    }

    public function test_check_year_station_four_season_summer_july()
    {
        $result = $this->concrete->checkYearStation('2025-07-15', 'fourSeason');
        $this->assertEquals(2, $result);
    }

    public function test_check_year_station_four_season_autumn_august()
    {
        $result = $this->concrete->checkYearStation('2025-08-01', 'fourSeason');
        $this->assertEquals(3, $result);
    }

    public function test_check_year_station_four_season_winter_january()
    {
        $result = $this->concrete->checkYearStation('2026-01-15', 'fourSeason');
        $this->assertEquals(4, $result);
    }
}
