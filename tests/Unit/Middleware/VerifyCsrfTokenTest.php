<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Http\Middleware\VerifyCsrfToken;

class VerifyCsrfTokenTest extends TestCase
{
    /**
     * Test VerifyCsrfToken middleware extends parent class
     */
    public function test_verify_csrf_token_extends_base_middleware()
    {
        $middleware = new VerifyCsrfToken(app(), app('encrypter'));

        $this->assertInstanceOf(
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
            $middleware
        );
    }

    /**
     * Test VerifyCsrfToken middleware can be instantiated
     */
    public function test_verify_csrf_token_can_be_instantiated()
    {
        $middleware = new VerifyCsrfToken(app(), app('encrypter'));

        $this->assertInstanceOf(VerifyCsrfToken::class, $middleware);
    }

    /**
     * Test except property exists
     */
    public function test_except_property_exists()
    {
        $reflection = new \ReflectionClass(VerifyCsrfToken::class);

        $this->assertTrue($reflection->hasProperty('except'));
    }

    /**
     * Test except property is array
     */
    public function test_except_property_is_array()
    {
        $middleware = new VerifyCsrfToken(app(), app('encrypter'));

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
        $middleware = new VerifyCsrfToken(app(), app('encrypter'));

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
        $reflection = new \ReflectionClass(VerifyCsrfToken::class);
        $property = $reflection->getProperty('except');

        $this->assertTrue($property->isProtected());
    }
}
