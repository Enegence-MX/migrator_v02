<?php

namespace Tests\Unit\Traits;

use PHPUnit\Framework\TestCase;
use App\Http\Traits\MeasurementsRepoTrait;

// Concrete class to test the trait
class MeasurementsRepoTraitConcrete
{
    use MeasurementsRepoTrait;
}

class MeasurementsRepoTraitTest extends TestCase
{
    /**
     * Test mergeDataArrays combines multiple measurement arrays.
     */
    public function test_merge_data_arrays_combines_measurements()
    {
        $concrete = new MeasurementsRepoTraitConcrete();

        $demandaRoladaHourlyMax = [
            'rpu1' => [
                '2025-01-01' => [
                    '01' => 150.5
                ]
            ]
        ];

        $measurementskwheHourlySums = [
            'rpu1' => [
                '2025-01-01' => [
                    '01' => 100.0
                ]
            ]
        ];

        $measurementKwhrHourlySums = [
            'rpu1' => [
                '2025-01-01' => [
                    '01' => 80.0
                ]
            ]
        ];

        $measurementKvarHourlySums = [
            'rpu1' => [
                '2025-01-01' => [
                    '01' => 50.0
                ]
            ]
        ];

        $measurementsTypeAndMissingDatesHourly = [
            'rpu1' => [
                '2025-01-01' => [
                    '01' => [
                        'type' => 'A',
                        'missingRecords' => null
                    ]
                ]
            ]
        ];

        $result = $concrete->mergeDataArrays(
            $demandaRoladaHourlyMax,
            $measurementskwheHourlySums,
            $measurementKwhrHourlySums,
            $measurementKvarHourlySums,
            $measurementsTypeAndMissingDatesHourly
        );

        $this->assertArrayHasKey('rpu1', $result);
        $this->assertArrayHasKey('2025-01-01', $result['rpu1']);
        $this->assertArrayHasKey('01', $result['rpu1']['2025-01-01']);

        $hourData = $result['rpu1']['2025-01-01']['01'];
        $this->assertEquals(150.5, $hourData['rolledDemand']);
        $this->assertEquals(100.0, $hourData['kwhe']);
        $this->assertEquals(80.0, $hourData['kwhr']);
        $this->assertEquals(50.0, $hourData['kvar']);
        $this->assertEquals('A', $hourData['tipo']);
        $this->assertNull($hourData['missingRecordAdded']);
    }

    /**
     * Test mergeDataArrays with multiple hours.
     */
    public function test_merge_data_arrays_multiple_hours()
    {
        $concrete = new MeasurementsRepoTraitConcrete();

        $demandaRoladaHourlyMax = [
            'rpu1' => [
                '2025-01-01' => [
                    '01' => 150.5,
                    '02' => 200.0
                ]
            ]
        ];

        $measurementskwheHourlySums = [
            'rpu1' => [
                '2025-01-01' => [
                    '01' => 100.0,
                    '02' => 120.0
                ]
            ]
        ];

        $measurementKwhrHourlySums = [
            'rpu1' => [
                '2025-01-01' => [
                    '01' => 80.0,
                    '02' => 90.0
                ]
            ]
        ];

        $measurementKvarHourlySums = [
            'rpu1' => [
                '2025-01-01' => [
                    '01' => 50.0,
                    '02' => 60.0
                ]
            ]
        ];

        $measurementsTypeAndMissingDatesHourly = [
            'rpu1' => [
                '2025-01-01' => [
                    '01' => [
                        'type' => 'A',
                        'missingRecords' => null
                    ],
                    '02' => [
                        'type' => 'B',
                        'missingRecords' => 'yes'
                    ]
                ]
            ]
        ];

        $result = $concrete->mergeDataArrays(
            $demandaRoladaHourlyMax,
            $measurementskwheHourlySums,
            $measurementKwhrHourlySums,
            $measurementKvarHourlySums,
            $measurementsTypeAndMissingDatesHourly
        );

        $this->assertCount(2, $result['rpu1']['2025-01-01']);
        $this->assertEquals('yes', $result['rpu1']['2025-01-01']['02']['missingRecordAdded']);
    }

