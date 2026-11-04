<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TeamTest extends TestCase
{
    /**
     * Test Team model has correct fillable attributes
     */
    public function test_team_has_fillable_attributes()
    {
        $team = new Team();

        $expected = [
            'team_id',
            'name',
            'active',
            'sync_general_data',
            'sync_catalog_data',
            'sync_cloud_catalog_data',
            'sync_measurements',
            'sync_liquidaciones_ecd',
        ];

        $this->assertEquals($expected, $team->getFillable());
    }

    /**
     * Test Team model casts attributes correctly
     */
    public function test_team_casts_attributes()
    {
        $team = new Team();

        $casts = $team->getCasts();

        $this->assertArrayHasKey('active', $casts);
        $this->assertEquals('boolean', $casts['active']);
        $this->assertArrayHasKey('sync_general_data', $casts);
        $this->assertEquals('boolean', $casts['sync_general_data']);
        $this->assertArrayHasKey('sync_catalog_data', $casts);
        $this->assertEquals('boolean', $casts['sync_catalog_data']);
        $this->assertArrayHasKey('sync_measurements', $casts);
        $this->assertEquals('boolean', $casts['sync_measurements']);
    }

    /**
     * Test Team has credentials method
     */
    public function test_team_has_credentials_method()
    {
        $team = new Team();

        $this->assertTrue(method_exists($team, 'credentials'));
    }

    /**
     * Test Team has databases method
     */
    public function test_team_has_databases_method()
    {
        $team = new Team();

        $this->assertTrue(method_exists($team, 'databases'));
    }

    /**
     * Test Team has users method
     */
    public function test_team_has_users_method()
    {
        $team = new Team();

        $this->assertTrue(method_exists($team, 'users'));
    }

    /**
     * Test Team uses correct database connection
     */
    public function test_team_uses_mysql_connection()
    {
        $team = new Team();

        $this->assertEquals('mysql', $team->getConnectionName());
    }

    /**
     * Test Team model can be instantiated
     */
    public function test_team_can_be_instantiated()
    {
        $team = new Team();

        $this->assertInstanceOf(Team::class, $team);
    }

    /**
     * Test Team model has correct table name
     */
    public function test_team_has_correct_table_name()
    {
        $team = new Team();

        $this->assertEquals('teams', $team->getTable());
    }

    /**
     * Test Team casts sync_cloud_catalog_data to boolean
     */
    public function test_team_casts_sync_cloud_catalog_data()
    {
        $team = new Team();

        $casts = $team->getCasts();

        $this->assertArrayHasKey('sync_cloud_catalog_data', $casts);
        $this->assertEquals('boolean', $casts['sync_cloud_catalog_data']);
    }

    /**
     * Test Team casts sync_liquidaciones_ecd to boolean
     */
    public function test_team_casts_sync_liquidaciones_ecd()
    {
        $team = new Team();

        $casts = $team->getCasts();

        $this->assertArrayHasKey('sync_liquidaciones_ecd', $casts);
        $this->assertEquals('boolean', $casts['sync_liquidaciones_ecd']);
    }
}
