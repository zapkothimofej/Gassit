<?php

namespace Tests\Feature\Pricing;

use App\Models\InsuranceOption;
use App\Models\Park;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsuranceOptionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Park $park;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->park  = Park::factory()->create();
        $this->token = $this->admin->createToken('api-token')->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_unauthenticated_cannot_list_insurance_options(): void
    {
        $this->getJson("/api/parks/{$this->park->id}/insurance-options")->assertStatus(401);
    }

    public function test_admin_can_list_insurance_options(): void
    {
        InsuranceOption::create([
            'park_id'         => $this->park->id,
            'name'            => 'Basic Cover',
            'provider'        => 'Allianz',
            'monthly_premium' => 9.99,
            'coverage_amount' => 5000,
        ]);

        $this->withHeaders($this->auth())
            ->getJson("/api/parks/{$this->park->id}/insurance-options")
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_admin_can_create_insurance_option(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/parks/{$this->park->id}/insurance-options", [
                'name'            => 'Premium Cover',
                'provider'        => 'Zurich',
                'monthly_premium' => 19.99,
                'coverage_amount' => 10000,
                'active'          => true,
            ])
            ->assertStatus(201)
            ->assertJsonFragment(['name' => 'Premium Cover']);

        $this->assertDatabaseHas('insurance_options', [
            'park_id'  => $this->park->id,
            'provider' => 'Zurich',
        ]);
    }

    public function test_admin_can_update_insurance_option(): void
    {
        $option = InsuranceOption::create([
            'park_id'         => $this->park->id,
            'name'            => 'Old Option',
            'provider'        => 'Old Provider',
            'monthly_premium' => 5.00,
            'coverage_amount' => 1000,
        ]);

        $this->withHeaders($this->auth())
            ->putJson("/api/parks/{$this->park->id}/insurance-options/{$option->id}", [
                'name'            => 'Updated Option',
                'monthly_premium' => 7.50,
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated Option']);
    }

    public function test_admin_can_delete_insurance_option(): void
    {
        $option = InsuranceOption::create([
            'park_id'         => $this->park->id,
            'name'            => 'To Delete',
            'provider'        => 'Provider',
            'monthly_premium' => 5.00,
            'coverage_amount' => 1000,
        ]);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/parks/{$this->park->id}/insurance-options/{$option->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Insurance option deleted.']);

        $this->assertDatabaseMissing('insurance_options', ['id' => $option->id]);
    }

    public function test_get_insurance_options_for_unit_type(): void
    {
        $unitType = UnitType::factory()->create(['park_id' => $this->park->id]);

        // Park-wide option
        InsuranceOption::create([
            'park_id'         => $this->park->id,
            'name'            => 'Park Wide Insurance',
            'provider'        => 'Provider A',
            'monthly_premium' => 9.99,
            'coverage_amount' => 5000,
            'active'          => true,
        ]);

        // Unit-type-specific option
        InsuranceOption::create([
            'park_id'         => $this->park->id,
            'unit_type_id'    => $unitType->id,
            'name'            => 'Unit Type Insurance',
            'provider'        => 'Provider B',
            'monthly_premium' => 14.99,
            'coverage_amount' => 8000,
            'active'          => true,
        ]);

        // Inactive — should not appear
        InsuranceOption::create([
            'park_id'         => $this->park->id,
            'name'            => 'Inactive Insurance',
            'provider'        => 'Provider C',
            'monthly_premium' => 99.99,
            'coverage_amount' => 50000,
            'active'          => false,
        ]);

        $this->withHeaders($this->auth())
            ->getJson("/api/unit-types/{$unitType->id}/insurance-options")
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_unauthorized_role_cannot_manage_insurance_options(): void
    {
        $worker = User::factory()->create(['role' => 'park_worker', 'active' => true]);
        $workerToken = $worker->createToken('api-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$workerToken}")
            ->getJson("/api/parks/{$this->park->id}/insurance-options")
            ->assertStatus(403);
    }
}
