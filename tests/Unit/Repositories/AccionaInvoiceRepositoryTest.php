<?php

namespace Tests\Unit\Repositories;

use Tests\TestCase;
use App\Http\Repositories\AccionaInvoiceRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Mockery;
use ReflectionClass;

class AccionaInvoiceRepositoryTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
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
     * Test constructor sets Acciona-specific properties
     */
    public function test_constructor_sets_acciona_properties()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Config::shouldReceive('get')->andReturn(null);

        $repo = new AccionaInvoiceRepository('2025-01-01', '2025-01-31');

        $teamIds = $this->getProtectedProperty($repo, 'teamIds');
        $companyName = $this->getProtectedProperty($repo, 'companyName');
        $loggerFileName = $this->getProtectedProperty($repo, 'loggerFileName');
        $invoceSCTableName = $this->getProtectedProperty($repo, 'invoceSCTableName');
        $invoceMEMTableName = $this->getProtectedProperty($repo, 'invoceMEMTableName');

        $this->assertEquals([500, 508, 516, 524], $teamIds);
        $this->assertEquals('ACCIONA', $companyName);
        $this->assertEquals('acciona_invoice_sync', $loggerFileName);
        $this->assertEquals('acciona_sc_invoices', $invoceSCTableName);
        $this->assertEquals('acciona_mem_invoices', $invoceMEMTableName);
    }

    /**
     * Test constructor sets all teamId credentials
     */
    public function test_constructor_sets_team_credentials()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_EMAIL')->andReturn('test@acciona.com');
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_PASSWORD')->andReturn('password123');
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_EMAIL_TEAMID_500', null)->andReturn('team500@acciona.com');
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_PASSWORD_TEAMID_500', null)->andReturn('pass500');
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_EMAIL_TEAMID_508', null)->andReturn('team508@acciona.com');
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_PASSWORD_TEAMID_508', null)->andReturn('pass508');
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_EMAIL_TEAMID_516', null)->andReturn('team516@acciona.com');
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_PASSWORD_TEAMID_516', null)->andReturn('pass516');
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_EMAIL_TEAMID_524', null)->andReturn('team524@acciona.com');
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_PASSWORD_TEAMID_524', null)->andReturn('pass524');
        Config::shouldReceive('get')->andReturn(null);

        $repo = new AccionaInvoiceRepository('2025-01-01', '2025-01-31');

        $teamCredentials = $this->getProtectedProperty($repo, 'teamCredentials');

        $this->assertArrayHasKey(500, $teamCredentials);
        $this->assertArrayHasKey(508, $teamCredentials);
        $this->assertArrayHasKey(516, $teamCredentials);
        $this->assertArrayHasKey(524, $teamCredentials);
        $this->assertEquals('team500@acciona.com', $teamCredentials[500]['email']);
        $this->assertEquals('pass500', $teamCredentials[500]['password']);
    }

    /**
     * Test constructor sets target connection to Acciona
     */
    public function test_constructor_sets_acciona_target_connection()
    {
        $mockSourceConnection = Mockery::mock();
        $mockSimsaTargetConnection = Mockery::mock(); // Parent constructor calls this first
        $mockAccionaTargetConnection = Mockery::mock();

        DB::shouldReceive('connection')->with('mysql_cloud')->andReturn($mockSourceConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_simsa_target')->andReturn($mockSimsaTargetConnection);
        DB::shouldReceive('connection')->with('mysql_gcp_acciona_target')->andReturn($mockAccionaTargetConnection);
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Config::shouldReceive('get')->andReturn(null);

        $repo = new AccionaInvoiceRepository('2025-01-01', '2025-01-31');

        $targetConnection = $this->getProtectedProperty($repo, 'targetConnection');

        $this->assertSame($mockAccionaTargetConnection, $targetConnection);
    }

    /**
     * Test constructor inherits date handling from parent
     */
    public function test_constructor_inherits_date_handling()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Config::shouldReceive('get')->andReturn(null);

        $repo = new AccionaInvoiceRepository('2025-01-01', '2025-01-31');

        $startDate = $this->getProtectedProperty($repo, 'startDate');
        $endDate = $this->getProtectedProperty($repo, 'endDate');

        $this->assertEquals('2025-01-01', $startDate);
        $this->assertEquals('2025-01-31 23:59:59', $endDate);
    }

    /**
     * Test constructor with null dates uses defaults from parent
     */
    public function test_constructor_with_null_dates()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Config::shouldReceive('get')->andReturn(null);

        $repo = new AccionaInvoiceRepository();

        $startDate = $this->getProtectedProperty($repo, 'startDate');
        $endDate = $this->getProtectedProperty($repo, 'endDate');

        $this->assertNotNull($startDate);
        $this->assertNotNull($endDate);
    }

    /**
     * Test instance is of correct type
     */
    public function test_instance_is_acciona_invoice_repository()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Config::shouldReceive('get')->andReturn(null);

        $repo = new AccionaInvoiceRepository('2025-01-01', '2025-01-31');

        $this->assertInstanceOf(AccionaInvoiceRepository::class, $repo);
        $this->assertInstanceOf(\App\Http\Repositories\SimsaInvoiceRepository::class, $repo);
    }

    /**
     * Test teamIds are Acciona-specific
     */
    public function test_team_ids_are_acciona_specific()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Config::shouldReceive('get')->andReturn(null);

        $repo = new AccionaInvoiceRepository('2025-01-01', '2025-01-31');

        $teamIds = $this->getProtectedProperty($repo, 'teamIds');

        // Verify Acciona team IDs are different from Simsa default [93, 339, 454]
        $this->assertNotContains(93, $teamIds);
        $this->assertNotContains(339, $teamIds);
        $this->assertNotContains(454, $teamIds);

        // Verify Acciona team IDs are correct
        $this->assertContains(500, $teamIds);
        $this->assertContains(508, $teamIds);
        $this->assertContains(516, $teamIds);
        $this->assertContains(524, $teamIds);
    }

    /**
     * Test table names are Acciona-specific
     */
    public function test_table_names_are_acciona_specific()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);
        Config::shouldReceive('get')->andReturn(null);

        $repo = new AccionaInvoiceRepository('2025-01-01', '2025-01-31');

        $invoceSCTableName = $this->getProtectedProperty($repo, 'invoceSCTableName');
        $invoceMEMTableName = $this->getProtectedProperty($repo, 'invoceMEMTableName');

        // Verify tables are prefixed with 'acciona' not 'simsa'
        $this->assertStringContainsString('acciona', $invoceSCTableName);
        $this->assertStringContainsString('acciona', $invoceMEMTableName);
        $this->assertStringNotContainsString('simsa', $invoceSCTableName);
        $this->assertStringNotContainsString('simsa', $invoceMEMTableName);
    }

    /**
     * Test API credentials are set from config
     */
    public function test_api_credentials_from_config()
    {
        DB::shouldReceive('connection')->andReturnSelf();
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturn(true);

        // Mock SIMSA config calls from parent constructor
        Config::shouldReceive('get')->with('app.SIMSA_SMART_EMAIL', Mockery::any())->andReturn('simsa@example.com');
        Config::shouldReceive('get')->with('app.SIMSA_SMART_PASSWORD', Mockery::any())->andReturn('simsapass');
        Config::shouldReceive('get')->with('app.SIMSA_SMART_EMAIL_TEAMID_93', Mockery::any())->andReturn(null);
        Config::shouldReceive('get')->with('app.SIMSA_SMART_PASSWORD_TEAMID_93', Mockery::any())->andReturn(null);

        // Mock Acciona config calls
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_EMAIL', Mockery::any())->andReturn('api@acciona.com');
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_PASSWORD', Mockery::any())->andReturn('apipassword');
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_EMAIL_TEAMID_500', Mockery::any())->andReturn(null);
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_PASSWORD_TEAMID_500', Mockery::any())->andReturn(null);
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_EMAIL_TEAMID_508', Mockery::any())->andReturn(null);
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_PASSWORD_TEAMID_508', Mockery::any())->andReturn(null);
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_EMAIL_TEAMID_516', Mockery::any())->andReturn(null);
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_PASSWORD_TEAMID_516', Mockery::any())->andReturn(null);
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_EMAIL_TEAMID_524', Mockery::any())->andReturn(null);
        Config::shouldReceive('get')->with('app.ACCIONA_SMART_PASSWORD_TEAMID_524', Mockery::any())->andReturn(null);

        $repo = new AccionaInvoiceRepository('2025-01-01', '2025-01-31');

        $apiEmail = $this->getProtectedProperty($repo, 'apiEmail');
        $apiPassword = $this->getProtectedProperty($repo, 'apiPassword');

        $this->assertEquals('api@acciona.com', $apiEmail);
        $this->assertEquals('apipassword', $apiPassword);
    }
}
