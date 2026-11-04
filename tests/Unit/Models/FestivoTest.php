<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Festivo;

class FestivoTest extends TestCase
{
    /**
     * Test Festivo uses correct database connection
     */
    public function test_festivo_uses_mysql_dev_2_connection()
    {
        $festivo = new Festivo();

        $this->assertEquals('mysql_dev_2', $festivo->getConnectionName());
    }

    /**
     * Test Festivo has guarded array empty
     */
    public function test_festivo_has_empty_guarded()
    {
        $festivo = new Festivo();

        $this->assertEquals([], $festivo->getGuarded());
    }

    /**
     * Test Festivo has timestamps disabled
     */
    public function test_festivo_has_timestamps_disabled()
    {
        $festivo = new Festivo();

        $this->assertFalse($festivo->timestamps);
    }

    /**
     * Test Festivo model can be instantiated
     */
    public function test_festivo_can_be_instantiated()
    {
        $festivo = new Festivo();

        $this->assertInstanceOf(Festivo::class, $festivo);
    }

    /**
     * Test Festivo extends Model
     */
    public function test_festivo_extends_model()
    {
        $festivo = new Festivo();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Model::class, $festivo);
    }

    /**
     * Test Festivo uses correct table name (default 'festivos')
     */
    public function test_festivo_uses_default_table_name()
    {
        $festivo = new Festivo();

        $this->assertEquals('festivos', $festivo->getTable());
    }
}
