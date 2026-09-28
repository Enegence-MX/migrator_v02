<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Http\Middleware\TrimStrings;

class TrimStringsTest extends TestCase
{
    /**
     * Test TrimStrings middleware extends parent class
     */
    public function test_trim_strings_extends_base_middleware()
    {
        $middleware = new TrimStrings(app());

        $this->assertInstanceOf(\Illuminate\Foundation\Http\Middleware\TrimStrings::class, $middleware);
    }

    /**
     * Test TrimStrings middleware can be instantiated
     */
    public function test_trim_strings_can_be_instantiated()
    {
        $middleware = new TrimStrings(app());

        $this->assertInstanceOf(TrimStrings::class, $middleware);
    }

    /**
     * Test except property exists
     */
    public function test_except_property_exists()
    {
        $reflection = new \ReflectionClass(TrimStrings::class);

        $this->assertTrue($reflection->hasProperty('except'));
    }

    /**
     * Test except property is array
     */
    public function test_except_property_is_array()
    {
        $middleware = new TrimStrings(app());

        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('except');
        $property->setAccessible(true);

        $this->assertIsArray($property->getValue($middleware));
    }

    /**
     * Test except property contains password fields
     */
    public function test_except_property_contains_password_fields()
    {
        $middleware = new TrimStrings(app());

        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('except');
        $property->setAccessible(true);

        $except = $property->getValue($middleware);

        $this->assertContains('current_password', $except);
        $this->assertContains('password', $except);
        $this->assertContains('password_confirmation', $except);
    }

    /**
     * Test except property has exactly 3 items
     */
    public function test_except_property_has_three_items()
    {
        $middleware = new TrimStrings(app());

        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('except');
        $property->setAccessible(true);

        $this->assertCount(3, $property->getValue($middleware));
    }

    /**
     * Test except property is protected
     */
    public function test_except_property_is_protected()
    {
        $reflection = new \ReflectionClass(TrimStrings::class);
        $property = $reflection->getProperty('except');

        $this->assertTrue($property->isProtected());
    }

    /**
     * Test except contains current_password
     */
    public function test_except_contains_current_password()
    {
        $middleware = new TrimStrings(app());

        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('except');
        $property->setAccessible(true);

        $this->assertContains('current_password', $property->getValue($middleware));
    }

    /**
     * Test except contains password
     */
    public function test_except_contains_password()
    {
        $middleware = new TrimStrings(app());

        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('except');
        $property->setAccessible(true);

        $this->assertContains('password', $property->getValue($middleware));
    }

    /**
     * Test except contains password_confirmation
     */
    public function test_except_contains_password_confirmation()
    {
        $middleware = new TrimStrings(app());

        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('except');
        $property->setAccessible(true);

        $this->assertContains('password_confirmation', $property->getValue($middleware));
    }
}
