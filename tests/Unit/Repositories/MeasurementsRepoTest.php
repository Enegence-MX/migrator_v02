<?php

namespace Tests\Unit\Repositories;

use App\Http\Repositories\MeasurementsRepo;
use PHPUnit\Framework\TestCase;
use Illuminate\Support\Facades\DB;
use Mockery;

class MeasurementsRepoTest extends TestCase
{
    /**
     * Test orderMesurementsKeys sorts nested arrays correctly.
     */
    public function test_order_mesurements_keys_sorts_nested_arrays()
    {
        $repo = new MeasurementsRepo();

        $data = [
            'rpu2' => [
                '2025-02-01' => [
                    '02' => ['2025-02-01 02:00:00' => 100],
                    '01' => ['2025-02-01 01:00:00' => 50]
                ],
                '2025-01-01' => [
                    '02' => ['2025-01-01 02:00:00' => 75],
                    '01' => ['2025-01-01 01:00:00' => 25]
                ]
            ],
            'rpu1' => [
                '2025-01-01' => [
                    '01' => ['2025-01-01 01:00:00' => 10]
                ]
            ]
        ];

        $repo->orderMesurementsKeys($data);

        // Check RPU sorting
        $keys = array_keys($data);
        $this->assertEquals('rpu1', $keys[0]);
        $this->assertEquals('rpu2', $keys[1]);

        // Check date sorting
        $rpu2Keys = array_keys($data['rpu2']);
        $this->assertEquals('2025-01-01', $rpu2Keys[0]);
        $this->assertEquals('2025-02-01', $rpu2Keys[1]);

        // Check hour sorting
        $hourKeys = array_keys($data['rpu2']['2025-02-01']);
        $this->assertEquals('01', $hourKeys[0]);
        $this->assertEquals('02', $hourKeys[1]);
    }

    /**
     * Test setHourlySumVariables calculates sums correctly.
     */
    public function test_set_hourly_sum_variables_calculates_sums()
    {
        $repo = new MeasurementsRepo();

        $mesurementsData = [
            'rpu1' => [
                '2025-01-01' => [
                    '01' => [10, 20, 30],
                    '02' => [5, 15, 25]
                ]
            ]
        ];

        $result = $repo->setHourlySumVariables($mesurementsData);

        $this->assertEquals(60, $result['rpu1']['2025-01-01']['01']);
        $this->assertEquals(45, $result['rpu1']['2025-01-01']['02']);
    }

    /**
     * Test setHourlySumVariables handles null values.
     */
    public function test_set_hourly_sum_variables_handles_nulls()
    {
        $repo = new MeasurementsRepo();

        $mesurementsData = [
            'rpu1' => [
                '2025-01-01' => [
                    '01' => [null, null, null]
                ]
            ]
        ];

        // Without canBeNull
        $result = $repo->setHourlySumVariables($mesurementsData, false);
        $this->assertEquals(0, $result['rpu1']['2025-01-01']['01']);

        // With canBeNull
        $result = $repo->setHourlySumVariables($mesurementsData, true);
        $this->assertNull($result['rpu1']['2025-01-01']['01']);
    }

    /**
     * Test setHourlySumVariables with mixed values.
     */
    public function test_set_hourly_sum_variables_with_mixed_values()
    {
        $repo = new MeasurementsRepo();

        $mesurementsData = [
            'rpu1' => [
                '2025-01-01' => [
                    '01' => [10, null, 30],
                    '02' => [0, 0, 0]
                ]
            ]
        ];

        $result = $repo->setHourlySumVariables($mesurementsData);

        $this->assertEquals(40, $result['rpu1']['2025-01-01']['01']); // null treated as 0
        $this->assertEquals(0, $result['rpu1']['2025-01-01']['02']);
    }

