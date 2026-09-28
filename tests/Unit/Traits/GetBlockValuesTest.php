<?php

namespace Tests\Unit\Traits;

use PHPUnit\Framework\TestCase;
use App\Http\Traits\GetBlockValues;

// Concrete class to test the trait
class GetBlockValuesConcrete
{
    use GetBlockValues;
}

class GetBlockValuesTest extends TestCase
{
    /**
     * Test checkYearStation with twoSeasonVarient in summer
     */
    public function test_check_year_station_two_season_varient_summer()
    {
        $concrete = new GetBlockValuesConcrete();

        // May 1st should be summer (station 2)
        $result = $concrete->checkYearStation('2025-05-15', 'twoSeasonVarient');
        $this->assertEquals(2, $result);
    }

    /**
     * Test checkYearStation with twoSeasonVarient in winter
     */
    public function test_check_year_station_two_season_varient_winter()
    {
        $concrete = new GetBlockValuesConcrete();

        // November should be winter (station 4)
        $result = $concrete->checkYearStation('2025-11-15', 'twoSeasonVarient');
        $this->assertEquals(4, $result);
    }

    /**
     * Test checkYearStation with twoSeason in summer
     */
    public function test_check_year_station_two_season_summer()
    {
        $concrete = new GetBlockValuesConcrete();

        // June should be summer (station 2)
        $result = $concrete->checkYearStation('2025-06-15', 'twoSeason');
        $this->assertEquals(2, $result);
    }

    /**
     * Test checkYearStation with twoSeason in winter
     */
    public function test_check_year_station_two_season_winter()
    {
        $concrete = new GetBlockValuesConcrete();

        // December should be winter (station 4)
        $result = $concrete->checkYearStation('2025-12-15', 'twoSeason');
        $this->assertEquals(4, $result);
    }

    /**
     * Test checkYearStation with fourSeason - spring
     */
    public function test_check_year_station_four_season_spring()
    {
        $concrete = new GetBlockValuesConcrete();

        // February should be spring (station 1)
        $result = $concrete->checkYearStation('2025-02-15', 'fourSeason');
        $this->assertEquals(1, $result);
    }

    /**
     * Test checkYearStation with fourSeason - summer
     */
    public function test_check_year_station_four_season_summer()
    {
        $concrete = new GetBlockValuesConcrete();

        // June should be summer (station 2)
        $result = $concrete->checkYearStation('2025-06-15', 'fourSeason');
        $this->assertEquals(2, $result);
    }

    /**
     * Test checkYearStation with fourSeason - autumn
     */
    public function test_check_year_station_four_season_autumn()
    {
        $concrete = new GetBlockValuesConcrete();

        // August should be autumn (station 3)
        $result = $concrete->checkYearStation('2025-08-15', 'fourSeason');
        $this->assertEquals(3, $result);
    }

    /**
     * Test checkYearStation with fourSeason - winter
     */
    public function test_check_year_station_four_season_winter()
    {
        $concrete = new GetBlockValuesConcrete();

        // November should be winter (station 4)
        $result = $concrete->checkYearStation('2025-11-15', 'fourSeason');
        $this->assertEquals(4, $result);
    }

    /**
     * Test getBlock BCA GDMTH summer weekday intermediate hour
     */
    public function test_get_block_bca_gdmth_summer_weekday_intermediate()
    {
        $concrete = new GetBlockValuesConcrete();

        $dataFromChargeCenter = [
            'sistema' => 'BCA',
            'grupoTarifario' => 'GDMTH'
        ];

        // Monday in summer, hour 10 should be I (intermediate)
        $result = $concrete->getBlock('2025-06-02', 10, 'RPU1', $dataFromChargeCenter, []);
        $this->assertEquals('I', $result);
    }

