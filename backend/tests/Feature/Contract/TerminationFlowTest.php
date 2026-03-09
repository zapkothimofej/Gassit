<?php

namespace Tests\Feature\Contract;

use App\Jobs\NotifyWaitingListEntries;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\DamageReport;
use App\Models\Deposit;
use App\Models\Invoice;
use App\Models\Park;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TerminationFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Park $park;
    private Customer $customer;
    private Unit $unit;
    private Contract $contract;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        Queue::fake();

        $this->admin    = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->park     = Park::factory()->create(['name' => 'TestPark']);
        $unitType       = UnitType::factory()->create(['park_id' => $this->park->id]);
        $this->unit     = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $unitType->id,
            'status'       => 'rented',
        ]);
        $this->customer = Customer::factory()->create();
        $this->contract = Contract::factory()->create([
            'customer_id'        => $this->customer->id,
            'unit_id'            => $this->unit->id,
            'status'             => 'active',
            'rent_amount'        => 300.00,
            'notice_period_days' => 0,
            'start_date'         => now()->subMonths(3)->toDateString(),
        ]);

        $this->adminToken = $this->admin->createToken('test')->plainTextToken;
    }

    public function test_terminate_creates_prorated_final_invoice(): void
    {
        $response = $this->withToken($this->adminToken)
            ->postJson("/api/contracts/{$this->contract->id}/terminate", [
                'termination_type'        => 'customer',
                'termination_notice_date' => now()->toDateString(),
            ]);

        $response->assertOk();

        $billingMonth = now()->format('Y-m') . '-final';
        $this->assertDatabaseHas('invoices', [
            'contract_id'   => $this->contract->id,
            'billing_month' => $billingMonth,
            'status'        => 'draft',
        ]);

        $invoice = Invoice::where('contract_id', $this->contract->id)
            ->where('billing_month', $billingMonth)->first();

        $this->assertNotNull($invoice);
        $this->assertGreaterThan(0, (float) $invoice->total_amount);
        $this->assertLessThanOrEqual(300.00, (float) $invoice->total_amount);
    }

    public function test_terminate_sets_unit_to_maintenance(): void
    {
        $this->withToken($this->adminToken)
            ->postJson("/api/contracts/{$this->contract->id}/terminate", [
                'termination_type'        => 'customer',
                'termination_notice_date' => now()->toDateString(),
            ])
            ->assertOk();

        $this->assertDatabaseHas('units', [
            'id'     => $this->unit->id,
            'status' => 'maintenance',
        ]);
    }

    public function test_terminate_creates_termination_inspection_damage_report(): void
    {
        $this->withToken($this->adminToken)
            ->postJson("/api/contracts/{$this->contract->id}/terminate", [
                'termination_type'        => 'customer',
                'termination_notice_date' => now()->toDateString(),
            ])
            ->assertOk();

        $this->assertDatabaseHas('damage_reports', [
            'unit_id'                   => $this->unit->id,
            'contract_id'               => $this->contract->id,
            'is_termination_inspection' => true,
            'status'                    => 'reported',
        ]);
    }

    public function test_resolving_termination_inspection_sets_unit_free_and_notifies_waiting_list(): void
    {
        // Terminate to create inspection report
        $this->withToken($this->adminToken)
            ->postJson("/api/contracts/{$this->contract->id}/terminate", [
                'termination_type'        => 'customer',
                'termination_notice_date' => now()->toDateString(),
            ])
            ->assertOk();

        $report = DamageReport::where('unit_id', $this->unit->id)
            ->where('is_termination_inspection', true)->first();

        $this->assertNotNull($report);

        // Transition through required states to reach 'resolved'
        $this->withToken($this->adminToken)
            ->putJson("/api/damage-reports/{$report->id}/status", ['status' => 'in_assessment'])
            ->assertOk();

        $this->withToken($this->adminToken)
            ->putJson("/api/damage-reports/{$report->id}/status", ['status' => 'repair_ordered'])
            ->assertOk();

        $this->withToken($this->adminToken)
            ->putJson("/api/damage-reports/{$report->id}/status", ['status' => 'in_repair'])
            ->assertOk();

        $this->withToken($this->adminToken)
            ->putJson("/api/damage-reports/{$report->id}/status", ['status' => 'resolved'])
            ->assertOk();

        $this->assertDatabaseHas('units', [
            'id'     => $this->unit->id,
            'status' => 'free',
        ]);

        Queue::assertPushed(NotifyWaitingListEntries::class, function ($job) {
            return $job->unitId === $this->unit->id;
        });
    }

    public function test_deposit_return_blocked_if_final_invoice_unpaid(): void
    {
        $deposit = Deposit::factory()->create([
            'contract_id' => $this->contract->id,
            'customer_id' => $this->customer->id,
            'park_id'     => $this->park->id,
            'status'      => 'held',
            'amount'      => 600.00,
        ]);

        $this->withToken($this->adminToken)
            ->postJson("/api/contracts/{$this->contract->id}/terminate", [
                'termination_type'        => 'customer',
                'termination_notice_date' => now()->toDateString(),
            ])
            ->assertOk();

        $response = $this->withToken($this->adminToken)
            ->postJson("/api/deposits/{$deposit->id}/return", [
                'return_method' => 'bank_transfer',
            ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Cannot return deposit: final invoice is not yet paid.']);
    }

    public function test_deposit_return_allowed_if_final_invoice_waived(): void
    {
        $deposit = Deposit::factory()->create([
            'contract_id' => $this->contract->id,
            'customer_id' => $this->customer->id,
            'park_id'     => $this->park->id,
            'status'      => 'held',
            'amount'      => 600.00,
        ]);

        $this->withToken($this->adminToken)
            ->postJson("/api/contracts/{$this->contract->id}/terminate", [
                'termination_type'        => 'customer',
                'termination_notice_date' => now()->toDateString(),
            ])
            ->assertOk();

        $this->contract->update(['final_invoice_waived' => true]);

        $response = $this->withToken($this->adminToken)
            ->postJson("/api/deposits/{$deposit->id}/return", [
                'return_method' => 'bank_transfer',
            ]);

        $response->assertOk();
    }

    public function test_deposit_return_allowed_if_final_invoice_paid(): void
    {
        $deposit = Deposit::factory()->create([
            'contract_id' => $this->contract->id,
            'customer_id' => $this->customer->id,
            'park_id'     => $this->park->id,
            'status'      => 'held',
            'amount'      => 600.00,
        ]);

        $this->withToken($this->adminToken)
            ->postJson("/api/contracts/{$this->contract->id}/terminate", [
                'termination_type'        => 'customer',
                'termination_notice_date' => now()->toDateString(),
            ])
            ->assertOk();

        // Mark final invoice as paid
        Invoice::where('contract_id', $this->contract->id)
            ->where('billing_month', 'like', '%-final')
            ->update(['status' => 'paid']);

        $response = $this->withToken($this->adminToken)
            ->postJson("/api/deposits/{$deposit->id}/return", [
                'return_method' => 'bank_transfer',
            ]);

        $response->assertOk();
    }
}
