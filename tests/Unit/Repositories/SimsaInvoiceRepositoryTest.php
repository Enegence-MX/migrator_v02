<?php

namespace Tests\Unit\Repositories;

use Tests\TestCase;
use App\Http\Repositories\SimsaInvoiceRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Mockery;
use ReflectionClass;
use ReflectionMethod;
use stdClass;
use Exception;

class SimsaInvoiceRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Helper method to access protected methods using reflection
     */
    protected function invokeProtectedMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    /**
     * Helper method to get protected property value
     */
    protected function getProtectedProperty($object, $propertyName)
    {
        $reflection = new ReflectionClass(get_class($object));
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }

    /**
     * Test buildTeamIdClause with default team IDs (multiple)
     */
    public function test_build_team_id_clause_default_teams()
    {
        // Mock DB and Log facades
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');

        $result = $this->invokeProtectedMethod($repo, 'buildTeamIdClause');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('clause', $result);
        $this->assertArrayHasKey('params', $result);
        // Default teamIds is [93, 339, 454] (3 teams)
        $this->assertStringContainsString('IN', $result['clause']);
        $this->assertIsArray($result['params']);
        $this->assertCount(3, $result['params']);
    }

    /**
     * Test buildTeamIdClause with single team ID
     */
    public function test_build_team_id_clause_single_team()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');

        // Use reflection to set teamIds to single value
        $reflection = new ReflectionClass($repo);
        $property = $reflection->getProperty('teamIds');
        $property->setAccessible(true);
        $property->setValue($repo, [500]);

        $result = $this->invokeProtectedMethod($repo, 'buildTeamIdClause');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('clause', $result);
        $this->assertArrayHasKey('params', $result);
        $this->assertEquals('i.team_id = ?', $result['clause']);
        $this->assertCount(1, $result['params']);
    }

    /**
     * Test buildTeamIdClause with multiple team IDs
     */
    public function test_build_team_id_clause_multiple_teams()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');

        // Use reflection to set teamIds to an array with multiple values
        $reflection = new ReflectionClass($repo);
        $property = $reflection->getProperty('teamIds');
        $property->setAccessible(true);
        $property->setValue($repo, [500, 508, 516]);

        $result = $this->invokeProtectedMethod($repo, 'buildTeamIdClause');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('clause', $result);
        $this->assertArrayHasKey('params', $result);
        $this->assertStringContainsString('IN', $result['clause']);
        $this->assertCount(3, $result['params']);
    }

    /**
     * Test getTeamIds returns teamIds property
     */
    public function test_get_team_ids()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');

        $result = $this->invokeProtectedMethod($repo, 'getTeamIds');

        $this->assertIsArray($result);
    }

    /**
     * Test mapSCInvoiceData transforms source invoice correctly
     */
    public function test_map_sc_invoice_data()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');

        $sourceInvoice = new stdClass();
        $sourceInvoice->team_id = 500;
        $sourceInvoice->id = 12345;
        $sourceInvoice->contractId = 'CON-001';
        $sourceInvoice->contractCalculation = 'CALC-001';
        $sourceInvoice->startDateParam = '2025-01-01';
        $sourceInvoice->endDateParam = '2025-01-31';
        $sourceInvoice->quantityEnergySectionSum = 1000.50;
        $sourceInvoice->energyAmount = 5000.00;
        $sourceInvoice->capacityAmount = 2000.00;
        $sourceInvoice->cleanEnergyCertificateAmount = 500.00;
        $sourceInvoice->subtotal = 7500.00;
        $sourceInvoice->iva = 1200.00;
        $sourceInvoice->total = 8700.00;
        $sourceInvoice->uuid = 'UUID-12345';
        $sourceInvoice->invoiceNumber = 'INV-001';

        $result = $this->invokeProtectedMethod($repo, 'mapSCInvoiceData', [$sourceInvoice]);

        $this->assertIsArray($result);
        $this->assertEquals(500, $result['TEAMID']);
        $this->assertEquals(12345, $result['FACTURAID']);
        $this->assertEquals('CON-001', $result['CONTRATO']);
        $this->assertEquals('CALC-001', $result['CALCULO']);
        $this->assertEquals('2025-01-01', $result['FECHAINICIO']);
        $this->assertEquals('2025-01-31', $result['FECHAFIN']);
        $this->assertEquals(1000.50, $result['CANTIDADENERGIA']);
        $this->assertEquals(5000.00, $result['ENERGIA']);
        $this->assertEquals(2000.00, $result['POTENCIA']);
        $this->assertEquals(500.00, $result['CELS']);
        $this->assertEquals(7500.00, $result['SUBTOTAL']);
        $this->assertEquals(1200.00, $result['IVA']);
        $this->assertEquals(8700.00, $result['TOTAL']);
        $this->assertEquals('UUID-12345', $result['UUID']);
        $this->assertEquals('INV-001', $result['FACTURA']);
        $this->assertArrayHasKey('FECHAENVIO', $result);
        $this->assertArrayHasKey('FECHAACTUALIZACION', $result);
    }

    /**
     * Test mapSCInvoiceData handles null values
     */
    public function test_map_sc_invoice_data_with_nulls()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');

        $sourceInvoice = new stdClass();
        $sourceInvoice->team_id = 500;
        $sourceInvoice->id = 12345;

        $result = $this->invokeProtectedMethod($repo, 'mapSCInvoiceData', [$sourceInvoice]);

        $this->assertIsArray($result);
        $this->assertEquals(500, $result['TEAMID']);
        $this->assertEquals(12345, $result['FACTURAID']);
        $this->assertNull($result['CONTRATO']);
        $this->assertNull($result['CALCULO']);
        $this->assertNull($result['ENERGIA']);
        $this->assertNull($result['UUID']);
    }

    /**
     * Test mapMEMInvoiceData transforms source invoice correctly
     */
    public function test_map_mem_invoice_data()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');

        $sourceInvoice = new stdClass();
        $sourceInvoice->team_id = 508;
        $sourceInvoice->id = 67890;
        $sourceInvoice->uuid = 'MEM-UUID-123';
        $sourceInvoice->fechaPago = '2025-01-15';
        $sourceInvoice->metodoPago = 'TRANSFERENCIA';
        $sourceInvoice->uuidComplemento = 'COMP-UUID-456';
        $sourceInvoice->tipo_factura = 'Pago';
        $sourceInvoice->tipoDocumento = 'COMPLEMENTO';

        $result = $this->invokeProtectedMethod($repo, 'mapMEMInvoiceData', [$sourceInvoice]);

        $this->assertIsArray($result);
        $this->assertEquals(508, $result['TEAMID']);
        $this->assertEquals(67890, $result['FACTURAID']);
        $this->assertEquals('MEM-UUID-123', $result['UUID']);
        $this->assertEquals('2025-01-15', $result['FECHAPAGO']);
        $this->assertEquals('TRANSFERENCIA', $result['METODOPAGO']);
        $this->assertEquals('COMP-UUID-456', $result['UUIDCOMPLEMENTO']);
        $this->assertEquals('Pago', $result['TIPODOCUMENTO']);
    }

    /**
     * Test mapMEMInvoiceData handles null values
     */
    public function test_map_mem_invoice_data_with_nulls()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');

        $sourceInvoice = new stdClass();
        $sourceInvoice->team_id = 508;
        $sourceInvoice->id = 67890;
        $sourceInvoice->tipo_factura = 'Factura'; // Required field

        $result = $this->invokeProtectedMethod($repo, 'mapMEMInvoiceData', [$sourceInvoice]);

        $this->assertIsArray($result);
        $this->assertEquals(508, $result['TEAMID']);
        $this->assertEquals(67890, $result['FACTURAID']);
        $this->assertNull($result['UUID']);
        $this->assertNull($result['FECHAPAGO']);
        $this->assertNull($result['METODOPAGO']);
        // When tipo_factura != 'Pago', TIPODOCUMENTO should use tipoDocumento ?? null
        $this->assertNull($result['TIPODOCUMENTO']);
    }

    /**
     * Test constructor sets properties correctly
     */
    public function test_constructor_sets_properties()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');

        $startDate = $this->getProtectedProperty($repo, 'startDate');
        $endDate = $this->getProtectedProperty($repo, 'endDate');

        $this->assertEquals('2025-01-01', $startDate);
        // Constructor appends time to endDate
        $this->assertEquals('2025-01-31 23:59:59', $endDate);
    }

    /**
     * Test constructor with null dates sets defaults
     */
    public function test_constructor_with_null_dates()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);

        $repo = new SimsaInvoiceRepository();

        $startDate = $this->getProtectedProperty($repo, 'startDate');
        $endDate = $this->getProtectedProperty($repo, 'endDate');

        $this->assertNotNull($startDate);
        $this->assertNotNull($endDate);
    }

    // Commented out temporarily - complex DB mocking with many required fields
    // /**
    //  * Test getInvoiceCount with mocked DB
    //  */
    // public function test_get_invoice_count()
    // {
    //     $mockConnection = Mockery::mock();
    //     $mockConnection->shouldReceive('select')->andReturn([
    //         (object)['total' => 50]
    //     ]);
    //
    //     DB::shouldReceive('connection')->andReturn($mockConnection);
    //     Log::shouldReceive('channel')->andReturnSelf();
    //     Log::shouldReceive('info')->andReturn(true);
    //     Log::shouldReceive('error')->andReturn(true);
    //
    //     $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
    //     $result = $repo->getInvoiceCount();
    //
    //     $this->assertEquals(50, $result);
    // }

    // /**
    //  * Test getTargetInvoiceCount with mocked DB
    //  */
    // public function test_get_target_invoice_count()
    // {
    //     $mockTargetConnection = Mockery::mock();
    //     $mockTargetConnection->shouldReceive('table')->andReturnSelf();
    //     $mockTargetConnection->shouldReceive('count')->andReturn(45);
    //
    //     $mockSourceConnection = Mockery::mock();
    //
    //     DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
    //     DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
    //     Log::shouldReceive('channel')->andReturnSelf();
    //     Log::shouldReceive('info')->andReturn(true);
    //     Log::shouldReceive('error')->andReturn(true);
    //
    //     $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
    //     $result = $repo->getTargetInvoiceCount();
    //
    //     $this->assertEquals(45, $result);
    // }

    // /**
    //  * Test upsertSCInvoiceData insert operation
    //  */
    // public function test_upsert_sc_invoice_data_insert()
    // {
    //     $mockConnection = Mockery::mock();
    //     $mockConnection->shouldReceive('table')->andReturnSelf();
    //     $mockConnection->shouldReceive('where')->andReturnSelf();
    //     $mockConnection->shouldReceive('first')->andReturn(null);
    //     $mockConnection->shouldReceive('insert')->andReturn(true);
    //
    //     DB::shouldReceive('connection')->andReturn($mockConnection);
    //     Log::shouldReceive('channel')->andReturnSelf();
    //     Log::shouldReceive('info')->andReturn(true);
    //     Log::shouldReceive('debug')->andReturn(true);
    //     Log::shouldReceive('error')->andReturn(true);
    //
    //     $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
    //
    //     $invoiceData = [
    //         'TEAMID' => 93,
    //         'FACTURAID' => 12345,
    //         'UUID' => 'UUID-001',
    //         'FACTURA' => 'FAC-001',
    //         'CONTRATO' => 'CON-001',
    //         'TOTAL' => 1000.00
    //     ];
    //
    //     $result = $this->invokeProtectedMethod($repo, 'upsertSCInvoiceData', [$invoiceData]);
    //
    //     $this->assertEquals('inserted', $result);
    // }

    // /**
    //  * Test upsertSCInvoiceData update operation
    //  */
    // public function test_upsert_sc_invoice_data_update()
    // {
    //     $existing = new stdClass();
    //     $existing->ID = 999;
    //     $existing->FACTURAID = 12345;
    //
    //     $mockConnection = Mockery::mock();
    //     $mockConnection->shouldReceive('table')->andReturnSelf();
    //     $mockConnection->shouldReceive('where')->andReturnSelf();
    //     $mockConnection->shouldReceive('first')->andReturn($existing);
    //     $mockConnection->shouldReceive('update')->andReturn(true);
    //
    //     DB::shouldReceive('connection')->andReturn($mockConnection);
    //     Log::shouldReceive('channel')->andReturnSelf();
    //     Log::shouldReceive('info')->andReturn(true);
    //     Log::shouldReceive('debug')->andReturn(true);
    //     Log::shouldReceive('error')->andReturn(true);
    //
    //     $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
    //
    //     $invoiceData = [
    //         'TEAMID' => 93,
    //         'FACTURAID' => 12345,
    //         'UUID' => 'UUID-001',
    //         'FACTURA' => 'FAC-001',
    //         'CONTRATO' => 'CON-001',
    //         'TOTAL' => 1000.00
    //     ];
    //
    //     $result = $this->invokeProtectedMethod($repo, 'upsertSCInvoiceData', [$invoiceData]);
    //
    //     $this->assertEquals('updated', $result);
    // }

    /**
     * Test upsertMEMInvoiceData insert operation
     */
    public function test_upsert_mem_invoice_data_insert()
    {
        $mockTargetConnection = Mockery::mock();
        $mockTargetConnection->shouldReceive('table')->andReturnSelf();
        $mockTargetConnection->shouldReceive('where')->andReturnSelf();
        $mockTargetConnection->shouldReceive('first')->andReturn(null);
        $mockTargetConnection->shouldReceive('insert')->andReturn(true);

        $mockSourceConnection = Mockery::mock();

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('debug')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');

        $invoiceData = [
            'TEAMID' => 93,
            'FACTURAID' => 67890,
            'UUID' => 'UUID-123',
            'FACTURA' => 'MEM-001'
        ];

        $result = $this->invokeProtectedMethod($repo, 'upsertMEMInvoiceData', [$invoiceData]);

        $this->assertEquals('inserted', $result);
    }

    /**
     * Test upsertMEMInvoiceData update operation
     */
    public function test_upsert_mem_invoice_data_update()
    {
        $existing = new stdClass();
        $existing->ID = 888;
        $existing->FACTURAID = 67890;

        $mockTargetConnection = Mockery::mock();
        $mockTargetConnection->shouldReceive('table')->andReturnSelf();
        $mockTargetConnection->shouldReceive('where')->andReturnSelf();
        $mockTargetConnection->shouldReceive('first')->andReturn($existing);
        $mockTargetConnection->shouldReceive('update')->andReturn(true);

        $mockSourceConnection = Mockery::mock();

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('debug')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');

        $invoiceData = [
            'TEAMID' => 93,
            'FACTURAID' => 67890,
            'UUID' => 'UUID-123',
            'FACTURA' => 'MEM-001',
            'FECHAENVIO' => '2025-01-01'
        ];

        $result = $this->invokeProtectedMethod($repo, 'upsertMEMInvoiceData', [$invoiceData]);

        $this->assertEquals('updated', $result);
    }

    /**
     * Test upsertSCInvoiceData insert operation
     */
    public function test_upsert_sc_invoice_data_insert()
    {
        $mockTargetConnection = Mockery::mock();
        $mockTargetConnection->shouldReceive('table')->andReturnSelf();
        $mockTargetConnection->shouldReceive('where')->andReturnSelf();
        $mockTargetConnection->shouldReceive('first')->andReturn(null);
        $mockTargetConnection->shouldReceive('insert')->andReturn(true);

        $mockSourceConnection = Mockery::mock();

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('debug')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');

        $invoiceData = [
            'TEAMID' => 93,
            'FACTURAID' => 12345,
            'UUID' => 'UUID-001',
            'FACTURA' => 'FAC-001',
            'CONTRATO' => 'CON-001',
            'TOTAL' => 1000.00
        ];

        $result = $this->invokeProtectedMethod($repo, 'upsertSCInvoiceData', [$invoiceData]);

        $this->assertEquals('inserted', $result);
    }

    /**
     * Test upsertSCInvoiceData update operation
     */
    public function test_upsert_sc_invoice_data_update()
    {
        $existing = new stdClass();
        $existing->ID = 999;
        $existing->FACTURAID = 12345;

        $mockTargetConnection = Mockery::mock();
        $mockTargetConnection->shouldReceive('table')->andReturnSelf();
        $mockTargetConnection->shouldReceive('where')->andReturnSelf();
        $mockTargetConnection->shouldReceive('first')->andReturn($existing);
        $mockTargetConnection->shouldReceive('update')->andReturn(true);

        $mockSourceConnection = Mockery::mock();

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('debug')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');

        $invoiceData = [
            'TEAMID' => 93,
            'FACTURAID' => 12345,
            'UUID' => 'UUID-001',
            'FACTURA' => 'FAC-001',
            'CONTRATO' => 'CON-001',
            'TOTAL' => 1000.00,
            'FECHAENVIO' => '2025-01-01'
        ];

        $result = $this->invokeProtectedMethod($repo, 'upsertSCInvoiceData', [$invoiceData]);

        $this->assertEquals('updated', $result);
    }

    /**
     * Test removeOldInvoices deletes records successfully
     */
    public function test_remove_old_invoices_success()
    {
        $mockTargetConnection = Mockery::mock();
        $mockTargetConnection->shouldReceive('table')->with('simsa_sc_invoices')->andReturnSelf();
        $mockTargetConnection->shouldReceive('where')->andReturnSelf();
        $mockTargetConnection->shouldReceive('delete')->andReturn(5);

        $mockTargetConnection->shouldReceive('table')->with('simsa_mem_invoices')->andReturnSelf();

        $mockSourceConnection = Mockery::mock();

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
        $result = $repo->removeOldInvoices();

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(10, $result['total_deleted']);
    }

    /**
     * Test fetchSCInvoicesFromSource with mocked DB
     */
    public function test_fetch_sc_invoices_from_source()
    {
        $invoice = new stdClass();
        $invoice->id = 1;
        $invoice->team_id = 93;
        $invoice->uuid = 'UUID-001';

        $mockSourceConnection = Mockery::mock();
        $mockSourceConnection->shouldReceive('select')->andReturn([$invoice]);

        $mockTargetConnection = Mockery::mock();

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('debug')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
        $result = $this->invokeProtectedMethod($repo, 'fetchSCInvoicesFromSource');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /**
     * Test fetchMEMInvoicesFromSource with mocked DB
     */
    public function test_fetch_mem_invoices_from_source()
    {
        $invoice = new stdClass();
        $invoice->id = 2;
        $invoice->team_id = 93;
        $invoice->uuid = 'MEM-UUID-001';

        $mockSourceConnection = Mockery::mock();
        $mockSourceConnection->shouldReceive('select')->andReturn([$invoice]);

        $mockTargetConnection = Mockery::mock();

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('debug')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
        $result = $this->invokeProtectedMethod($repo, 'fetchMEMInvoicesFromSource');

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /**
     * Test getInvoiceCount with mocked DB
     */
    public function test_get_invoice_count()
    {
        $mockSourceConnection = Mockery::mock();
        $mockSourceConnection->shouldReceive('select')->andReturn([
            (object)['total' => 50]
        ]);

        $mockTargetConnection = Mockery::mock();

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
        $result = $repo->getInvoiceCount();

        $this->assertEquals(50, $result);
    }

    /**
     * Test getTargetInvoiceCount with mocked DB
     */
    public function test_get_target_invoice_count()
    {
        $mockTargetConnection = Mockery::mock();
        $mockTargetConnection->shouldReceive('table')->andReturnSelf();
        $mockTargetConnection->shouldReceive('count')->andReturn(45);

        $mockSourceConnection = Mockery::mock();

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
        $result = $repo->getTargetInvoiceCount();

        $this->assertEquals(45, $result);
    }

    /**
     * Test getF ACTURAID List retrieves data
     */
    public function test_get_facturaid_list()
    {
        $row1 = new stdClass();
        $row1->FACTURAID = 123;
        $row1->TeamId = 93;

        $row2 = new stdClass();
        $row2->FACTURAID = 456;
        $row2->TeamId = 93;

        $mockTargetConnection = Mockery::mock();
        $mockTargetConnection->shouldReceive('table')->andReturnSelf();
        $mockTargetConnection->shouldReceive('select')->andReturnSelf();
        $mockTargetConnection->shouldReceive('whereNotNull')->andReturnSelf();
        $mockTargetConnection->shouldReceive('whereNull')->andReturnSelf();
        $mockTargetConnection->shouldReceive('get')->andReturn([$row1, $row2]);

        $mockSourceConnection = Mockery::mock();

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('debug')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
        $result = $this->invokeProtectedMethod($repo, 'getFACTURAIDList');

        $this->assertIsArray($result);
        $this->assertArrayHasKey(93, $result);
        $this->assertCount(2, $result[93]);
    }

    /**
     * Test getFACTURAIDListMEM retrieves MEM data
     */
    public function test_get_facturaid_list_mem()
    {
        $row1 = new stdClass();
        $row1->FACTURAID = 789;
        $row1->FUF = 'FUF-001';
        $row1->TeamId = 93;

        $mockTargetConnection = Mockery::mock();
        $mockTargetConnection->shouldReceive('table')->andReturnSelf();
        $mockTargetConnection->shouldReceive('select')->andReturnSelf();
        $mockTargetConnection->shouldReceive('whereNotNull')->andReturnSelf();
        $mockTargetConnection->shouldReceive('whereNull')->andReturnSelf();
        $mockTargetConnection->shouldReceive('get')->andReturn([$row1]);

        $mockSourceConnection = Mockery::mock();

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('debug')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
        $result = $this->invokeProtectedMethod($repo, 'getFACTURAIDListMEM');

        $this->assertIsArray($result);
        $this->assertArrayHasKey(93, $result);
        $this->assertArrayHasKey('ids', $result[93]);
        $this->assertArrayHasKey('fufs', $result[93]);
    }

    /**
     * Test getApiToken success
     */
    public function test_get_api_token_success()
    {
        Http::fake([
            '*' => Http::response(['token' => 'test-token-123'], 200)
        ]);

        $mockTargetConnection = Mockery::mock();
        $mockSourceConnection = Mockery::mock();

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('debug')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
        $result = $this->invokeProtectedMethod($repo, 'getApiToken', [93]);

        $this->assertEquals('test-token-123', $result);
    }

    /**
     * Test getApiToken failure no credentials
     */
    public function test_get_api_token_no_credentials()
    {
        $mockTargetConnection = Mockery::mock();
        $mockSourceConnection = Mockery::mock();

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
        $result = $this->invokeProtectedMethod($repo, 'getApiToken', [999]);

        $this->assertNull($result);
    }

    /**
     * Test getApiToken failure HTTP error
     */
    public function test_get_api_token_http_failure()
    {
        Http::fake([
            '*' => Http::response([], 401)
        ]);

        $mockTargetConnection = Mockery::mock();
        $mockSourceConnection = Mockery::mock();

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('debug')->andReturn(true);
        Log::shouldReceive('error')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
        $result = $this->invokeProtectedMethod($repo, 'getApiToken', [93]);

        $this->assertNull($result);
    }

    /**
     * Test downloadInvoices success
     */
    public function test_download_invoices_success()
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    ['invoiceId' => 123, 'content' => 'base64content1'],
                    ['invoiceId' => 456, 'content' => 'base64content2']
                ]
            ], 200)
        ]);

        $mockTargetConnection = Mockery::mock();
        $mockTargetConnection->shouldReceive('table')->andReturnSelf();
        $mockTargetConnection->shouldReceive('where')->andReturnSelf();
        $mockTargetConnection->shouldReceive('update')->andReturn(1);

        $mockSourceConnection = Mockery::mock();

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('debug')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
        $result = $this->invokeProtectedMethod($repo, 'downloadInvoices', ['test-token', [123, 456]]);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['updated_count']);
    }

    /**
     * Test downloadMEMInvoices success
     */
    public function test_download_mem_invoices_success()
    {
        Http::fake([
            '*' => Http::response([
                'data' => [
                    ['fuf' => 'FUF-001', 'content' => 'base64memcontent']
                ]
            ], 200)
        ]);

        $mockTargetConnection = Mockery::mock();
        $mockTargetConnection->shouldReceive('table')->andReturnSelf();
        $mockTargetConnection->shouldReceive('where')->andReturnSelf();
        $mockTargetConnection->shouldReceive('update')->andReturn(1);

        $mockSourceConnection = Mockery::mock();

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('debug')->andReturn(true);
        Log::shouldReceive('warning')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
        $result = $this->invokeProtectedMethod($repo, 'downloadMEMInvoices', ['test-token', [789], ['FUF-001']]);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['updated_count']);
    }

    /**
     * Test synchronizeSCInvoices with full flow
     */
    public function test_synchronize_sc_invoices()
    {
        $invoice = new stdClass();
        $invoice->id = 100;
        $invoice->team_id = 93;
        $invoice->uuid = 'SC-UUID-100';
        $invoice->invoiceNumber = 'SC-INV-100';

        $mockSourceConnection = Mockery::mock();
        $mockSourceConnection->shouldReceive('select')->andReturn([$invoice]);

        $mockTargetConnection = Mockery::mock();
        $mockTargetConnection->shouldReceive('table')->andReturnSelf();
        $mockTargetConnection->shouldReceive('where')->andReturnSelf();
        $mockTargetConnection->shouldReceive('first')->andReturn(null);
        $mockTargetConnection->shouldReceive('insert')->andReturn(true);
        $mockTargetConnection->shouldReceive('select')->andReturnSelf();
        $mockTargetConnection->shouldReceive('whereNotNull')->andReturnSelf();
        $mockTargetConnection->shouldReceive('whereNull')->andReturnSelf();
        $mockTargetConnection->shouldReceive('get')->andReturn([]);

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('debug')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
        $result = $this->invokeProtectedMethod($repo, 'synchronizeSCInvoices');

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['total_fetched']);
        $this->assertEquals(1, $result['inserted']);
    }

    /**
     * Test synchronizeMEMInvoices with full flow
     */
    public function test_synchronize_mem_invoices()
    {
        $invoice = new stdClass();
        $invoice->id = 200;
        $invoice->team_id = 93;
        $invoice->uuid = 'MEM-UUID-200';
        $invoice->invoiceNumber = 'MEM-INV-200';
        $invoice->tipo_factura = 'Factura';

        $mockSourceConnection = Mockery::mock();
        $mockSourceConnection->shouldReceive('select')->andReturn([$invoice]);

        $mockTargetConnection = Mockery::mock();
        $mockTargetConnection->shouldReceive('table')->andReturnSelf();
        $mockTargetConnection->shouldReceive('where')->andReturnSelf();
        $mockTargetConnection->shouldReceive('first')->andReturn(null);
        $mockTargetConnection->shouldReceive('insert')->andReturn(true);
        $mockTargetConnection->shouldReceive('select')->andReturnSelf();
        $mockTargetConnection->shouldReceive('whereNotNull')->andReturnSelf();
        $mockTargetConnection->shouldReceive('whereNull')->andReturnSelf();
        $mockTargetConnection->shouldReceive('get')->andReturn([]);

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('debug')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
        $result = $this->invokeProtectedMethod($repo, 'synchronizeMEMInvoices');

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['total_fetched']);
        $this->assertEquals(1, $result['inserted']);
    }

    /**
     * Test synchronizeInvoices combines SC and MEM results
     */
    public function test_synchronize_invoices()
    {
        $scInvoice = new stdClass();
        $scInvoice->id = 300;
        $scInvoice->team_id = 93;
        $scInvoice->uuid = 'SC-UUID-300';
        $scInvoice->invoiceNumber = 'SC-INV-300';

        $memInvoice = new stdClass();
        $memInvoice->id = 400;
        $memInvoice->team_id = 93;
        $memInvoice->uuid = 'MEM-UUID-400';
        $memInvoice->invoiceNumber = 'MEM-INV-400';
        $memInvoice->tipo_factura = 'Factura';

        $mockSourceConnection = Mockery::mock();
        $mockSourceConnection->shouldReceive('select')->times(2)->andReturn([$scInvoice], [$memInvoice]);

        $mockTargetConnection = Mockery::mock();
        $mockTargetConnection->shouldReceive('table')->andReturnSelf();
        $mockTargetConnection->shouldReceive('where')->andReturnSelf();
        $mockTargetConnection->shouldReceive('first')->andReturn(null);
        $mockTargetConnection->shouldReceive('insert')->andReturn(true);
        $mockTargetConnection->shouldReceive('select')->andReturnSelf();
        $mockTargetConnection->shouldReceive('whereNotNull')->andReturnSelf();
        $mockTargetConnection->shouldReceive('whereNull')->andReturnSelf();
        $mockTargetConnection->shouldReceive('get')->andReturn([]);

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Log::shouldReceive('debug')->andReturn(true);

        $repo = new SimsaInvoiceRepository('2025-01-01', '2025-01-31');
        $result = $repo->synchronizeInvoices();

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['totals']['total_fetched']);
        $this->assertEquals(2, $result['totals']['inserted']);
    }
}