    /**
     * Test getBlock BCA GDMTH summer weekday peak hour
     */
    public function test_get_block_bca_gdmth_summer_weekday_peak()
    {
        $concrete = new GetBlockValuesConcrete();

        $dataFromChargeCenter = [
            'sistema' => 'BCA',
            'grupoTarifario' => 'GDMTH'
        ];

        // Monday in summer, hour 16 should be P (peak)
        $result = $concrete->getBlock('2025-06-02', 16, 'RPU1', $dataFromChargeCenter, []);
        $this->assertEquals('P', $result);
    }

    /**
     * Test getBlock BCA GDMTH summer holiday
     */
    public function test_get_block_bca_gdmth_summer_holiday()
    {
        $concrete = new GetBlockValuesConcrete();

        $dataFromChargeCenter = [
            'sistema' => 'BCA',
            'grupoTarifario' => 'GDMTH'
        ];

        // Holiday in summer should be I
        $result = $concrete->getBlock('2025-06-02', 10, 'RPU1', $dataFromChargeCenter, ['2025-06-02']);
        $this->assertEquals('I', $result);
    }

    /**
     * Test getBlock BCA GDMTH winter weekday base hour
     */
    public function test_get_block_bca_gdmth_winter_weekday_base()
    {
        $concrete = new GetBlockValuesConcrete();

        $dataFromChargeCenter = [
            'sistema' => 'BCA',
            'grupoTarifario' => 'GDMTH'
        ];

        // Monday in winter, hour 10 should be B (base)
        $result = $concrete->getBlock('2025-11-03', 10, 'RPU1', $dataFromChargeCenter, []);
        $this->assertEquals('B', $result);
    }

    /**
     * Test getBlock BCA GDMTH winter weekday intermediate hour
     */
    public function test_get_block_bca_gdmth_winter_weekday_intermediate()
    {
        $concrete = new GetBlockValuesConcrete();

        $dataFromChargeCenter = [
            'sistema' => 'BCA',
            'grupoTarifario' => 'GDMTH'
        ];

        // Monday in winter, hour 20 should be I (intermediate)
        $result = $concrete->getBlock('2025-11-03', 20, 'RPU1', $dataFromChargeCenter, []);
        $this->assertEquals('I', $result);
    }

    /**
     * Test getBlock BCA DIST summer weekday semi-peak
     */
    public function test_get_block_bca_dist_summer_weekday_semipeak()
    {
        $concrete = new GetBlockValuesConcrete();

        $dataFromChargeCenter = [
            'sistema' => 'BCA',
            'grupoTarifario' => 'DIST'
        ];

        // Monday in summer, hour 13 should be S (semi-peak)
        $result = $concrete->getBlock('2025-06-02', 13, 'RPU1', $dataFromChargeCenter, []);
        $this->assertEquals('S', $result);
    }

    /**
     * Test getBlock BCA DIST summer weekday peak
     */
    public function test_get_block_bca_dist_summer_weekday_peak()
    {
        $concrete = new GetBlockValuesConcrete();

        $dataFromChargeCenter = [
            'sistema' => 'BCA',
            'grupoTarifario' => 'DIST'
        ];

        // Monday in summer, hour 16 should be P (peak)
        $result = $concrete->getBlock('2025-06-02', 16, 'RPU1', $dataFromChargeCenter, []);
        $this->assertEquals('P', $result);
    }

    /**
     * Test getBlock BCA DIT summer weekday
     */
    public function test_get_block_bca_dit_summer_weekday()
    {
        $concrete = new GetBlockValuesConcrete();

        $dataFromChargeCenter = [
            'sistema' => 'BCA',
            'grupoTarifario' => 'DIT'
        ];

        // Monday in summer, hour 15 should be P (peak)
        $result = $concrete->getBlock('2025-06-02', 15, 'RPU1', $dataFromChargeCenter, []);
        $this->assertEquals('P', $result);
    }

