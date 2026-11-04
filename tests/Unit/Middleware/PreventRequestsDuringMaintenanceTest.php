<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Http\Middleware\PreventRequestsDuringMaintenance;

class PreventRequestsDuringMaintenanceTest extends TestCase
{
    /**
     * Test PreventRequestsDuringMaintenance middleware extends parent class
     */
    public function test_prevent_requests_during_maintenance_extends_base_middleware()
    {
        $middleware = new PreventRequestsDuringMaintenance(app());

        $this->assertInstanceOf(
            \Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance::class,
            $middleware
        );
    }

    /**
     * Test PreventRequestsDuringMaintenance middleware can be instantiated
     */
    public function test_prevent_requests_during_maintenance_can_be_instantiated()
    {
        $middleware = new PreventRequestsDuringMaintenance(app());

        $this->assertInstanceOf(PreventRequestsDuringMaintenance::class, $middleware);
    }

    /**
     * Test except property exists
     */
    public function test_except_property_exists()
    {
        $reflection = new \ReflectionClass(PreventRequestsDuringMaintenance::class);

        $this->assertTrue($reflection->hasProperty('except'));
    }

    /**
     * Test except property is array
     */
    public function test_except_property_is_array()
    {
        $middleware = new PreventRequestsDuringMaintenance(app());

        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('except');
        $property->setAccessible(true);

        $this->assertIsArray($property->getValue($middleware));
    }

    /**
     * Test except property is empty by default
     */
    public function test_except_property_is_empty()
    {
        $middleware = new PreventRequestsDuringMaintenance(app());

        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('except');
        $property->setAccessible(true);

        $this->assertEmpty($property->getValue($middleware));
    }

    /**
     * Test except property is protected
     */
    public function test_except_property_is_protected()
    {
        $reflection = new \ReflectionClass(PreventRequestsDuringMaintenance::class);
        $property = $reflection->getProperty('except');

        $this->assertTrue($property->isProtected());
    }
}
