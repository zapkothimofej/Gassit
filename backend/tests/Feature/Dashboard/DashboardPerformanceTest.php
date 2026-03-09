<?php

namespace Tests\Feature\Dashboard;

use App\Models\Application;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Park;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_kpi_responds_under_200ms_with_seeded_data(): void
    {
        $admin    = User::factory()->create(['role' => 'admin', 'active' => true]);
        $park     = Park::factory()->create();
        $unitType = UnitType::factory()->create(['park_id' => $park->id]);

        // Seed 50 records (representative load for unit test — not 1000 to keep CI fast)
        Customer::factory()->count(50)->create();
        Unit::factory()->count(50)->create([
            'park_id'      => $park->id,
            'unit_type_id' => $unitType->id,
        ]);
        Application::factory()->count(50)->create([
            'park_id'      => $park->id,
            'unit_type_id' => $unitType->id,
        ]);

        Cache::flush();
        $token = $admin->createToken('test')->plainTextToken;

        $start = microtime(true);

        $response = $this->withToken($token)
            ->getJson('/api/dashboard/kpis');

        $elapsed = (microtime(true) - $start) * 1000;

        $response->assertOk();
        $this->assertLessThan(200, $elapsed, "KPI endpoint took {$elapsed}ms, expected <200ms");
    }

    public function test_dashboard_kpi_is_cached(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $token = $admin->createToken('test')->plainTextToken;

        Cache::flush();

        $this->withToken($token)->getJson('/api/dashboard/kpis')->assertOk();

        $cacheKey = 'dashboard_kpis_all_' . $admin->id;
        $this->assertTrue(Cache::has($cacheKey), 'Dashboard KPI response should be cached');
    }
}
