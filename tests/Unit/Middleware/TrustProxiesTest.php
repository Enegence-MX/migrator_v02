<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

class TrustProxiesTest extends TestCase
{
    /**
     * Test TrustProxies middleware extends parent class
     */
    public function test_trust_proxies_extends_base_middleware()
    {
        $middleware = new TrustProxies(app());

        $this->assertInstanceOf(\Illuminate\Http\Middleware\TrustProxies::class, $middleware);
    }

    /**
     * Test TrustProxies middleware can be instantiated
     */
    public function test_trust_proxies_can_be_instantiated()
    {
        $middleware = new TrustProxies(app());

        $this->assertInstanceOf(TrustProxies::class, $middleware);
    }

    /**
     * Test proxies property is null by default
     */
    public function test_proxies_property_is_null()
    {
        $middleware = new TrustProxies(app());

        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('proxies');
        $property->setAccessible(true);

        $this->assertNull($property->getValue($middleware));
    }

    /**
     * Test headers property is set correctly
     */
    public function test_headers_property_is_set()
    {
        $middleware = new TrustProxies(app());

        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('headers');
        $property->setAccessible(true);

        $expectedHeaders =
            Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB;

        $this->assertEquals($expectedHeaders, $property->getValue($middleware));
    }

    /**
     * Test headers includes X-Forwarded-For
     */
    public function test_headers_includes_x_forwarded_for()
    {
        $middleware = new TrustProxies(app());

        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('headers');
        $property->setAccessible(true);

        $headers = $property->getValue($middleware);

        $this->assertTrue(($headers & Request::HEADER_X_FORWARDED_FOR) !== 0);
    }

    /**
     * Test headers includes X-Forwarded-Host
     */
    public function test_headers_includes_x_forwarded_host()
    {
        $middleware = new TrustProxies(app());

        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('headers');
        $property->setAccessible(true);

        $headers = $property->getValue($middleware);

        $this->assertTrue(($headers & Request::HEADER_X_FORWARDED_HOST) !== 0);
    }

    /**
     * Test headers includes X-Forwarded-Proto
     */
    public function test_headers_includes_x_forwarded_proto()
    {
        $middleware = new TrustProxies(app());

        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('headers');
        $property->setAccessible(true);

        $headers = $property->getValue($middleware);

        $this->assertTrue(($headers & Request::HEADER_X_FORWARDED_PROTO) !== 0);
    }

    /**
     * Test headers includes AWS ELB header
     */
    public function test_headers_includes_aws_elb()
    {
        $middleware = new TrustProxies(app());

        $reflection = new \ReflectionClass($middleware);
        $property = $reflection->getProperty('headers');
        $property->setAccessible(true);

        $headers = $property->getValue($middleware);

        $this->assertTrue(($headers & Request::HEADER_X_FORWARDED_AWS_ELB) !== 0);
    }
}
