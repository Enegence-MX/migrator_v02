<?php

namespace Tests\Unit\Middleware;

use Tests\TestCase;
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class RedirectIfAuthenticatedTest extends TestCase
{
    /**
     * Test RedirectIfAuthenticated middleware can be instantiated
     */
    public function test_redirect_if_authenticated_can_be_instantiated()
    {
        $middleware = new RedirectIfAuthenticated();

        $this->assertInstanceOf(RedirectIfAuthenticated::class, $middleware);
    }

    /**
     * Test handle method exists
     */
    public function test_handle_method_exists()
    {
        $middleware = new RedirectIfAuthenticated();

        $this->assertTrue(method_exists($middleware, 'handle'));
    }

    /**
     * Test handle redirects authenticated user to home
     */
    public function test_handle_redirects_authenticated_user()
    {
        Route::get('/home', function () {
            return 'home';
        })->name('home');

        Auth::shouldReceive('guard')
            ->with(null)
            ->andReturnSelf();
        Auth::shouldReceive('check')
            ->andReturn(true);

        $middleware = new RedirectIfAuthenticated();
        $request = Request::create('/login', 'GET');

        $response = $middleware->handle($request, function () {
            return response('next');
        });

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString(RouteServiceProvider::HOME, $response->getTargetUrl());
    }

    /**
     * Test handle allows unauthenticated user to proceed
     */
    public function test_handle_allows_unauthenticated_user()
    {
        Auth::shouldReceive('guard')
            ->with(null)
            ->andReturnSelf();
        Auth::shouldReceive('check')
            ->andReturn(false);

        $middleware = new RedirectIfAuthenticated();
        $request = Request::create('/login', 'GET');

        $response = $middleware->handle($request, function () {
            return response('next');
        });

        $this->assertEquals('next', $response->getContent());
    }

    /**
     * Test handle works with custom guard
     */
    public function test_handle_works_with_custom_guard()
    {
        Route::get('/home', function () {
            return 'home';
        })->name('home');

        Auth::shouldReceive('guard')
            ->with('admin')
            ->andReturnSelf();
        Auth::shouldReceive('check')
            ->andReturn(true);

        $middleware = new RedirectIfAuthenticated();
        $request = Request::create('/login', 'GET');

        $response = $middleware->handle($request, function () {
            return response('next');
        }, 'admin');

        $this->assertEquals(302, $response->getStatusCode());
    }

    /**
     * Test handle works with multiple guards
     */
    public function test_handle_works_with_multiple_guards()
    {
        Route::get('/home', function () {
            return 'home';
        })->name('home');

        Auth::shouldReceive('guard')
            ->with('web')
            ->andReturnSelf();
        Auth::shouldReceive('check')
            ->once()
            ->andReturn(false);

        Auth::shouldReceive('guard')
            ->with('admin')
            ->andReturnSelf();
        Auth::shouldReceive('check')
            ->once()
            ->andReturn(true);

        $middleware = new RedirectIfAuthenticated();
        $request = Request::create('/login', 'GET');

        $response = $middleware->handle($request, function () {
            return response('next');
        }, 'web', 'admin');

        $this->assertEquals(302, $response->getStatusCode());
    }

    /**
     * Test handle proceeds when all guards are unauthenticated
     */
    public function test_handle_proceeds_when_all_guards_unauthenticated()
    {
        Auth::shouldReceive('guard')
            ->with('web')
            ->andReturnSelf();
        Auth::shouldReceive('check')
            ->once()
            ->andReturn(false);

        Auth::shouldReceive('guard')
            ->with('admin')
            ->andReturnSelf();
        Auth::shouldReceive('check')
            ->once()
            ->andReturn(false);

        $middleware = new RedirectIfAuthenticated();
        $request = Request::create('/login', 'GET');

        $response = $middleware->handle($request, function () {
            return response('next');
        }, 'web', 'admin');

        $this->assertEquals('next', $response->getContent());
    }
}