    /**
     * Test parse JsonToCsv File converts json to CSV format
     */
    public function test_parse_json_to_csv_file()
    {
        $concrete = new MeasurementsRepoTraitConcrete();

        $jsonData = [
            (object)[
                'fecha' => '2025-01-01',
                'hora' => '00:05:00',
                'kwhe' => 10.5,
                'kwhr' => 8.2,
                'kvarh' => 5.1,
                'tipo' => 'R'
            ],
            (object)[
                'fecha' => '2025-01-01',
                'hora' => '00:10:00',
                'kwhe' => 11.0,
                'kwhr' => 9.0,
                'kvarh' => 6.0,
                'tipo' => 'R'
            ]
        ];

        $result = $this->invokeProtectedMethod($concrete, 'parseJsonToCsvFile', [$jsonData]);

        $this->assertStringContainsString('No.,Fecha,kWh E,kWh R,kVARh,Tipo', $result);
        $this->assertStringContainsString('2025-01-01 00:05:00', $result);
        $this->assertStringContainsString('10.5', $result);
        $this->assertStringContainsString('8.2', $result);
        $this->assertStringContainsString('5.1', $result);
    }

    /**
     * Test parseJsonToCsvFile with null values
     */
    public function test_parse_json_to_csv_file_with_nulls()
    {
        $concrete = new MeasurementsRepoTraitConcrete();

        $jsonData = [
            (object)[
                'fecha' => '2025-01-01',
                'hora' => '00:05:00',
                'kwhe' => null,
                'kwhr' => null,
                'kvarh' => null,
                'tipo' => 'Estimada'
            ]
        ];

        $result = $this->invokeProtectedMethod($concrete, 'parseJsonToCsvFile', [$jsonData]);

        $this->assertStringContainsString('Estimada', $result);
        $this->assertStringContainsString('2025-01-01 00:05:00', $result);
    }

    /**
     * Test fillUpGapMeasurements fills missing 5-minute intervals
     */
    public function test_fill_up_gap_measurements()
    {
        $concrete = new MeasurementsRepoTraitConcrete();

        $datesRange = ['2025-01-01'];
        $jsonData = [
            (object)[
                'fecha' => '2025-01-01',
                'hora' => '00:05:00',
                'kwhe' => 10.0,
                'kwhr' => 8.0,
                'kvarh' => 5.0,
                'tipo' => 'R'
            ],
            (object)[
                'fecha' => '2025-01-01',
                'hora' => '00:15:00', // Missing 00:10:00
                'kwhe' => 11.0,
                'kwhr' => 9.0,
                'kvarh' => 6.0,
                'tipo' => 'R'
            ]
        ];

        $result = $concrete->fillUpGapMeasurements($datesRange, $jsonData);

        // Should have 287 intervals (288 - 1 from array_shift)
        $this->assertCount(287, $result);

        // First entry should be 00:05:00
        $this->assertEquals('00:05:00', $result[0]->hora);

        // Check that missing interval is filled with Estimada
        $hasEstimated = false;
        foreach ($result as $entry) {
            if ($entry->hora === '00:10:00') {
                $this->assertEquals('Estimada', $entry->tipo);
                $hasEstimated = true;
            }
        }
        $this->assertTrue($hasEstimated);
    }

    /**
     * Test fillUpGapMeasurements with empty data
     */
    public function test_fill_up_gap_measurements_empty_data()
    {
        $concrete = new MeasurementsRepoTraitConcrete();

        $datesRange = ['2025-01-01'];
        $jsonData = [];

        $result = $concrete->fillUpGapMeasurements($datesRange, $jsonData);

        // Should return empty or minimal array
        $this->assertIsArray($result);
    }

    /**
     * Test fillUpGapMeasurements with multiple dates
     */
    public function test_fill_up_gap_measurements_multiple_dates()
    {
        $concrete = new MeasurementsRepoTraitConcrete();

        $datesRange = ['2025-01-01', '2025-01-02'];
        $jsonData = [
            (object)[
                'fecha' => '2025-01-01',
                'hora' => '00:05:00',
                'kwhe' => 10.0,
                'kwhr' => 8.0,
                'kvarh' => 5.0,
                'tipo' => 'R'
            ],
            (object)[
                'fecha' => '2025-01-02',
                'hora' => '00:05:00',
                'kwhe' => 12.0,
                'kwhr' => 10.0,
                'kvarh' => 7.0,
                'tipo' => 'R'
            ]
        ];

        $result = $concrete->fillUpGapMeasurements($datesRange, $jsonData);

        // Should have 575 intervals for 2 days (2 * 288 - 1 from array_shift)
        $this->assertCount(575, $result);
    }

