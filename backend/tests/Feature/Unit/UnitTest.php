<?php

namespace Tests\Feature\Unit;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Park;
use App\Models\Unit;
use App\Models\UnitPhoto;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UnitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private Park $park;
    private UnitType $unitType;
    private string $adminToken;
    private string $managerToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin     = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->manager   = User::factory()->create(['role' => 'rental_manager', 'active' => true]);
        $this->park      = Park::factory()->create();
        $this->unitType  = UnitType::factory()->create(['park_id' => $this->park->id]);
        $this->manager->parks()->attach($this->park->id);

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

    // --- LIST ---

    public function test_unauthenticated_cannot_list_units(): void
    {
        $this->getJson("/api/parks/{$this->park->id}/units")->assertStatus(401);
    }

    public function test_admin_can_list_units(): void
    {
        Unit::factory()->count(3)->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $this->unitType->id,
        ]);

        $this->withHeaders($this->adminAuth())
            ->getJson("/api/parks/{$this->park->id}/units")
            ->assertOk()
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_list_units_filter_by_status(): void
    {
        Unit::factory()->create(['park_id' => $this->park->id, 'unit_type_id' => $this->unitType->id, 'status' => 'free']);
        Unit::factory()->create(['park_id' => $this->park->id, 'unit_type_id' => $this->unitType->id, 'status' => 'rented']);

        $response = $this->withHeaders($this->adminAuth())
            ->getJson("/api/parks/{$this->park->id}/units?status=free")
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    public function test_list_units_filter_by_unit_type(): void
    {
        $otherType = UnitType::factory()->create(['park_id' => $this->park->id]);
        Unit::factory()->create(['park_id' => $this->park->id, 'unit_type_id' => $this->unitType->id]);
        Unit::factory()->create(['park_id' => $this->park->id, 'unit_type_id' => $otherType->id]);

        $response = $this->withHeaders($this->adminAuth())
            ->getJson("/api/parks/{$this->park->id}/units?unit_type_id={$this->unitType->id}")
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    // --- STORE ---

    public function test_admin_can_create_unit(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->postJson("/api/parks/{$this->park->id}/units", [
                'unit_type_id' => $this->unitType->id,
                'unit_number'  => 'A-101',
                'floor'        => 1,
                'size_m2'      => 25.0,
            ])
            ->assertStatus(201)
            ->assertJsonFragment(['unit_number' => 'A-101', 'status' => 'free']);

        $this->assertDatabaseHas('units', ['unit_number' => 'A-101', 'park_id' => $this->park->id]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->withHeaders($this->adminAuth())
            ->postJson("/api/parks/{$this->park->id}/units", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unit_type_id', 'unit_number']);
    }

    public function test_rental_manager_can_create_unit(): void
    {
        $this->withHeaders($this->managerAuth())
            ->postJson("/api/parks/{$this->park->id}/units", [
                'unit_type_id' => $this->unitType->id,
                'unit_number'  => 'B-202',
                'size_m2'      => 20.0,
            ])
            ->assertStatus(201);
    }

    // --- UPDATE ---

    public function test_admin_can_update_unit(): void
    {
        $unit = Unit::factory()->create(['park_id' => $this->park->id, 'unit_type_id' => $this->unitType->id]);

        $this->withHeaders($this->adminAuth())
            ->putJson("/api/units/{$unit->id}", ['unit_number' => 'X-999'])
            ->assertOk()
            ->assertJsonFragment(['unit_number' => 'X-999']);
    }

    // --- DELETE ---

    public function test_admin_can_soft_delete_unit(): void
    {
        $unit = Unit::factory()->create(['park_id' => $this->park->id, 'unit_type_id' => $this->unitType->id]);

        $this->withHeaders($this->adminAuth())
            ->deleteJson("/api/units/{$unit->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Unit deleted.']);

        $this->assertSoftDeleted('units', ['id' => $unit->id]);
    }

    // --- STATUS TRANSITIONS ---

    public function test_valid_status_transition_free_to_reserved(): void
    {
        $unit = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $this->unitType->id,
            'status'       => 'free',
        ]);

        $this->withHeaders($this->adminAuth())
            ->putJson("/api/units/{$unit->id}/status", ['status' => 'reserved'])
            ->assertOk()
            ->assertJsonFragment(['status' => 'reserved']);
    }

    public function test_reserved_to_rented_requires_active_contract(): void
    {
        $unit = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $this->unitType->id,
            'status'       => 'reserved',
        ]);

        $this->withHeaders($this->adminAuth())
            ->putJson("/api/units/{$unit->id}/status", ['status' => 'rented'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => "Cannot set status to 'rented' without an active contract."]);
    }

    public function test_reserved_to_rented_succeeds_with_active_contract(): void
    {
        $unit     = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $this->unitType->id,
            'status'       => 'reserved',
        ]);
        $customer = Customer::factory()->create();
        Contract::factory()->create(['unit_id' => $unit->id, 'customer_id' => $customer->id, 'status' => 'active']);

        $this->withHeaders($this->adminAuth())
            ->putJson("/api/units/{$unit->id}/status", ['status' => 'rented'])
            ->assertOk()
            ->assertJsonFragment(['status' => 'rented']);
    }

    public function test_rented_to_free_requires_terminated_contract(): void
    {
        $unit = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $this->unitType->id,
            'status'       => 'rented',
        ]);

        $this->withHeaders($this->adminAuth())
            ->putJson("/api/units/{$unit->id}/status", ['status' => 'free'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => "Cannot set status to 'free' without a terminated contract."]);
    }

    public function test_rented_to_free_succeeds_with_terminated_contract(): void
    {
        $unit     = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $this->unitType->id,
            'status'       => 'rented',
        ]);
        $customer = Customer::factory()->create();
        Contract::factory()->create(['unit_id' => $unit->id, 'customer_id' => $customer->id, 'status' => 'terminated_by_customer']);

        $this->withHeaders($this->adminAuth())
            ->putJson("/api/units/{$unit->id}/status", ['status' => 'free'])
            ->assertOk()
            ->assertJsonFragment(['status' => 'free']);
    }

    public function test_invalid_status_transition_rejected(): void
    {
        $unit = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $this->unitType->id,
            'status'       => 'free',
        ]);

        $this->withHeaders($this->adminAuth())
            ->putJson("/api/units/{$unit->id}/status", ['status' => 'rented'])
            ->assertStatus(422);
    }

    // --- PHOTOS ---

    public function test_upload_photo(): void
    {
        Storage::fake('s3');

        $unit = Unit::factory()->create(['park_id' => $this->park->id, 'unit_type_id' => $this->unitType->id]);
        $file = UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg');

        $this->withHeaders($this->adminAuth())
            ->postJson("/api/units/{$unit->id}/photos", ['photo' => $file, 'caption' => 'Front view'])
            ->assertStatus(201)
            ->assertJsonStructure(['id', 'path', 'caption']);

        $this->assertDatabaseHas('unit_photos', ['unit_id' => $unit->id, 'caption' => 'Front view']);
    }

    public function test_delete_photo(): void
    {
        Storage::fake('s3');

        $unit  = Unit::factory()->create(['park_id' => $this->park->id, 'unit_type_id' => $this->unitType->id]);
        $photo = UnitPhoto::create([
            'unit_id'    => $unit->id,
            'path'       => 'units/1/photos/test.jpg',
            'caption'    => null,
            'sort_order' => 0,
        ]);

        $this->withHeaders($this->adminAuth())
            ->deleteJson("/api/units/{$unit->id}/photos/{$photo->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Photo deleted.']);

        $this->assertDatabaseMissing('unit_photos', ['id' => $photo->id]);
    }

    // --- HISTORY ---

    public function test_unit_history_returns_past_contracts(): void
    {
        $unit     = Unit::factory()->create(['park_id' => $this->park->id, 'unit_type_id' => $this->unitType->id]);
        $customer = Customer::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);

        Contract::factory()->create([
            'unit_id'     => $unit->id,
            'customer_id' => $customer->id,
            'status'      => 'terminated_by_customer',
        ]);

        $response = $this->withHeaders($this->adminAuth())
            ->getJson("/api/units/{$unit->id}/history")
            ->assertOk();

        $this->assertCount(1, $response->json());
        $this->assertStringContainsString('John', $response->json('0.customer_name'));
    }

    public function test_unit_history_empty_when_no_contracts(): void
    {
        $unit = Unit::factory()->create(['park_id' => $this->park->id, 'unit_type_id' => $this->unitType->id]);

        $this->withHeaders($this->adminAuth())
            ->getJson("/api/units/{$unit->id}/history")
            ->assertOk()
            ->assertJson([]);
    }
}
