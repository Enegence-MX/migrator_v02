<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Http\Middleware\TrustHosts;
use Illuminate\Support\Facades\Config;

class TrustHostsTest extends TestCase
{
    /**
     * Test TrustHosts middleware extends parent class
     */
    public function test_trust_hosts_extends_base_middleware()
    {
        $middleware = new TrustHosts(app());

        $this->assertInstanceOf(\Illuminate\Http\Middleware\TrustHosts::class, $middleware);
    }

    /**
     * Test TrustHosts middleware can be instantiated
     */
    public function test_trust_hosts_can_be_instantiated()
    {
        $middleware = new TrustHosts(app());

        $this->assertInstanceOf(TrustHosts::class, $middleware);
    }

    /**
     * Test hosts method exists
     */
    public function test_hosts_method_exists()
    {
        $middleware = new TrustHosts(app());

        $this->assertTrue(method_exists($middleware, 'hosts'));
    }

    /**
     * Test hosts returns array
     */
    public function test_hosts_returns_array()
    {
        Config::set('app.url', 'https://example.com');

        $middleware = new TrustHosts(app());
        $hosts = $middleware->hosts();

        $this->assertIsArray($hosts);
    }

    /**
     * Test hosts includes allSubdomainsOfApplicationUrl
     */
    public function test_hosts_includes_all_subdomains()
    {
        Config::set('app.url', 'https://example.com');

        $middleware = new TrustHosts(app());
        $hosts = $middleware->hosts();

        $this->assertNotEmpty($hosts);
        $this->assertCount(1, $hosts);
    }
}