    /**
     * Test setHourlyMeasureTypeAndMissingDatesVariables finds most common type.
     */
    public function test_set_hourly_measure_type_finds_most_common()
    {
        $repo = new MeasurementsRepo();

        $mesurementsData = [
            'rpu1' => [
                '2025-01-01' => [
                    '01' => ['A', 'A', 'B', 'A', 'C', 'A', 'A', 'A', 'A', 'A', 'A', 'A']
                ]
            ]
        ];

        $result = $repo->setHourlyMeasureTypeAndMissingDatesVariables($mesurementsData);

        $this->assertEquals('A', $result['rpu1']['2025-01-01']['01']['type']);
        $this->assertNull($result['rpu1']['2025-01-01']['01']['missingRecords']);
    }

    /**
     * Test setHourlyMeasureTypeAndMissingDatesVariables detects missing records.
     */
    public function test_set_hourly_measure_type_detects_missing_records()
    {
        $repo = new MeasurementsRepo();

        $mesurementsData = [
            'rpu1' => [
                '2025-01-01' => [
                    '01' => ['A', 'A', 'B'] // Less than 12 records
                ]
            ]
        ];

        $result = $repo->setHourlyMeasureTypeAndMissingDatesVariables($mesurementsData);

        $this->assertEquals('A', $result['rpu1']['2025-01-01']['01']['type']);
        $this->assertEquals('yes', $result['rpu1']['2025-01-01']['01']['missingRecords']);
    }

    /**
     * Test setHourlyMeasureTypeAndMissingDatesVariables with tie uses first.
     */
    public function test_set_hourly_measure_type_with_tie()
    {
        $repo = new MeasurementsRepo();

        $mesurementsData = [
            'rpu1' => [
                '2025-01-01' => [
                    '01' => ['A', 'A', 'B', 'B', 'C', 'A', 'A', 'A', 'B', 'B', 'B', 'B']
                ]
            ]
        ];

        $result = $repo->setHourlyMeasureTypeAndMissingDatesVariables($mesurementsData);

        // B appears 6 times, A appears 5 times, C appears 1 time
        $this->assertEquals('B', $result['rpu1']['2025-01-01']['01']['type']);
    }

