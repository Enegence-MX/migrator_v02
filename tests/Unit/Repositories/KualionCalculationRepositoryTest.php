<?php

namespace Tests\Unit\Repositories;

use Tests\TestCase;
use App\Http\Repositories\KualionCalculationRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Mockery;
use ReflectionClass;
use DateTime;
use Exception;
use stdClass;

class KualionCalculationRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper method to access private methods using reflection
     */
    protected function invokePrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Test formatCsvValue with null value
     */
    public function test_format_csv_value_with_null()
    {
        $repo = new KualionCalculationRepository();
        $result = $this->invokePrivateMethod($repo, 'formatCsvValue', [null]);

        $this->assertEquals('', $result);
    }

    /**
     * Test formatCsvValue with DateTime object
     */
    public function test_format_csv_value_with_datetime()
    {
        $repo = new KualionCalculationRepository();
        $dateTime = new DateTime('2025-01-15 10:30:45.123456');
        $result = $this->invokePrivateMethod($repo, 'formatCsvValue', [$dateTime]);

        $this->assertStringContainsString('2025-01-15', $result);
        $this->assertStringContainsString('10:30:45', $result);
    }

    /**
     * Test formatCsvValue with string value
     */
    public function test_format_csv_value_with_string()
    {
        $repo = new KualionCalculationRepository();
        $result = $this->invokePrivateMethod($repo, 'formatCsvValue', ['test string']);

        $this->assertEquals('test string', $result);
    }

    /**
     * Test formatCsvValue with numeric value
     */
    public function test_format_csv_value_with_number()
    {
        $repo = new KualionCalculationRepository();
        $result = $this->invokePrivateMethod($repo, 'formatCsvValue', [12345]);

        $this->assertEquals('12345', $result);
    }

    /**
     * Test formatCsvValue with float value
     */
    public function test_format_csv_value_with_float()
    {
        $repo = new KualionCalculationRepository();
        $result = $this->invokePrivateMethod($repo, 'formatCsvValue', [123.45]);

        $this->assertEquals('123.45', $result);
    }

    /**
     * Test formatCsvValue with boolean value
     */
    public function test_format_csv_value_with_boolean()
    {
        $repo = new KualionCalculationRepository();
        $result = $this->invokePrivateMethod($repo, 'formatCsvValue', [true]);

        $this->assertEquals('1', $result);
    }

    /**
     * Test getCalulationConceptsReport with mocked database
     */
    public function test_get_calulation_concepts_report()
    {
        // Mock the cloud connection
        $mockCloudConnection = Mockery::mock();

        // Mock the select query for contract calculations
        $contractCalculation = new stdClass();
        $contractCalculation->contractNumber = 'CONT-001';
        $contractCalculation->contractCalculationNumber = 'CALC-001';
        $contractCalculation->startDateParam = '2025-01-01';
        $contractCalculation->endDateParam = '2025-01-31';
        $contractCalculation->centrosDeCargaParam = 'CC-001';
        $contractCalculation->centralesElectricasParam = 1;
        $contractCalculation->name = 'Test Calculation';
        $contractCalculation->calculationResultSection = json_encode([
            [
                'name' => 'Section 1',
                'concepts' => [
                    [
                        'componentType' => 'contractVariable',
                        'componentId' => 1,
                        'description' => 'Test Concept',
                        'cantidad' => 100,
                        'valorUnitario' => '50.00',
                        'unidad' => 'kWh',
                        'total' => '5000.00'
                    ]
                ]
            ]
        ]);
        $contractCalculation->calculationResultSectionByCE = null;
        $contractCalculation->contractId = 1;
        $contractCalculation->created_at = '2025-01-01 00:00:00';
        $contractCalculation->updated_at = '2025-01-01 00:00:00';

        $mockCloudConnection->shouldReceive('select')->andReturn([$contractCalculation]);

        // Mock table queries for contractVariables, algorithms, equations, primary_components
        $mockCloudConnection->shouldReceive('table')->with('contractVariables')->andReturnSelf();
        $mockCloudConnection->shouldReceive('table')->with('algorithms')->andReturnSelf();
        $mockCloudConnection->shouldReceive('table')->with('equations')->andReturnSelf();
        $mockCloudConnection->shouldReceive('table')->with('primary_components')->andReturnSelf();
        $mockCloudConnection->shouldReceive('table')->with('contracts')->andReturnSelf();
        $mockCloudConnection->shouldReceive('where')->andReturnSelf();

        // Mock get() to return collections
        $contractVariable = new stdClass();
        $contractVariable->id = 1;
        $contractVariable->units = 'MXN';

        $contract = new stdClass();
        $contract->id = 1;
        $contract->currency = 'MXN';

        $mockCloudConnection->shouldReceive('get')->andReturn(
            collect([$contractVariable])->keyBy('id')
        );
        $mockCloudConnection->shouldReceive('keyBy')->andReturn(
            collect([$contractVariable])->keyBy('id')
        );

        // Mock the dev_2 connection
        $mockDev2Connection = Mockery::mock();
        $mockDev2Connection->shouldReceive('table')->with('centralElectrica')->andReturnSelf();
        $mockDev2Connection->shouldReceive('table')->with('reporteDeConceptosDeCaluloDeContrato')->andReturnSelf();
        $mockDev2Connection->shouldReceive('where')->andReturnSelf();
        $mockDev2Connection->shouldReceive('pluck')->andReturn(collect([1 => 'Central 1']));
        $mockDev2Connection->shouldReceive('statement')->andReturn(true);
        $mockDev2Connection->shouldReceive('insert')->andReturn(true);

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockCloudConnection);
        DB::shouldReceive('connection')->with('mysql_dev_2')->andReturn($mockDev2Connection);

        $repo = Mockery::mock(KualionCalculationRepository::class)->makePartial();
        $repo->shouldReceive('generateConceptsReportCsv')->andReturn(null);

        // Capture output
        ob_start();
        $repo->getCalulationConceptsReport();
        $output = ob_get_clean();

        $this->assertStringContainsString('all good', $output);
    }

    /**
     * Test getCalulationConceptsReport with Multidivisa currency
     */
    public function test_get_calulation_concepts_report_with_multidivisa()
    {
        // Mock the cloud connection
        $mockCloudConnection = Mockery::mock();

        // Mock the select query with Multidivisa contract
        $contractCalculation = new stdClass();
        $contractCalculation->contractNumber = 'CONT-002';
        $contractCalculation->contractCalculationNumber = 'CALC-002';
        $contractCalculation->startDateParam = '2025-01-01';
        $contractCalculation->endDateParam = '2025-01-31';
        $contractCalculation->centrosDeCargaParam = 'CC-002';
        $contractCalculation->centralesElectricasParam = 1;
        $contractCalculation->name = 'Test Multidivisa';
        $contractCalculation->calculationResultSection = null;
        $contractCalculation->calculationResultSectionByCE = json_encode([
            'MXN' => [
                [
                    'name' => 'Section MXN',
                    'concepts' => [
                        [
                            'componentType' => 'algorithmComponent',
                            'componentId' => 2,
                            'description' => 'MXN Concept',
                            'cantidad' => 50,
                            'valorUnitario' => '25.00',
                            'unidad' => 'kWh',
                            'total' => '1250.00'
                        ]
                    ]
                ]
            ],
            'USD' => [
                [
                    'name' => 'Section USD',
                    'concepts' => [
                        [
                            'componentType' => 'equationComponent',
                            'componentId' => 3,
                            'description' => 'USD Concept',
                            'cantidad' => 30,
                            'valorUnitario' => '50.00',
                            'unidad' => 'kWh',
                            'total' => '1500.00'
                        ]
                    ]
                ]
            ]
        ]);
        $contractCalculation->contractId = 2;
        $contractCalculation->created_at = '2025-01-01 00:00:00';
        $contractCalculation->updated_at = '2025-01-01 00:00:00';

        $mockCloudConnection->shouldReceive('select')->andReturn([$contractCalculation]);

        // Mock table queries - need to return proper collections for each table
        $contractVariable = new stdClass();
        $contractVariable->id = 1;
        $contractVariable->units = 'MXN';

        $algorithm = new stdClass();
        $algorithm->id = 2;
        $algorithm->units = 'USD';

        $equation = new stdClass();
        $equation->id = 3;
        $equation->units = 'MXN/USD';

        $primary = new stdClass();
        $primary->id = 4;
        $primary->units = 'kWh';

        $contract = new stdClass();
        $contract->id = 2;
        $contract->currency = 'Multidivisa';

        // Create collections for each table
        $contractVarsCollection = collect([1 => $contractVariable])->keyBy('id');
        $algorithmsCollection = collect([2 => $algorithm])->keyBy('id');
        $equationsCollection = collect([3 => $equation])->keyBy('id');
        $primaryCollection = collect([4 => $primary])->keyBy('id');
        $contractsCollection = collect([2 => $contract])->keyBy('id');

        $mockCloudConnection->shouldReceive('table')->with('contractVariables')->andReturnSelf();
        $mockCloudConnection->shouldReceive('table')->with('algorithms')->andReturnSelf();
        $mockCloudConnection->shouldReceive('table')->with('equations')->andReturnSelf();
        $mockCloudConnection->shouldReceive('table')->with('primary_components')->andReturnSelf();
        $mockCloudConnection->shouldReceive('table')->with('contracts')->andReturnSelf();
        $mockCloudConnection->shouldReceive('where')->andReturnSelf();
        $mockCloudConnection->shouldReceive('get')->andReturn(
            $contractVarsCollection,
            $algorithmsCollection,
            $equationsCollection,
            $primaryCollection,
            $contractsCollection
        );
        $mockCloudConnection->shouldReceive('keyBy')->andReturn(
            $contractVarsCollection,
            $algorithmsCollection,
            $equationsCollection,
            $primaryCollection,
            $contractsCollection
        );

        // Mock the dev_2 connection
        $mockDev2Connection = Mockery::mock();
        $mockDev2Connection->shouldReceive('table')->andReturnSelf();
        $mockDev2Connection->shouldReceive('where')->andReturnSelf();
        $mockDev2Connection->shouldReceive('pluck')->andReturn(collect([1 => 'Central 1']));
        $mockDev2Connection->shouldReceive('statement')->andReturn(true);
        $mockDev2Connection->shouldReceive('insert')->andReturn(true);

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockCloudConnection);
        DB::shouldReceive('connection')->with('mysql_dev_2')->andReturn($mockDev2Connection);

        $repo = Mockery::mock(KualionCalculationRepository::class)->makePartial();
        $repo->shouldReceive('generateConceptsReportCsv')->andReturn(null);

        // Capture output
        ob_start();
        $repo->getCalulationConceptsReport();
        $output = ob_get_clean();

        $this->assertStringContainsString('all good', $output);
    }

    /**
     * Test getCalulationComponentsReport with mocked database
     */
    public function test_get_calulation_components_report()
    {
        // Mock the cloud connection
        $mockCloudConnection = Mockery::mock();

        // Mock the select query
        $calculation = new stdClass();
        $calculation->contractNumber = 'CONT-001';
        $calculation->contractCalculationNumber = 'CALC-001';
        $calculation->centrosDeCargaParam = 'CC-001';
        $calculation->centralesElectricasParam = 1;
        $calculation->contractVariables = json_encode([
            '1' => [
                'contractVariableValue' => json_encode([
                    '2025_1' => 100
                ])
            ]
        ]);
        $calculation->startDateParam = '2025-01-15';
        $calculation->created_at = '2025-01-01 00:00:00';
        $calculation->updated_at = '2025-01-01 00:00:00';

        $mockCloudConnection->shouldReceive('select')->andReturn(collect([$calculation]));

        // Mock table query for contractVariables
        $mockCloudConnection->shouldReceive('table')->with('contractVariables')->andReturnSelf();
        $mockCloudConnection->shouldReceive('whereIn')->andReturnSelf();

        $variable = new stdClass();
        $variable->id = 1;
        $variable->name = 'Test Variable';
        $variable->units = 'kWh';

        $mockCloudConnection->shouldReceive('get')->andReturn(collect([$variable]));
        $mockCloudConnection->shouldReceive('keyBy')->andReturn(collect(['1' => $variable])->keyBy('id'));

        // Mock the dev_2 connection
        $mockDev2Connection = Mockery::mock();
        $mockDev2Connection->shouldReceive('table')->andReturnSelf();
        $mockDev2Connection->shouldReceive('where')->andReturnSelf();
        $mockDev2Connection->shouldReceive('pluck')->andReturn(collect([1 => 'Central 1']));
        $mockDev2Connection->shouldReceive('statement')->andReturn(true);
        $mockDev2Connection->shouldReceive('insert')->andReturn(true);

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockCloudConnection);
        DB::shouldReceive('connection')->with('mysql_dev_2')->andReturn($mockDev2Connection);

        $repo = Mockery::mock(KualionCalculationRepository::class)->makePartial();
        $repo->shouldReceive('generateComponentsReportCsv')->andReturn(null);

        // Capture output
        ob_start();
        $repo->getCalulationComponentsReport();
        $output = ob_get_clean();

        $this->assertStringContainsString('all good', $output);
    }

    /**
     * Test getCalulationComponentsReport with invalid JSON
     */
    public function test_get_calulation_components_report_with_invalid_json()
    {
        // Mock the cloud connection
        $mockCloudConnection = Mockery::mock();

        // Mock the select query with invalid JSON
        $calculation = new stdClass();
        $calculation->contractNumber = 'CONT-001';
        $calculation->contractCalculationNumber = 'CALC-001';
        $calculation->centrosDeCargaParam = 'CC-001';
        $calculation->centralesElectricasParam = 1;
        $calculation->contractVariables = '{invalid json}';
        $calculation->startDateParam = '2025-01-15';
        $calculation->created_at = '2025-01-01 00:00:00';
        $calculation->updated_at = '2025-01-01 00:00:00';

        $mockCloudConnection->shouldReceive('select')->andReturn(collect([$calculation]));

        // Mock the dev_2 connection
        $mockDev2Connection = Mockery::mock();
        $mockDev2Connection->shouldReceive('table')->andReturnSelf();
        $mockDev2Connection->shouldReceive('where')->andReturnSelf();
        $mockDev2Connection->shouldReceive('pluck')->andReturn(collect([1 => 'Central 1']));

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockCloudConnection);
        DB::shouldReceive('connection')->with('mysql_dev_2')->andReturn($mockDev2Connection);

        $repo = Mockery::mock(KualionCalculationRepository::class)->makePartial();
        $repo->shouldReceive('generateComponentsReportCsv')->andReturn(null);

        // Should skip invalid JSON and not insert anything
        ob_start();
        $repo->getCalulationComponentsReport();
        $output = ob_get_clean();

        $this->assertStringNotContainsString('doing truncate', $output);
    }

    /**
     * Test generateConceptsReportCsv with mocked file operations
     * Note: Skipping actual file operations since writeCsvFile is private
     */
    public function test_generate_concepts_report_csv()
    {
        // Mock DB connection
        $mockDev2Connection = Mockery::mock();

        $row = new stdClass();
        $row->contrato = 'CONT-001';
        $row->idDeCalculo = 'CALC-001';
        $row->fechaInicio = '2025-01-01';
        $row->fechaFin = '2025-01-31';
        $row->centrosDeCarga = 'CC-001';
        $row->NombreDelCalculo = 'Test Calc';
        $row->CategoriaOSeccion = 'Section 1';
        $row->InstrumentoOProducto = 'Product 1';
        $row->componentId = 1;
        $row->Cantidad = 100;
        $row->UnidadFact = 'kWh';
        $row->Monto = 5000;
        $row->Divisa = 'MXN';
        $row->IVA = 800;
        $row->centralesElectricas = 'Central 1';
        $row->componentType = 'contractVariable';
        $row->created_at = '2025-01-01 00:00:00';
        $row->updated_at = '2025-01-01 00:00:00';
        $row->report_created_at = '2025-01-01 00:00:00';
        $row->Precio = 50;
        $row->UnidadComponente = 'kWh';

        $mockDev2Connection->shouldReceive('select')->andReturn([$row]);

        DB::shouldReceive('connection')->with('mysql_dev_2')->andReturn($mockDev2Connection);

        // Mock the repository - can only mock public/protected methods
        $repo = Mockery::mock(KualionCalculationRepository::class)->makePartial();
        $repo->shouldReceive('executeOracleDataLoad')->andReturn(null);

        // Capture output - execution will fail at file operations but we verify DB query
        ob_start();
        try {
            $repo->generateConceptsReportCsv();
        } catch (\Exception $e) {
            // Expected to fail at file operations
        }
        $output = ob_get_clean();

        $this->assertStringContainsString('Generating Concepts Report CSV', $output);
        $this->assertStringContainsString('Total records: 1', $output);
    }

    /**
     * Test generateConceptsReportCsv with no records
     */
    public function test_generate_concepts_report_csv_no_records()
    {
        // Mock DB connection
        $mockDev2Connection = Mockery::mock();
        $mockDev2Connection->shouldReceive('select')->andReturn([]);

        DB::shouldReceive('connection')->with('mysql_dev_2')->andReturn($mockDev2Connection);

        $repo = new KualionCalculationRepository();

        // Capture output
        ob_start();
        $repo->generateConceptsReportCsv();
        $output = ob_get_clean();

        $this->assertStringContainsString('No records found', $output);
    }

    /**
     * Test generateComponentsReportCsv with mocked file operations
     * Note: Skipping actual file operations since writeCsvFile is private
     */
    public function test_generate_components_report_csv()
    {
        // Mock DB connection
        $mockDev2Connection = Mockery::mock();

        $row = new stdClass();
        $row->contrato = 'CONT-001';
        $row->idDeCalculo = 'CALC-001';
        $row->centrosDeCarga = 'CC-001';
        $row->nombreDeVariable = 'Variable 1';
        $row->idDeVariable = 1;
        $row->valor = 100;
        $row->unidades = 'kWh';
        $row->fechaInicio = '2025-01-01';
        $row->centralesElectricas = 'Central 1';
        $row->created_at = '2025-01-01 00:00:00';
        $row->updated_at = '2025-01-01 00:00:00';
        $row->report_created_at = '2025-01-01 00:00:00';

        $mockDev2Connection->shouldReceive('select')->andReturn([$row]);

        DB::shouldReceive('connection')->with('mysql_dev_2')->andReturn($mockDev2Connection);

        // Mock the repository - can only mock public/protected methods
        $repo = Mockery::mock(KualionCalculationRepository::class)->makePartial();
        $repo->shouldReceive('executeOracleDataLoad')->andReturn(null);

        // Capture output - execution will fail at file operations but we verify DB query
        ob_start();
        try {
            $repo->generateComponentsReportCsv();
        } catch (\Exception $e) {
            // Expected to fail at file operations
        }
        $output = ob_get_clean();

        $this->assertStringContainsString('Generating Components Report CSV', $output);
        $this->assertStringContainsString('Total records: 1', $output);
    }

    /**
     * Test generateComponentsReportCsv with no records
     */
    public function test_generate_components_report_csv_no_records()
    {
        // Mock DB connection
        $mockDev2Connection = Mockery::mock();
        $mockDev2Connection->shouldReceive('select')->andReturn([]);

        DB::shouldReceive('connection')->with('mysql_dev_2')->andReturn($mockDev2Connection);

        $repo = new KualionCalculationRepository();

        // Capture output
        ob_start();
        $repo->generateComponentsReportCsv();
        $output = ob_get_clean();

        $this->assertStringContainsString('No records found', $output);
    }

    /**
     * Test writeCsvFile with mocked file operations
     */
    public function test_write_csv_file()
    {
        $repo = new KualionCalculationRepository();

        $testData = [
            ['value1', 'value2', 'value3'],
            ['value4', 'value5', 'value6']
        ];

        // Use a temporary file for testing
        $tempFile = tempnam(sys_get_temp_dir(), 'test_csv_');

        try {
            $rowCount = $this->invokePrivateMethod($repo, 'writeCsvFile', [$tempFile, $testData, 3]);

            $this->assertEquals(2, $rowCount);
            $this->assertFileExists($tempFile);

            // Read back the file to verify content
            $content = file_get_contents($tempFile);
            $this->assertStringContainsString('value1', $content);
            $this->assertStringContainsString('value2', $content);
        } finally {
            // Clean up
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Test writeCsvFile with column count validation failure
     */
    public function test_write_csv_file_column_count_mismatch()
    {
        $repo = new KualionCalculationRepository();

        $testData = [
            ['value1', 'value2', 'value3'],
            ['value4', 'value5'] // Wrong column count
        ];

        $tempFile = tempnam(sys_get_temp_dir(), 'test_csv_');

        try {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('expected 3');

            $this->invokePrivateMethod($repo, 'writeCsvFile', [$tempFile, $testData, 3]);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Test writeCsvFile with invalid file path
     */
    public function test_write_csv_file_invalid_path()
    {
        $repo = new KualionCalculationRepository();

        $testData = [
            ['value1', 'value2']
        ];

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/Failed to open CSV file|Failed to open stream/');

        $this->invokePrivateMethod($repo, 'writeCsvFile', ['/invalid/path/file.csv', $testData]);
    }

    /**
     * Test generateConceptsCtlFile creates CTL file
     */
    public function test_generate_concepts_ctl_file()
    {
        $repo = new KualionCalculationRepository();

        $tempFile = tempnam(sys_get_temp_dir(), 'test_ctl_');

        try {
            $this->invokePrivateMethod($repo, 'generateConceptsCtlFile', [$tempFile, 'test.csv']);

            $this->assertFileExists($tempFile);

            $content = file_get_contents($tempFile);
            $this->assertStringContainsString('LOAD DATA', $content);
            $this->assertStringContainsString('test.csv', $content);
            $this->assertStringContainsString('REPORTEDECONCEPTOSDECALULODECONTRATO', $content);
            $this->assertStringContainsString('CONTRATO', $content);
            $this->assertStringContainsString('IDDECALCULO', $content);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Test generateComponentsCtlFile creates CTL file
     */
    public function test_generate_components_ctl_file()
    {
        $repo = new KualionCalculationRepository();

        $tempFile = tempnam(sys_get_temp_dir(), 'test_ctl_');

        try {
            $this->invokePrivateMethod($repo, 'generateComponentsCtlFile', [$tempFile, 'test.csv']);

            $this->assertFileExists($tempFile);

            $content = file_get_contents($tempFile);
            $this->assertStringContainsString('LOAD DATA', $content);
            $this->assertStringContainsString('test.csv', $content);
            $this->assertStringContainsString('REPORTEDEVARIABLESDECALCULODECONTRATO', $content);
            $this->assertStringContainsString('CONTRATO', $content);
            $this->assertStringContainsString('NOMBREDEVARIABLE', $content);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }
}
