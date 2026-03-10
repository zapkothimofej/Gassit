<?php

namespace Tests\Feature\Dashboard;

use App\Models\Application;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\DamageReport;
use App\Models\DunningRecord;
use App\Models\Invoice;
use App\Models\Park;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardTest extends TestCase
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

    // KPI tests

    public function test_kpis_returns_all_fields(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/dashboard/kpis');

        $response->assertOk();
        $data = $response->json();

        $this->assertArrayHasKey('new_requests', $data);
        $this->assertArrayHasKey('new_customers', $data);
        $this->assertArrayHasKey('new_invoices_count', $data);
        $this->assertArrayHasKey('free_units', $data);
        $this->assertArrayHasKey('ongoing_contracts', $data);
        $this->assertArrayHasKey('cancellations', $data);
        $this->assertArrayHasKey('problem_clients', $data);
        $this->assertArrayHasKey('inactive_units', $data);
        $this->assertArrayHasKey('debtors_count', $data);
        $this->assertArrayHasKey('max_dunning_level', $data);
        $this->assertArrayHasKey('damages_open', $data);
        $this->assertArrayHasKey('repair_jobs_open', $data);
    }

    public function test_kpis_counts_free_units(): void
    {
        Unit::factory()->create(['park_id' => $this->park->id, 'status' => 'free']);
        Unit::factory()->create(['park_id' => $this->park->id, 'status' => 'rented']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/kpis?park_id=' . $this->park->id);

        $response->assertOk();
        $this->assertEquals(1, $response->json('free_units'));
    }

    public function test_kpis_counts_inactive_units(): void
    {
        Unit::factory()->create(['park_id' => $this->park->id, 'status' => 'inactive']);
        Unit::factory()->create(['park_id' => $this->park->id, 'status' => 'free']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/kpis?park_id=' . $this->park->id);

        $response->assertOk();
        $this->assertEquals(1, $response->json('inactive_units'));
    }

    public function test_kpis_counts_new_requests_this_month(): void
    {
        Application::factory()->create(['park_id' => $this->park->id, 'created_at' => now()]);
        Application::factory()->create(['park_id' => $this->park->id, 'created_at' => now()->subMonths(2)]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/kpis?park_id=' . $this->park->id);

        $response->assertOk();
        $this->assertEquals(1, $response->json('new_requests'));
    }

    public function test_kpis_counts_damages_open(): void
    {
        $unit = Unit::factory()->create(['park_id' => $this->park->id]);
        DamageReport::factory()->create(['unit_id' => $unit->id, 'status' => 'reported']);
        DamageReport::factory()->create(['unit_id' => $unit->id, 'status' => 'closed']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/kpis?park_id=' . $this->park->id);

        $response->assertOk();
        $this->assertEquals(1, $response->json('damages_open'));
    }

    public function test_kpis_returns_403_for_wrong_park(): void
    {
        $rental = User::factory()->create(['role' => 'rental_manager']);
        $otherPark = Park::factory()->create();

        $response = $this->actingAs($rental)
            ->getJson('/api/dashboard/kpis?park_id=' . $otherPark->id);

        $response->assertForbidden();
    }

    public function test_kpis_cached_for_30_seconds(): void
    {
        Cache::flush();

        $this->actingAs($this->admin)->getJson('/api/dashboard/kpis');

        $cacheKey = 'dashboard_kpis_all_' . $this->admin->id;
        $this->assertTrue(Cache::has($cacheKey));
    }

    // Mahnstuffe tests

    public function test_mahnstuffe_returns_debtor_rows(): void
    {
        $customer = Customer::factory()->create();
        $invoice  = Invoice::factory()->create(['customer_id' => $customer->id, 'park_id' => $this->park->id, 'status' => 'sent', 'total_amount' => 200.00]);
        DunningRecord::factory()->create(['customer_id' => $customer->id, 'invoice_id' => $invoice->id, 'level' => 2]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/mahnstuffe?park_id=' . $this->park->id);

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals(2, $data[0]['dunning_level']);
        $this->assertEquals(200.00, $data[0]['total_owed']);
    }

    public function test_mahnstuffe_returns_403_for_wrong_park(): void
    {
        $rental = User::factory()->create(['role' => 'rental_manager']);
        $otherPark = Park::factory()->create();

        $response = $this->actingAs($rental)
            ->getJson('/api/dashboard/mahnstuffe?park_id=' . $otherPark->id);

        $response->assertForbidden();
    }

    // Revenue tests

    public function test_revenue_returns_planned_and_actual(): void
    {
        Invoice::factory()->create([
            'park_id'      => $this->park->id,
            'status'       => 'paid',
            'total_amount' => 500.00,
            'created_at'   => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/dashboard/revenue?park_id=' . $this->park->id);

        $response->assertOk();
        $data = $response->json();
        $this->assertIsArray($data);
        $row = collect($data)->firstWhere('park_id', $this->park->id);
        $this->assertNotNull($row);
        $this->assertEquals(500.00, $row['actual']);
    }

    public function test_revenue_returns_403_for_wrong_park(): void
    {
        $rental = User::factory()->create(['role' => 'rental_manager']);
        $otherPark = Park::factory()->create();

        $response = $this->actingAs($rental)
            ->getJson('/api/dashboard/revenue?park_id=' . $otherPark->id);

        $response->assertForbidden();
    }

    // Reports tests

    public function test_report_applications_json(): void
    {
        Application::factory()->create(['park_id' => $this->park->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/applications?park_id=' . $this->park->id . '&format=json');

        $response->assertOk();
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('status', $data[0]);
    }

    public function test_report_applications_xlsx(): void
    {
        Application::factory()->create(['park_id' => $this->park->id]);

        $response = $this->actingAs($this->admin)
            ->get('/api/reports/applications?park_id=' . $this->park->id . '&format=xlsx');

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('Content-Type')
        );
    }

    public function test_report_customers_json(): void
    {
        $customer = Customer::factory()->create();
        Application::factory()->create(['park_id' => $this->park->id, 'customer_id' => $customer->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/customers?park_id=' . $this->park->id);

        $response->assertOk();
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('email', $data[0]);
    }

    public function test_report_units_json(): void
    {
        Unit::factory()->create(['park_id' => $this->park->id]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/units?park_id=' . $this->park->id);

        $response->assertOk();
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('status', $data[0]);
    }

    public function test_report_finance_json(): void
    {
        Invoice::factory()->create(['park_id' => $this->park->id, 'status' => 'paid']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/reports/finance?park_id=' . $this->park->id);

        $response->assertOk();
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('total_amount', $data[0]);
    }

    public function test_report_finance_xlsx(): void
    {
        Invoice::factory()->create(['park_id' => $this->park->id]);

        $response = $this->actingAs($this->admin)
            ->get('/api/reports/finance?park_id=' . $this->park->id . '&format=xlsx');

        $response->assertOk();
        $this->assertStringContainsString(
            'spreadsheetml',
            $response->headers->get('Content-Type')
        );
    }

    public function test_report_audit_admin_only(): void
    {
        $rental = User::factory()->create(['role' => 'rental_manager']);

        $response = $this->actingAs($rental)->getJson('/api/reports/audit');

        $response->assertForbidden();
    }

    public function test_report_audit_returns_logs(): void
    {
        \App\Models\AuditLog::create([
            'user_id'    => $this->admin->id,
            'action'     => 'update',
            'model_type' => 'Park',
            'model_id'   => 1,
            'old_values' => ['name' => 'old'],
            'new_values' => ['name' => 'new'],
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/reports/audit');

        $response->assertOk();
        $data = $response->json();
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals('update', $data[0]['action']);
    }

    public function test_report_non_admin_cannot_access_finance(): void
    {
        $worker = User::factory()->create(['role' => 'park_worker']);

        $response = $this->actingAs($worker)->getJson('/api/reports/finance');

        $response->assertForbidden();
    }
}