    /**
     * Test mergeDataArrays with multiple RPUs
     */
    public function test_merge_data_arrays_multiple_rpus()
    {
        $concrete = new MeasurementsRepoTraitConcrete();

        $demandaRoladaHourlyMax = [
            'rpu1' => ['2025-01-01' => ['01' => 150.5]],
            'rpu2' => ['2025-01-01' => ['01' => 200.0]]
        ];

        $measurementskwheHourlySums = [
            'rpu1' => ['2025-01-01' => ['01' => 100.0]],
            'rpu2' => ['2025-01-01' => ['01' => 120.0]]
        ];

        $measurementKwhrHourlySums = [
            'rpu1' => ['2025-01-01' => ['01' => 80.0]],
            'rpu2' => ['2025-01-01' => ['01' => 90.0]]
        ];

        $measurementKvarHourlySums = [
            'rpu1' => ['2025-01-01' => ['01' => 50.0]],
            'rpu2' => ['2025-01-01' => ['01' => 60.0]]
        ];

        $measurementsTypeAndMissingDatesHourly = [
            'rpu1' => ['2025-01-01' => ['01' => ['type' => 'A', 'missingRecords' => null]]],
            'rpu2' => ['2025-01-01' => ['01' => ['type' => 'B', 'missingRecords' => null]]]
        ];

        $result = $concrete->mergeDataArrays(
            $demandaRoladaHourlyMax,
            $measurementskwheHourlySums,
            $measurementKwhrHourlySums,
            $measurementKvarHourlySums,
            $measurementsTypeAndMissingDatesHourly
        );

        $this->assertCount(2, $result);
        $this->assertArrayHasKey('rpu1', $result);
        $this->assertArrayHasKey('rpu2', $result);
        $this->assertEquals(150.5, $result['rpu1']['2025-01-01']['01']['rolledDemand']);
        $this->assertEquals(200.0, $result['rpu2']['2025-01-01']['01']['rolledDemand']);
    }

    /**
     * Test mergeDataArrays with multiple dates
     */
    public function test_merge_data_arrays_multiple_dates()
    {
        $concrete = new MeasurementsRepoTraitConcrete();

        $demandaRoladaHourlyMax = [
            'rpu1' => [
                '2025-01-01' => ['01' => 150.5],
                '2025-01-02' => ['01' => 160.0]
            ]
        ];

        $measurementskwheHourlySums = [
            'rpu1' => [
                '2025-01-01' => ['01' => 100.0],
                '2025-01-02' => ['01' => 110.0]
            ]
        ];

        $measurementKwhrHourlySums = [
            'rpu1' => [
                '2025-01-01' => ['01' => 80.0],
                '2025-01-02' => ['01' => 85.0]
            ]
        ];

        $measurementKvarHourlySums = [
            'rpu1' => [
                '2025-01-01' => ['01' => 50.0],
                '2025-01-02' => ['01' => 55.0]
            ]
        ];

        $measurementsTypeAndMissingDatesHourly = [
            'rpu1' => [
                '2025-01-01' => ['01' => ['type' => 'A', 'missingRecords' => null]],
                '2025-01-02' => ['01' => ['type' => 'B', 'missingRecords' => null]]
            ]
        ];

        $result = $concrete->mergeDataArrays(
            $demandaRoladaHourlyMax,
            $measurementskwheHourlySums,
            $measurementKwhrHourlySums,
            $measurementKvarHourlySums,
            $measurementsTypeAndMissingDatesHourly
        );

        $this->assertCount(2, $result['rpu1']);
        $this->assertEquals(150.5, $result['rpu1']['2025-01-01']['01']['rolledDemand']);
        $this->assertEquals(160.0, $result['rpu1']['2025-01-02']['01']['rolledDemand']);
    }

    /**
     * Helper method to invoke protected methods
     */
    protected function invokeProtectedMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
