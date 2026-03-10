<?php

namespace Tests\Feature\UnitType;

use App\Models\Park;
use App\Models\Unit;
use App\Models\UnitFeature;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UnitTypeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private Park $park;
    private string $adminToken;
    private string $managerToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin   = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->manager = User::factory()->create(['role' => 'main_manager', 'active' => true]);
        $this->park    = Park::factory()->create();

        $this->adminToken   = $this->admin->createToken('api-token')->plainTextToken;
        $this->managerToken = $this->manager->createToken('api-token')->plainTextToken;
    }

    private function adminAuth(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    private function managerAuth(): array
    {
        return ['Authorization' => "Bearer {$this->managerToken}"];
    }

    public function test_unauthenticated_cannot_list_unit_types(): void
    {
        $this->getJson("/api/parks/{$this->park->id}/unit-types")->assertStatus(401);
    }

    public function test_unauthorized_role_cannot_list_unit_types(): void
    {
        $user  = User::factory()->create(['role' => 'park_worker', 'active' => true]);
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/parks/{$this->park->id}/unit-types")
            ->assertStatus(403);
    }

    public function test_admin_can_list_unit_types(): void
    {
        UnitType::factory()->count(2)->create(['park_id' => $this->park->id]);

        $this->withHeaders($this->adminAuth())
            ->getJson("/api/parks/{$this->park->id}/unit-types")
            ->assertOk()
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_admin_can_create_unit_type(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->postJson("/api/parks/{$this->park->id}/unit-types", [
                'name'           => 'Standard Box',
                'description'    => 'A standard storage box',
                'base_rent'      => 150.00,
                'deposit_amount' => 300.00,
                'size_m2'        => 15.5,
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('unit_types', [
            'park_id' => $this->park->id,
            'name'    => 'Standard Box',
        ]);

        $response->assertJsonStructure(['id', 'name', 'base_rent', 'features']);
    }

    public function test_manager_can_create_unit_type(): void
    {
        $this->withHeaders($this->managerAuth())
            ->postJson("/api/parks/{$this->park->id}/unit-types", [
                'name'           => 'Large Box',
                'base_rent'      => 250.00,
                'deposit_amount' => 500.00,
                'size_m2'        => 30.0,
            ])
            ->assertStatus(201);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->withHeaders($this->adminAuth())
            ->postJson("/api/parks/{$this->park->id}/unit-types", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'base_rent', 'deposit_amount', 'size_m2']);
    }

    public function test_admin_can_update_unit_type(): void
    {
        $unitType = UnitType::factory()->create(['park_id' => $this->park->id]);

        $this->withHeaders($this->adminAuth())
            ->putJson("/api/parks/{$this->park->id}/unit-types/{$unitType->id}", [
                'name' => 'Updated Name',
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);
    }

    public function test_admin_can_delete_unit_type(): void
    {
        $unitType = UnitType::factory()->create(['park_id' => $this->park->id]);

        $this->withHeaders($this->adminAuth())
            ->deleteJson("/api/parks/{$this->park->id}/unit-types/{$unitType->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Unit type deleted.']);

        $this->assertDatabaseMissing('unit_types', ['id' => $unitType->id]);
    }

    public function test_upload_floor_plan(): void
    {
        Storage::fake('s3');

        $unitType = UnitType::factory()->create(['park_id' => $this->park->id]);

        $file = UploadedFile::fake()->create('floor-plan.pdf', 100, 'application/pdf');

        $response = $this->withHeaders($this->adminAuth())
            ->postJson("/api/unit-types/{$unitType->id}/floor-plan", [
                'floor_plan' => $file,
            ])
            ->assertOk()
            ->assertJsonStructure(['floor_plan_path']);

        $this->assertDatabaseHas('unit_types', [
            'id'             => $unitType->id,
            'floor_plan_path' => $response->json('floor_plan_path'),
        ]);
    }

    public function test_sync_features(): void
    {
        $unitType = UnitType::factory()->create(['park_id' => $this->park->id]);

        $this->withHeaders($this->adminAuth())
            ->postJson("/api/unit-types/{$unitType->id}/features", [
                'features' => ['Wi-Fi', 'Climate Control', 'Security Camera'],
            ])
            ->assertOk()
            ->assertJsonStructure(['features']);

        $this->assertCount(3, UnitFeature::where('unit_type_id', $unitType->id)->get());
    }

    public function test_sync_features_replaces_existing(): void
    {
        $unitType = UnitType::factory()->create(['park_id' => $this->park->id]);
        UnitFeature::create(['unit_type_id' => $unitType->id, 'feature' => 'Old Feature']);

        $this->withHeaders($this->adminAuth())
            ->postJson("/api/unit-types/{$unitType->id}/features", [
                'features' => ['New Feature'],
            ])
            ->assertOk();

        $this->assertCount(1, UnitFeature::where('unit_type_id', $unitType->id)->get());
        $this->assertDatabaseHas('unit_types', ['id' => $unitType->id]);
        $this->assertDatabaseMissing('unit_features', ['unit_type_id' => $unitType->id, 'feature' => 'Old Feature']);
    }

    public function test_availability_returns_free_unit_count(): void
    {
        $unitType = UnitType::factory()->create(['park_id' => $this->park->id]);

        Unit::factory()->count(3)->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $unitType->id,
            'status'       => 'free',
        ]);

        Unit::factory()->count(2)->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $unitType->id,
            'status'       => 'rented',
        ]);

        $this->withHeaders($this->adminAuth())
            ->getJson("/api/unit-types/{$unitType->id}/availability")
            ->assertOk()
            ->assertJson([
                'unit_type_id' => $unitType->id,
                'free_units'   => 3,
            ]);
    }
}
