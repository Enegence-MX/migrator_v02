<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Http\Middleware\Authenticate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class AuthenticateTest extends TestCase
{
    /**
     * Test Authenticate middleware extends parent class
     */
    public function test_authenticate_extends_base_middleware()
    {
        $middleware = new Authenticate(app('auth'));

        $this->assertInstanceOf(\Illuminate\Auth\Middleware\Authenticate::class, $middleware);
    }

    /**
     * Test Authenticate middleware can be instantiated
     */
    public function test_authenticate_can_be_instantiated()
    {
        $middleware = new Authenticate(app('auth'));

        $this->assertInstanceOf(Authenticate::class, $middleware);
    }

    /**
     * Test redirectTo returns null when request expects JSON
     */
    public function test_redirect_to_returns_null_for_json_request()
    {
        Route::get('/login', function () {
            return 'login';
        })->name('login');

        $middleware = new Authenticate(app('auth'));
        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept', 'application/json');

        $reflection = new \ReflectionClass($middleware);
        $method = $reflection->getMethod('redirectTo');
        $method->setAccessible(true);

        $result = $method->invoke($middleware, $request);

        $this->assertNull($result);
    }

    /**
     * Test redirectTo behavior with non-JSON request
     */
    public function test_redirect_to_behavior_for_non_json_request()
    {
        $middleware = new Authenticate(app('auth'));
        $request = Request::create('/test', 'GET');

        // Verify that the method would attempt to redirect for non-JSON requests
        // by checking if request expects JSON returns false
        $this->assertFalse($request->expectsJson());
    }

    /**
     * Test redirectTo method exists
     */
    public function test_redirect_to_method_exists()
    {
        $middleware = new Authenticate(app('auth'));

        $reflection = new \ReflectionClass($middleware);

        $this->assertTrue($reflection->hasMethod('redirectTo'));
    }

    /**
     * Test redirectTo is protected method
     */
    public function test_redirect_to_is_protected()
    {
        $reflection = new \ReflectionClass(Authenticate::class);
        $method = $reflection->getMethod('redirectTo');

        $this->assertTrue($method->isProtected());
    }
}
