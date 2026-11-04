<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\CentroCarga;

class CentroCargaTest extends TestCase
{
    /**
     * Test CentroCarga uses correct database connection
     */
    public function test_centro_carga_uses_mysql_dev_2_connection()
    {
        $centroCarga = new CentroCarga();

        $this->assertEquals('mysql_dev_2', $centroCarga->getConnectionName());
    }

    /**
     * Test CentroCarga uses correct table name
     */
    public function test_centro_carga_uses_correct_table_name()
    {
        $centroCarga = new CentroCarga();

        $this->assertEquals('centrosCarga', $centroCarga->getTable());
    }

    /**
     * Test CentroCarga has guarded array empty
     */
    public function test_centro_carga_has_empty_guarded()
    {
        $centroCarga = new CentroCarga();

        $this->assertEquals([], $centroCarga->getGuarded());
    }

    /**
     * Test CentroCarga has correct fillable attributes
     */
    public function test_centro_carga_has_fillable_attributes()
    {
        $centroCarga = new CentroCarga();

        $fillable = $centroCarga->getFillable();

        $this->assertContains('userId', $fillable);
        $this->assertContains('teamId', $fillable);
        $this->assertContains('rpu', $fillable);
        $this->assertContains('rmu', $fillable);
        $this->assertContains('sistema', $fillable);
        $this->assertContains('nodoP', $fillable);
        $this->assertContains('useMedicionesMediMEM', $fillable);
        $this->assertContains('tokenMediMEM', $fillable);
        $this->assertContains('ceAsociada', $fillable);
    }

    /**
     * Test CentroCarga model can be instantiated
     */
    public function test_centro_carga_can_be_instantiated()
    {
        $centroCarga = new CentroCarga();

        $this->assertInstanceOf(CentroCarga::class, $centroCarga);
    }

    /**
     * Test CentroCarga extends Model
     */
    public function test_centro_carga_extends_model()
    {
        $centroCarga = new CentroCarga();

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Model::class, $centroCarga);
    }

    /**
     * Test CentroCarga fillable contains grupoTarifario
     */
    public function test_centro_carga_fillable_contains_grupo_tarifario()
    {
        $centroCarga = new CentroCarga();

        $this->assertContains('grupoTarifario', $centroCarga->getFillable());
    }

    /**
     * Test CentroCarga fillable contains nivelTension
     */
    public function test_centro_carga_fillable_contains_nivel_tension()
    {
        $centroCarga = new CentroCarga();

        $this->assertContains('nivelTension', $centroCarga->getFillable());
    }

    /**
     * Test CentroCarga fillable contains zonaCarga
     */
    public function test_centro_carga_fillable_contains_zona_carga()
    {
        $centroCarga = new CentroCarga();

        $this->assertContains('zonaCarga', $centroCarga->getFillable());
    }
}