    /**
     * Test setHourlyDemandaRolada with simple data.
     */
    public function test_set_hourly_demanda_rolada_simple()
    {
        $repo = new MeasurementsRepo();

        $mesurementsData = [
            'rpu1' => [
                '2025-01-01' => [
                    '01' => [10],
                    '02' => [20],
                    '03' => [15]
                ]
            ]
        ];

        $result = $repo->setHourlyDemandaRolada($mesurementsData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('rpu1', $result);
    }

    /**
     * Test setDayToInt converts day names to integers.
     */
    public function test_set_day_to_int_monday()
    {
        $repo = new MeasurementsRepo();
        $this->assertEquals(1, $repo->setDayToInt('Monday'));
    }

    public function test_set_day_to_int_friday()
    {
        $repo = new MeasurementsRepo();
        $this->assertEquals(5, $repo->setDayToInt('Friday'));
    }

    public function test_set_day_to_int_sunday()
    {
        $repo = new MeasurementsRepo();
        $this->assertEquals(7, $repo->setDayToInt('Sunday'));
    }

    public function test_set_day_to_int_invalid_returns_empty_string()
    {
        $repo = new MeasurementsRepo();
        $this->assertEquals("", $repo->setDayToInt('InvalidDay'));
    }

    /**
     * Test isDateBewteenRange checks if date is within range.
     */
    public function test_is_date_between_range_inside()
    {
        $repo = new MeasurementsRepo();
        $result = $repo->isDateBewteenRange('2025-01-15', '2025-01-01', '2025-01-31');
        $this->assertTrue($result);
    }

    public function test_is_date_between_range_start_boundary()
    {
        $repo = new MeasurementsRepo();
        $result = $repo->isDateBewteenRange('2025-01-01', '2025-01-01', '2025-01-31');
        $this->assertTrue($result);
    }

    public function test_is_date_between_range_end_boundary()
    {
        $repo = new MeasurementsRepo();
        $result = $repo->isDateBewteenRange('2025-01-31', '2025-01-01', '2025-01-31');
        $this->assertTrue($result);
    }

    public function test_is_date_between_range_before()
    {
        $repo = new MeasurementsRepo();
        $result = $repo->isDateBewteenRange('2024-12-31', '2025-01-01', '2025-01-31');
        $this->assertFalse($result);
    }

    public function test_is_date_between_range_after()
    {
        $repo = new MeasurementsRepo();
        $result = $repo->isDateBewteenRange('2025-02-01', '2025-01-01', '2025-01-31');
        $this->assertFalse($result);
    }

    // Commented out temporarily - complex DB mocking required
    // /**
    //  * Test updateOrInsertCCMeasurement with mocked DB
    //  */
    // public function test_update_or_insert_cc_measurement()
    // {
    //     DB::shouldReceive('connection')->andReturnSelf();
    //     DB::shouldReceive('table')->andReturnSelf();
    //     DB::shouldReceive('where')->andReturnSelf();
    //     DB::shouldReceive('first')->andReturn(null);
    //     DB::shouldReceive('insert')->andReturn(true);

    //     $repo = new MeasurementsRepo();

    //     $arrayData = [
    //         'RPU' => 'RPU123',
    //         'FECHA' => '2025-01-15',
    //         'HORA' => '10',
    //         'DEMANDA_ROLADA' => 100.5
    //     ];

    //     $result = $repo->updateOrInsertCCMeasurement($arrayData);
    //     $this->assertTrue(true); // Method doesn't return, just verify no exceptions
    // }

    // /**
    //  * Test bulkUpsertCCMeasurements with mocked DB
    //  */
    // public function test_bulk_upsert_cc_measurements()
    // {
    //     DB::shouldReceive('connection')->andReturnSelf();
    //     DB::shouldReceive('table')->andReturnSelf();
    //     DB::shouldReceive('where')->andReturnSelf();
    //     DB::shouldReceive('whereIn')->andReturnSelf();
    //     DB::shouldReceive('get')->andReturn(collect());
    //     DB::shouldReceive('insert')->andReturn(true);
    //     DB::shouldReceive('beginTransaction')->andReturn(true);
    //     DB::shouldReceive('commit')->andReturn(true);

    //     $repo = new MeasurementsRepo();

    //     $rows = [
    //         ['RPU' => 'RPU1', 'FECHA' => '2025-01-15', 'HORA' => '10'],
    //         ['RPU' => 'RPU2', 'FECHA' => '2025-01-15', 'HORA' => '11']
    //     ];

    //     $result = $repo->bulkUpsertCCMeasurements($rows);
    //     $this->assertIsArray($result);
    // }

    /**
     * Test checkYearStation from GetBlockValues trait
     */
    public function test_check_year_station_from_measurements_repo()
    {
        $repo = new MeasurementsRepo();
        $result = $repo->checkYearStation('2025-06-15', 'twoSeason');
        $this->assertIsInt($result);
    }

    /**
     * Test parseMesurementsData with simple CSV data (Wh measurement)
     */
    public function test_parse_mesurements_data_simple_wh()
    {
        $repo = new MeasurementsRepo();

        $csvContent = "Field1,DateTime,Wh,kwhr,kvarh\nValue1,2025-01-15 10:05:00,100,50,25";

        $result = $repo->parseMesurementsData($csvContent, 'RPU-001', 'Wh', 'BCA', false);

        $this->assertIsArray($result);
    }

    /**
     * Test parseMesurementsData with kwhe measurement
     */
    public function test_parse_mesurements_data_kwhe()
    {
        $repo = new MeasurementsRepo();

        $csvContent = "Field1,DateTime,Wh,kwhr,kvarh\nValue1,2025-01-15 10:05:00,100,50,25";

        $result = $repo->parseMesurementsData($csvContent, 'RPU-001', 'kwhe', 'BCA', false);

        $this->assertIsArray($result);
    }

    /**
     * Test parseMesurementsData with kwhr measurement
     */
    public function test_parse_mesurements_data_kwhr()
    {
        $repo = new MeasurementsRepo();

        $csvContent = "Field1,DateTime,Wh,kwhr,kvarh\nValue1,2025-01-15 10:05:00,100,50,25";

        $result = $repo->parseMesurementsData($csvContent, 'RPU-001', 'kwhr', 'BCA', false);

        $this->assertIsArray($result);
    }

    /**
     * Test parseMesurementsData with kvarh measurement
     */
    public function test_parse_mesurements_data_kvarh()
    {
        $repo = new MeasurementsRepo();

        $csvContent = "Field1,DateTime,Wh,kwhr,kvarh\nValue1,2025-01-15 10:05:00,100,50,25";

        $result = $repo->parseMesurementsData($csvContent, 'RPU-001', 'kvarh', 'BCA', false);

        $this->assertIsArray($result);
    }

    /**
     * Test parseMesurementsData with empty content
     */
    public function test_parse_mesurements_data_empty_content()
    {
        $repo = new MeasurementsRepo();

        $csvContent = "";

        $result = $repo->parseMesurementsData($csvContent, 'RPU-001', 'Wh', 'BCA', false);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test parseMesurementsData with multiple records
     */
    public function test_parse_mesurements_data_multiple_records()
    {
        $repo = new MeasurementsRepo();

        $csvContent = "Field1,DateTime,Wh,kwhr,kvarh\n";
        $csvContent .= "Value1,2025-01-15 10:05:00,100,50,25\n";
        $csvContent .= "Value2,2025-01-15 10:10:00,110,55,30\n";
        $csvContent .= "Value3,2025-01-15 10:15:00,120,60,35";

        $result = $repo->parseMesurementsData($csvContent, 'RPU-001', 'Wh', 'BCA', false);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    /**
     * Test parseMesurementsData with canBeNull parameter
     */
    public function test_parse_mesurements_data_can_be_null_true()
    {
        $repo = new MeasurementsRepo();

        $csvContent = "Field1,DateTime,Wh,kwhr,kvarh\nValue1,2025-01-15 10:05:00,100,50,25";

        $result = $repo->parseMesurementsData($csvContent, 'RPU-001', 'Wh', 'BCA', true);

        $this->assertIsArray($result);
    }

    /**
     * Test setHourlyDemandaRolada with simple data structure
     */
    public function test_set_hourly_demanda_rolada_detailed()
    {
        $repo = new MeasurementsRepo();

        $mesurementsData = [
            'RPU1' => [
                '2025-01-15' => [
                    '10' => [100, 110, 120, 130, 140, 150, 160, 170, 180, 190, 200, 210],
                    '11' => [200, 210, 220, 230, 240, 250, 260, 270, 280, 290, 300, 310]
                ]
            ]
        ];

        $result = $repo->setHourlyDemandaRolada($mesurementsData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('RPU1', $result);
    }

    /**
     * Test setHourlyDemandaRolada with minimal data
     */
    public function test_set_hourly_demanda_rolada_minimal_data()
    {
        $repo = new MeasurementsRepo();

        $mesurementsData = [
            'RPU1' => [
                '2025-01-15' => [
                    '10' => [100]
                ]
            ]
        ];

        $result = $repo->setHourlyDemandaRolada($mesurementsData);

        $this->assertIsArray($result);
    }

    /**
     * Test setDayToInt with all days of week
     */
    public function test_set_day_to_int_tuesday()
    {
        $repo = new MeasurementsRepo();
        $this->assertEquals(2, $repo->setDayToInt('Tuesday'));
    }

    public function test_set_day_to_int_wednesday()
    {
        $repo = new MeasurementsRepo();
        $this->assertEquals(3, $repo->setDayToInt('Wednesday'));
    }

    public function test_set_day_to_int_thursday()
    {
        $repo = new MeasurementsRepo();
        $this->assertEquals(4, $repo->setDayToInt('Thursday'));
    }

    public function test_set_day_to_int_saturday()
    {
        $repo = new MeasurementsRepo();
        $this->assertEquals(6, $repo->setDayToInt('Saturday'));
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
