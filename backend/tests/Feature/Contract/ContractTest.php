<?php

namespace Tests\Feature\Contract;

use App\Models\Application;
use App\Models\Contract;
use App\Models\ContractRenewal;
use App\Models\ContractSignature;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\Park;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ContractTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Park $park;
    private Customer $customer;
    private UnitType $unitType;
    private Unit $unit;
    private Application $application;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');

        $this->admin    = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->park     = Park::factory()->create();
        $this->unitType = UnitType::factory()->create(['park_id' => $this->park->id]);
        $this->unit     = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $this->unitType->id,
            'status'       => 'free',
        ]);
        $this->customer    = Customer::factory()->create();
        $this->application = Application::factory()->create([
            'park_id'      => $this->park->id,
            'customer_id'  => $this->customer->id,
            'unit_type_id' => $this->unitType->id,
            'status'       => 'completed',
        ]);

        $this->adminToken = $this->admin->createToken('api-token')->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    private function makeContract(array $attrs = []): Contract
    {
        return Contract::factory()->create(array_merge([
            'application_id' => $this->application->id,
            'customer_id'    => $this->customer->id,
            'unit_id'        => $this->unit->id,
            'status'         => 'draft',
        ], $attrs));
    }

    // --- GENERATE FROM APPLICATION ---

    public function test_unauthenticated_cannot_generate_contract(): void
    {
        $this->postJson("/api/applications/{$this->application->id}/contract", [])
            ->assertStatus(401);
    }

    public function test_generate_contract_from_application(): void
    {
        $response = $this->postJson(
            "/api/applications/{$this->application->id}/contract",
            [
                'unit_id'       => $this->unit->id,
                'start_date'    => '2026-04-01',
                'rent_amount'   => 500,
                'deposit_amount' => 1000,
            ],
            $this->auth()
        );

        $response->assertStatus(201)
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('customer_id', $this->customer->id);

        $this->assertDatabaseHas('contracts', [
            'application_id' => $this->application->id,
            'customer_id'    => $this->customer->id,
            'unit_id'        => $this->unit->id,
            'status'         => 'draft',
        ]);

        Storage::disk('s3')->assertExists($response->json('signed_pdf_path'));
    }

    // --- LIST ---

    public function test_admin_can_list_contracts(): void
    {
        $this->makeContract();
        $this->makeContract(['status' => 'active']);

        $response = $this->getJson('/api/contracts', $this->auth());

        $response->assertStatus(200)
            ->assertJsonPath('total', 2);
    }

    public function test_list_contracts_filtered_by_status(): void
    {
        $this->makeContract(['status' => 'draft']);
        $this->makeContract(['status' => 'active']);

        $response = $this->getJson('/api/contracts?status=active', $this->auth());

        $response->assertStatus(200)
            ->assertJsonPath('total', 1);
    }

    public function test_list_contracts_filtered_by_customer(): void
    {
        $other = Customer::factory()->create();
        $this->makeContract(['customer_id' => $this->customer->id]);
        Contract::factory()->create([
            'customer_id' => $other->id,
            'unit_id'     => Unit::factory()->create([
                'park_id'      => $this->park->id,
                'unit_type_id' => $this->unitType->id,
            ])->id,
        ]);

        $response = $this->getJson("/api/contracts?customer_id={$this->customer->id}", $this->auth());

        $response->assertStatus(200)
            ->assertJsonPath('total', 1);
    }

    // --- SHOW ---

    public function test_admin_can_show_contract(): void
    {
        $contract = $this->makeContract();

        $this->getJson("/api/contracts/{$contract->id}", $this->auth())
            ->assertStatus(200)
            ->assertJsonPath('id', $contract->id);
    }

    // --- UPDATE ---

    public function test_admin_can_update_contract(): void
    {
        $contract = $this->makeContract();

        $this->putJson("/api/contracts/{$contract->id}", [
            'rent_amount' => 999.99,
        ], $this->auth())
            ->assertStatus(200)
            ->assertJsonPath('rent_amount', '999.99');
    }

    // --- SEND FOR SIGNATURE ---

    public function test_send_for_signature_transitions_status(): void
    {
        $contract = $this->makeContract(['status' => 'draft']);

        $response = $this->postJson("/api/contracts/{$contract->id}/send-for-signature", [], $this->auth());

        $response->assertStatus(200)
            ->assertJsonPath('contract.status', 'awaiting_signature')
            ->assertJsonStructure(['esign_provider_id', 'sign_url']);
    }

    public function test_send_for_signature_requires_draft_status(): void
    {
        $contract = $this->makeContract(['status' => 'active']);

        $this->postJson("/api/contracts/{$contract->id}/send-for-signature", [], $this->auth())
            ->assertStatus(422);
    }

    // --- ESIGN WEBHOOK ---

    public function test_esign_webhook_signs_contract(): void
    {
        $contract = $this->makeContract(['status' => 'awaiting_signature']);

        $response = $this->postJson('/api/webhooks/esign', [
            'esign_provider_id' => 'stub-abc123',
            'contract_id'       => $contract->id,
            'signer_type'       => 'customer',
            'signer_name'       => 'Max Mustermann',
            'signed_at'         => now()->toIso8601String(),
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('contract.status', 'signed');

        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'status' => 'signed']);
        $this->assertDatabaseHas('contract_signatures', [
            'contract_id' => $contract->id,
            'signer_type' => 'customer',
            'signer_name' => 'Max Mustermann',
        ]);

        Storage::disk('s3')->assertExists($response->json('contract.signed_pdf_path'));
    }

    public function test_esign_webhook_rejects_non_awaiting_contract(): void
    {
        $contract = $this->makeContract(['status' => 'draft']);

        $this->postJson('/api/webhooks/esign', [
            'esign_provider_id' => 'stub-abc123',
            'contract_id'       => $contract->id,
            'signer_type'       => 'customer',
            'signer_name'       => 'Max Mustermann',
            'signed_at'         => now()->toIso8601String(),
        ])->assertStatus(422);
    }

    // --- ACTIVATE ---

    public function test_activate_sets_status_and_creates_deposit(): void
    {
        $contract = $this->makeContract(['status' => 'signed']);

        $response = $this->postJson("/api/contracts/{$contract->id}/activate", [], $this->auth());

        $response->assertStatus(200)
            ->assertJsonPath('contract.status', 'active');

        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'status' => 'active']);
        $this->assertDatabaseHas('units', ['id' => $this->unit->id, 'status' => 'rented']);
        $this->assertDatabaseHas('deposits', [
            'contract_id' => $contract->id,
            'customer_id' => $this->customer->id,
            'status'      => 'pending',
        ]);
    }

    public function test_activate_requires_signed_status(): void
    {
        $contract = $this->makeContract(['status' => 'draft']);

        $this->postJson("/api/contracts/{$contract->id}/activate", [], $this->auth())
            ->assertStatus(422);
    }

    // --- TERMINATE ---

    public function test_terminate_by_customer_sets_status(): void
    {
        $contract = $this->makeContract(['status' => 'active', 'notice_period_days' => 0]);

        $response = $this->postJson("/api/contracts/{$contract->id}/terminate", [
            'termination_type'        => 'customer',
            'termination_notice_date' => now()->subDay()->toDateString(),
        ], $this->auth());

        $response->assertStatus(200)
            ->assertJsonPath('status', 'terminated_by_customer');
    }

    public function test_terminate_by_lfg_sets_status(): void
    {
        $contract = $this->makeContract(['status' => 'active', 'notice_period_days' => 0]);

        $this->postJson("/api/contracts/{$contract->id}/terminate", [
            'termination_type'        => 'lfg',
            'termination_notice_date' => now()->subDay()->toDateString(),
        ], $this->auth())
            ->assertStatus(200)
            ->assertJsonPath('status', 'terminated_by_lfg');
    }

    public function test_terminate_validates_notice_period(): void
    {
        $contract = $this->makeContract(['status' => 'active', 'notice_period_days' => 30]);

        // Notice date is today, so termination would only be valid 30 days from now
        // Since terminated_at is now(), it's earlier than earliest date → 422
        $this->postJson("/api/contracts/{$contract->id}/terminate", [
            'termination_type'        => 'customer',
            'termination_notice_date' => now()->toDateString(),
        ], $this->auth())
            ->assertStatus(422);
    }

    public function test_terminate_requires_active_status(): void
    {
        $contract = $this->makeContract(['status' => 'draft']);

        $this->postJson("/api/contracts/{$contract->id}/terminate", [
            'termination_type'        => 'customer',
            'termination_notice_date' => now()->subDays(31)->toDateString(),
        ], $this->auth())
            ->assertStatus(422);
    }

    // --- RENEW ---

    public function test_renew_creates_new_contract_and_expires_old(): void
    {
        $contract = $this->makeContract(['status' => 'active']);

        $response = $this->postJson("/api/contracts/{$contract->id}/renew", [
            'start_date'  => '2027-01-01',
            'rent_amount' => 600,
        ], $this->auth());

        $response->assertStatus(201)
            ->assertJsonPath('old_contract.status', 'expired')
            ->assertJsonPath('new_contract.status', 'draft');

        $this->assertDatabaseHas('contracts', ['id' => $contract->id, 'status' => 'expired']);
        $this->assertDatabaseHas('contract_renewals', [
            'contract_id'     => $contract->id,
            'new_contract_id' => $response->json('new_contract.id'),
        ]);
    }

    public function test_renew_requires_active_status(): void
    {
        $contract = $this->makeContract(['status' => 'draft']);

        $this->postJson("/api/contracts/{$contract->id}/renew", [
            'start_date'  => '2027-01-01',
            'rent_amount' => 600,
        ], $this->auth())
            ->assertStatus(422);
    }

    public function test_unauthenticated_cannot_access_contracts(): void
    {
        $this->getJson('/api/contracts')->assertStatus(401);
    }
}
