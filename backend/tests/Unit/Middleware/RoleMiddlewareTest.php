<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\RoleMiddleware;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    public function test_allows_user_with_matching_role(): void
    {
        $user = new User(['role' => 'admin', 'active' => true]);
        $request = Request::create('/test');
        $request->setUserResolver(fn() => $user);

        $middleware = new RoleMiddleware();
        $response = $middleware->handle($request, fn($r) => new Response('OK'), 'admin', 'main_manager');

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_rejects_user_with_wrong_role(): void
    {
        $user = new User(['role' => 'customer_service', 'active' => true]);
        $request = Request::create('/test');
        $request->setUserResolver(fn() => $user);

        $middleware = new RoleMiddleware();
        $response = $middleware->handle($request, fn($r) => new Response('OK'), 'admin');

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_rejects_unauthenticated_request(): void
    {
        $request = Request::create('/test');
        $request->setUserResolver(fn() => null);

        $middleware = new RoleMiddleware();
        $response = $middleware->handle($request, fn($r) => new Response('OK'), 'admin');

        $this->assertEquals(403, $response->getStatusCode());
    }
}