    /**
     * Test getBlock BCN GDMTH summer
     */
    public function test_get_block_bcn_gdmth_summer()
    {
        $concrete = new GetBlockValuesConcrete();

        $dataFromChargeCenter = [
            'sistema' => 'BCN',
            'grupoTarifario' => 'GDMTH'
        ];

        // Should return a block value (may be "-" if not defined)
        $result = $concrete->getBlock('2025-06-02', 10, 'RPU1', $dataFromChargeCenter, []);
        $this->assertIsString($result);
    }

    /**
     * Test getBlock BCS GDMTH summer
     */
    public function test_get_block_bcs_gdmth_summer()
    {
        $concrete = new GetBlockValuesConcrete();

        $dataFromChargeCenter = [
            'sistema' => 'BCS',
            'grupoTarifario' => 'GDMTH'
        ];

        // Should return a block value
        $result = $concrete->getBlock('2025-06-02', 10, 'RPU1', $dataFromChargeCenter, []);
        $this->assertNotEmpty($result);
    }

    /**
     * Test getBlock SIM GDBT summer weekday
     */
    public function test_get_block_sim_gdbt_summer_weekday()
    {
        $concrete = new GetBlockValuesConcrete();

        $dataFromChargeCenter = [
            'sistema' => 'SIM',
            'grupoTarifario' => 'GDBT'
        ];

        // Should return a block value (may be "-" if not defined)
        $result = $concrete->getBlock('2025-06-02', 10, 'RPU1', $dataFromChargeCenter, []);
        $this->assertIsString($result);
    }

    /**
     * Test getBlock SIM GDMTO summer weekday
     */
    public function test_get_block_sim_gdmto_summer_weekday()
    {
        $concrete = new GetBlockValuesConcrete();

        $dataFromChargeCenter = [
            'sistema' => 'SIM',
            'grupoTarifario' => 'GDMTO'
        ];

        // Should return a block value (may be "-" if not defined)
        $result = $concrete->getBlock('2025-06-02', 10, 'RPU1', $dataFromChargeCenter, []);
        $this->assertIsString($result);
    }

    /**
     * Test getBlock on Saturday
     */
    public function test_get_block_saturday()
    {
        $concrete = new GetBlockValuesConcrete();

        $dataFromChargeCenter = [
            'sistema' => 'BCA',
            'grupoTarifario' => 'GDMTH'
        ];

        // Saturday in summer
        $result = $concrete->getBlock('2025-06-07', 10, 'RPU1', $dataFromChargeCenter, []);
        $this->assertEquals('I', $result);
    }

    /**
     * Test getBlock on Sunday
     */
    public function test_get_block_sunday()
    {
        $concrete = new GetBlockValuesConcrete();

        $dataFromChargeCenter = [
            'sistema' => 'BCA',
            'grupoTarifario' => 'GDMTH'
        ];

        // Sunday in summer
        $result = $concrete->getBlock('2025-06-08', 10, 'RPU1', $dataFromChargeCenter, []);
        $this->assertEquals('I', $result);
    }

    /**
     * Test getBlock winter Saturday different hours
     */
    public function test_get_block_bca_gdmth_winter_saturday_base()
    {
        $concrete = new GetBlockValuesConcrete();

        $dataFromChargeCenter = [
            'sistema' => 'BCA',
            'grupoTarifario' => 'GDMTH'
        ];

        // Saturday in winter, hour 10 should be B
        $result = $concrete->getBlock('2025-11-08', 10, 'RPU1', $dataFromChargeCenter, []);
        $this->assertEquals('B', $result);
    }

    /**
     * Test getBlock winter Saturday intermediate hours
     */
    public function test_get_block_bca_gdmth_winter_saturday_intermediate()
    {
        $concrete = new GetBlockValuesConcrete();

        $dataFromChargeCenter = [
            'sistema' => 'BCA',
            'grupoTarifario' => 'GDMTH'
        ];

        // Saturday in winter, hour 20 should be I
        $result = $concrete->getBlock('2025-11-08', 20, 'RPU1', $dataFromChargeCenter, []);
        $this->assertEquals('I', $result);
    }
}
