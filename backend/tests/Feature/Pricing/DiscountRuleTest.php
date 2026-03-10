<?php

namespace Tests\Feature\Pricing;

use App\Models\DiscountRule;
use App\Models\Park;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscountRuleTest extends TestCase
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

    public function test_unauthenticated_cannot_list_discount_rules(): void
    {
        $this->getJson("/api/parks/{$this->park->id}/discount-rules")->assertStatus(401);
    }

    public function test_admin_can_list_discount_rules(): void
    {
        DiscountRule::create([
            'park_id' => $this->park->id,
            'name' => 'Early Bird',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'applies_from_month' => 1,
        ]);

        $this->withHeaders($this->auth())
            ->getJson("/api/parks/{$this->park->id}/discount-rules")
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_admin_can_create_discount_rule(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson("/api/parks/{$this->park->id}/discount-rules", [
                'name'               => 'Long Stay Discount',
                'discount_type'      => 'percentage',
                'discount_value'     => 15,
                'applies_from_month' => 6,
                'applies_to_month'   => 12,
                'active'             => true,
            ])
            ->assertStatus(201)
            ->assertJsonFragment(['name' => 'Long Stay Discount']);

        $this->assertDatabaseHas('discount_rules', [
            'park_id'       => $this->park->id,
            'discount_type' => 'percentage',
        ]);
    }

    public function test_admin_can_update_discount_rule(): void
    {
        $rule = DiscountRule::create([
            'park_id' => $this->park->id,
            'name' => 'Old Rule',
            'discount_type' => 'fixed',
            'discount_value' => 20,
            'applies_from_month' => 3,
        ]);

        $this->withHeaders($this->auth())
            ->putJson("/api/parks/{$this->park->id}/discount-rules/{$rule->id}", [
                'name' => 'Updated Rule',
                'discount_value' => 25,
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated Rule']);
    }

    public function test_admin_can_delete_discount_rule(): void
    {
        $rule = DiscountRule::create([
            'park_id' => $this->park->id,
            'name' => 'To Delete',
            'discount_type' => 'fixed',
            'discount_value' => 10,
            'applies_from_month' => 1,
        ]);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/parks/{$this->park->id}/discount-rules/{$rule->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Discount rule deleted.']);

        $this->assertDatabaseMissing('discount_rules', ['id' => $rule->id]);
    }

    public function test_get_discount_rules_for_unit_type(): void
    {
        $unitType = UnitType::factory()->create(['park_id' => $this->park->id]);

        // Park-wide rule (no unit_type_id)
        DiscountRule::create([
            'park_id' => $this->park->id,
            'name' => 'Park Wide',
            'discount_type' => 'percentage',
            'discount_value' => 5,
            'applies_from_month' => 1,
            'active' => true,
        ]);

        // Unit-type-specific rule
        DiscountRule::create([
            'park_id' => $this->park->id,
            'unit_type_id' => $unitType->id,
            'name' => 'Unit Type Specific',
            'discount_type' => 'fixed',
            'discount_value' => 10,
            'applies_from_month' => 3,
            'active' => true,
        ]);

        // Inactive rule — should not appear
        DiscountRule::create([
            'park_id' => $this->park->id,
            'name' => 'Inactive',
            'discount_type' => 'fixed',
            'discount_value' => 50,
            'applies_from_month' => 1,
            'active' => false,
        ]);

        $this->withHeaders($this->auth())
            ->getJson("/api/unit-types/{$unitType->id}/discount-rules")
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_unauthorized_role_cannot_manage_discount_rules(): void
    {
        $worker = User::factory()->create(['role' => 'park_worker', 'active' => true]);
        $workerToken = $worker->createToken('api-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$workerToken}")
            ->getJson("/api/parks/{$this->park->id}/discount-rules")
            ->assertStatus(403);
    }
}
