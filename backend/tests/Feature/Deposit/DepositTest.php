<?php

namespace Tests\Feature\Deposit;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\Park;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Park $park;
    private Customer $customer;
    private Unit $unit;
    private Contract $contract;
    private Deposit $deposit;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin    = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->park     = Park::factory()->create();
        $unitType       = UnitType::factory()->create(['park_id' => $this->park->id]);
        $this->unit     = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $unitType->id,
            'status'       => 'rented',
        ]);
        $this->customer = Customer::factory()->create();
        $this->contract = Contract::factory()->create([
            'customer_id' => $this->customer->id,
            'unit_id'     => $this->unit->id,
            'status'      => 'active',
        ]);
        $this->deposit  = Deposit::factory()->create([
            'contract_id' => $this->contract->id,
            'customer_id' => $this->customer->id,
            'park_id'     => $this->park->id,
            'amount'      => 500.00,
            'status'      => 'pending',
        ]);

        $this->token = $this->admin->createToken('api-token')->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_list_deposits(): void
    {
        $response = $this->getJson('/api/deposits', $this->auth());
        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $this->deposit->id);
    }

    public function test_list_deposits_filter_by_park(): void
    {
        $otherPark = Park::factory()->create();
        Deposit::factory()->create(['park_id' => $otherPark->id]);

        $response = $this->getJson("/api/deposits?park_id={$this->park->id}", $this->auth());
        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('total'));
    }

    public function test_list_deposits_filter_by_status(): void
    {
        $response = $this->getJson('/api/deposits?status=pending', $this->auth());
        $response->assertStatus(200)
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_get_contract_deposit(): void
    {
        $response = $this->getJson("/api/contracts/{$this->contract->id}/deposit", $this->auth());
        $response->assertStatus(200)
            ->assertJsonPath('id', $this->deposit->id)
            ->assertJsonPath('amount', '500.00');
    }

    public function test_mark_deposit_received(): void
    {
        $response = $this->putJson("/api/deposits/{$this->deposit->id}/received", [
            'received_at' => '2026-03-01',
        ], $this->auth());

        $response->assertStatus(200)
            ->assertJsonPath('status', 'received')
            ->assertJsonStructure(['received_at']);

        $this->assertDatabaseHas('deposits', [
            'id'     => $this->deposit->id,
            'status' => 'received',
        ]);
    }

    public function test_mark_received_fails_if_not_pending(): void
    {
        $this->deposit->update(['status' => 'received']);

        $response = $this->putJson("/api/deposits/{$this->deposit->id}/received", [
            'received_at' => '2026-03-01',
        ], $this->auth());

        $response->assertStatus(422);
    }

    public function test_process_return_full(): void
    {
        $this->deposit->update(['status' => 'received']);
        $this->contract->update(['status' => 'terminated_by_customer']);

        $response = $this->postJson("/api/deposits/{$this->deposit->id}/return", [
            'return_method' => 'bank_transfer',
        ], $this->auth());

        $response->assertStatus(200)
            ->assertJsonPath('status', 'returned')
            ->assertJsonPath('return_amount', '500.00');
    }

    public function test_process_return_partial(): void
    {
        $this->deposit->update(['status' => 'received']);
        $this->contract->update(['status' => 'terminated_by_lfg']);

        $response = $this->postJson("/api/deposits/{$this->deposit->id}/return", [
            'deduction_amount' => 100,
            'deduction_reason' => 'Damage to walls',
            'return_method'    => 'bank_transfer',
        ], $this->auth());

        $response->assertStatus(200)
            ->assertJsonPath('status', 'partially_returned')
            ->assertJsonPath('return_amount', '400.00')
            ->assertJsonPath('deduction_amount', '100.00');
    }

    public function test_return_fails_if_contract_not_terminated(): void
    {
        $this->deposit->update(['status' => 'received']);
        // contract status is still 'active'

        $response = $this->postJson("/api/deposits/{$this->deposit->id}/return", [
            'return_method' => 'bank_transfer',
        ], $this->auth());

        $response->assertStatus(422);
    }

    public function test_return_fails_if_not_received(): void
    {
        $this->contract->update(['status' => 'terminated_by_customer']);
        // deposit status is still 'pending'

        $response = $this->postJson("/api/deposits/{$this->deposit->id}/return", [
            'return_method' => 'bank_transfer',
        ], $this->auth());

        $response->assertStatus(422);
    }

    public function test_mollie_payout(): void
    {
        $this->deposit->update([
            'status'        => 'returned',
            'return_amount' => 500.00,
            'return_method' => 'mollie_payout',
        ]);

        $response = $this->postJson("/api/deposits/{$this->deposit->id}/mollie-payout", [
            'customer_iban' => 'DE89370400440532013000',
        ], $this->auth());

        $response->assertStatus(200)
            ->assertJsonStructure(['mollie_payment_id', 'amount', 'customer_iban']);

        $this->assertNotNull($response->json('mollie_payment_id'));
        $this->assertDatabaseHas('deposits', [
            'id' => $this->deposit->id,
        ]);
        $this->assertNotNull(Deposit::find($this->deposit->id)->mollie_payment_id);
    }

    public function test_mollie_payout_fails_if_not_returned(): void
    {
        $this->deposit->update(['status' => 'received', 'return_method' => 'mollie_payout']);

        $response = $this->postJson("/api/deposits/{$this->deposit->id}/mollie-payout", [
            'customer_iban' => 'DE89370400440532013000',
        ], $this->auth());

        $response->assertStatus(422);
    }

    public function test_mollie_payout_fails_if_wrong_return_method(): void
    {
        $this->deposit->update([
            'status'        => 'returned',
            'return_amount' => 500.00,
            'return_method' => 'bank_transfer',
        ]);

        $response = $this->postJson("/api/deposits/{$this->deposit->id}/mollie-payout", [
            'customer_iban' => 'DE89370400440532013000',
        ], $this->auth());

        $response->assertStatus(422);
    }

    public function test_mollie_payout_idempotent(): void
    {
        $this->deposit->update([
            'status'            => 'returned',
            'return_amount'     => 500.00,
            'return_method'     => 'mollie_payout',
            'mollie_payment_id' => 'mollie-payout-existing',
        ]);

        $response = $this->postJson("/api/deposits/{$this->deposit->id}/mollie-payout", [
            'customer_iban' => 'DE89370400440532013000',
        ], $this->auth());

        $response->assertStatus(422);
    }

    public function test_unauthenticated_cannot_access_deposits(): void
    {
        $this->getJson('/api/deposits')->assertStatus(401);
    }
}
