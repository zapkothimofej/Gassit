<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\ParkScopeMiddleware;
use App\Models\Park;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

class ParkScopeMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bypasses_park_check(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $park = Park::factory()->create();

        $this->assertTrue(ParkScopeMiddleware::hasAccessToPark($admin, $park->id));
    }

    public function test_main_manager_bypasses_park_check(): void
    {
        $manager = User::factory()->create(['role' => 'main_manager', 'active' => true]);
        $park = Park::factory()->create();

        $this->assertTrue(ParkScopeMiddleware::hasAccessToPark($manager, $park->id));
    }

    public function test_user_with_park_access_allowed(): void
    {
        $user = User::factory()->create(['role' => 'rental_manager', 'active' => true]);
        $park = Park::factory()->create();
        $user->parks()->attach($park->id);

        $this->assertTrue(ParkScopeMiddleware::hasAccessToPark($user, $park->id));
    }

    public function test_user_without_park_access_denied(): void
    {
        $user = User::factory()->create(['role' => 'rental_manager', 'active' => true]);
        $park = Park::factory()->create();

        $this->assertFalse(ParkScopeMiddleware::hasAccessToPark($user, $park->id));
    }

    public function test_middleware_allows_request_without_park_id(): void
    {
        $user = User::factory()->create(['role' => 'rental_manager', 'active' => true]);
        $request = Request::create('/test');
        $request->setUserResolver(fn() => $user);

        $middleware = new ParkScopeMiddleware();
        $response = $middleware->handle($request, fn($r) => new Response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }
}
