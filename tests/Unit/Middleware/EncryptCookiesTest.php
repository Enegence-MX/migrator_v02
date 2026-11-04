<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Http\Middleware\EncryptCookies;

class EncryptCookiesTest extends TestCase
{
    /**
     * Test EncryptCookies middleware extends parent class
     */
    public function test_encrypt_cookies_extends_base_middleware()
    {
        $middleware = new EncryptCookies(app('encrypter'));

        $this->assertInstanceOf(\Illuminate\Cookie\Middleware\EncryptCookies::class, $middleware);
    }

    /**
     * Test EncryptCookies middleware can be instantiated
     */
    public function test_encrypt_cookies_can_be_instantiated()
    {
        $middleware = new EncryptCookies(app('encrypter'));

        $this->assertInstanceOf(EncryptCookies::class, $middleware);
    }

    /**
     * Test except property exists
     */
    public function test_except_property_exists()
    {
        $reflection = new \ReflectionClass(EncryptCookies::class);

        $this->assertTrue($reflection->hasProperty('except'));
    }

    /**
     * Test except property is array
     */
    public function test_except_property_is_array()
    {
        $middleware = new EncryptCookies(app('encrypter'));

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
        $middleware = new EncryptCookies(app('encrypter'));

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
        $reflection = new \ReflectionClass(EncryptCookies::class);
        $property = $reflection->getProperty('except');

        $this->assertTrue($property->isProtected());
    }
}
