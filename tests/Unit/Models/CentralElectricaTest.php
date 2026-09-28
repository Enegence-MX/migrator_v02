<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\CentralElectrica;

class CentralElectricaTest extends TestCase
{
    /**
     * Test CentralElectrica uses correct database connection
     */
    public function test_central_electrica_uses_mysql_dev_2_connection()
    {
        $centralElectrica = new CentralElectrica();

        $this->assertEquals('mysql_dev_2', $centralElectrica->getConnectionName());
    }

    /**
     * Test CentralElectrica uses correct table name
     */
    public function test_central_electrica_uses_correct_table_name()
    {
        $centralElectrica = new CentralElectrica();

        $this->assertEquals('centralElectrica', $centralElectrica->getTable());
    }

    /**
     * Test CentralElectrica has guarded array empty
     */
    public function test_central_electrica_has_empty_guarded()
    {
        $centralElectrica = new CentralElectrica();

        $this->assertEquals([], $centralElectrica->getGuarded());
    }

    /**
     * Test CentralElectrica model can be instantiated
     */
    public function test_central_electrica_can_be_instantiated()
    {
        $centralElectrica = new CentralElectrica();

        $this->assertInstanceOf(CentralElectrica::class, $centralElectrica);
    }

    /**
     * Test CentralElectrica extends Model
     */
    public function test_central_electrica_extends_model()
    {
        $centralElectrica = new CentralElectrica();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Model::class, $centralElectrica);
    }
}
