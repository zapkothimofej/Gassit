<?php

namespace Tests\Feature\RevenueTarget;

use App\Models\Invoice;
use App\Models\Park;
use App\Models\RevenueTarget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueTargetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Park $park;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->park  = Park::factory()->create();
    }

    public function test_list_targets_for_year(): void
    {
        RevenueTarget::create(['park_id' => $this->park->id, 'year' => 2026, 'month' => 1, 'target_amount' => 1000]);
        RevenueTarget::create(['park_id' => $this->park->id, 'year' => 2026, 'month' => 2, 'target_amount' => 2000]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/parks/{$this->park->id}/revenue-targets?year=2026");

        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonFragment(['month' => 1, 'target_amount' => '1000.00']);
        $response->assertJsonFragment(['month' => 2, 'target_amount' => '2000.00']);
    }

    public function test_list_targets_defaults_to_current_year(): void
    {
        $year = now()->year;
        RevenueTarget::create(['park_id' => $this->park->id, 'year' => $year, 'month' => 3, 'target_amount' => 500]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/parks/{$this->park->id}/revenue-targets");

        $response->assertOk();
        $response->assertJsonFragment(['month' => 3]);
    }

    public function test_list_targets_forbidden_for_wrong_park(): void
    {
        $other = Park::factory()->create();
        $manager = User::factory()->create(['role' => 'rental_manager']);
        $manager->parks()->attach($other->id);

        $response = $this->actingAs($manager)
            ->getJson("/api/parks/{$this->park->id}/revenue-targets");

        $response->assertForbidden();
    }

    public function test_store_creates_target(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/parks/{$this->park->id}/revenue-targets", [
                'year'          => 2026,
                'month'         => 6,
                'target_amount' => 3500.00,
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['year' => 2026, 'month' => 6, 'target_amount' => '3500.00']);
        $this->assertDatabaseHas('revenue_targets', ['park_id' => $this->park->id, 'month' => 6]);
    }

    public function test_store_upserts_existing_target(): void
    {
        RevenueTarget::create(['park_id' => $this->park->id, 'year' => 2026, 'month' => 6, 'target_amount' => 1000]);

        $response = $this->actingAs($this->admin)
            ->postJson("/api/parks/{$this->park->id}/revenue-targets", [
                'year'          => 2026,
                'month'         => 6,
                'target_amount' => 9999,
            ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['target_amount' => '9999.00']);
        $this->assertDatabaseCount('revenue_targets', 1);
    }

    public function test_store_requires_valid_month(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson("/api/parks/{$this->park->id}/revenue-targets", [
                'year'          => 2026,
                'month'         => 13,
                'target_amount' => 1000,
            ]);

        $response->assertUnprocessable();
    }

    public function test_update_target_amount(): void
    {
        $target = RevenueTarget::create(['park_id' => $this->park->id, 'year' => 2026, 'month' => 1, 'target_amount' => 1000]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/revenue-targets/{$target->id}", ['target_amount' => 5000]);

        $response->assertOk();
        $response->assertJsonFragment(['target_amount' => '5000.00']);
    }

    public function test_update_forbidden_for_wrong_park(): void
    {
        $other = Park::factory()->create();
        $target = RevenueTarget::create(['park_id' => $other->id, 'year' => 2026, 'month' => 1, 'target_amount' => 1000]);

        $manager = User::factory()->create(['role' => 'rental_manager']);
        $manager->parks()->attach($this->park->id);

        $response = $this->actingAs($manager)
            ->putJson("/api/revenue-targets/{$target->id}", ['target_amount' => 9999]);

        $response->assertForbidden();
    }

    public function test_actual_revenue_returns_paid_invoices_sum(): void
    {
        Invoice::factory()->create([
            'park_id'      => $this->park->id,
            'status'       => 'paid',
            'total_amount' => 1200,
            'created_at'   => '2026-03-15',
        ]);
        Invoice::factory()->create([
            'park_id'      => $this->park->id,
            'status'       => 'paid',
            'total_amount' => 800,
            'created_at'   => '2026-03-20',
        ]);
        // Different month — should not count
        Invoice::factory()->create([
            'park_id'      => $this->park->id,
            'status'       => 'paid',
            'total_amount' => 500,
            'created_at'   => '2026-04-01',
        ]);
        // Unpaid — should not count
        Invoice::factory()->create([
            'park_id'      => $this->park->id,
            'status'       => 'sent',
            'total_amount' => 300,
            'created_at'   => '2026-03-10',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/parks/{$this->park->id}/revenue-targets/2026/3/actual");

        $response->assertOk();
        $response->assertJsonFragment([
            'park_id' => $this->park->id,
            'year'    => 2026,
            'month'   => 3,
            'actual'  => 2000.0,
        ]);
    }

    public function test_actual_revenue_zero_when_no_invoices(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson("/api/parks/{$this->park->id}/revenue-targets/2026/1/actual");

        $response->assertOk();
        $response->assertJsonFragment(['actual' => 0.0]);
    }

    public function test_requires_auth(): void
    {
        $this->getJson("/api/parks/{$this->park->id}/revenue-targets")->assertUnauthorized();
    }

    public function test_accountant_can_access_revenue_targets(): void
    {
        $accountant = User::factory()->create(['role' => 'accountant']);
        $accountant->parks()->attach($this->park->id);

        $response = $this->actingAs($accountant)
            ->getJson("/api/parks/{$this->park->id}/revenue-targets");

        $response->assertOk();
    }

    public function test_park_worker_cannot_access_revenue_targets(): void
    {
        $worker = User::factory()->create(['role' => 'park_worker']);
        $worker->parks()->attach($this->park->id);

        $response = $this->actingAs($worker)
            ->getJson("/api/parks/{$this->park->id}/revenue-targets");

        $response->assertForbidden();
    }
}
