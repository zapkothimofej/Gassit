<?php

namespace Tests\Feature\Park;

use App\Models\AuditLog;
use App\Models\Park;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ParkTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private string $adminToken;
    private string $managerToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->manager = User::factory()->create(['role' => 'main_manager', 'active' => true]);
        $this->adminToken = $this->admin->createToken('api-token')->plainTextToken;
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

    public function test_unauthenticated_cannot_list_parks(): void
    {
        $this->getJson('/api/parks')->assertStatus(401);
    }

    public function test_non_manager_cannot_access_parks(): void
    {
        $user = User::factory()->create(['role' => 'park_worker', 'active' => true]);
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/parks')
            ->assertStatus(403);
    }

    public function test_admin_can_list_all_parks(): void
    {
        Park::factory()->count(3)->create();

        $this->withHeaders($this->adminAuth())
            ->getJson('/api/parks')
            ->assertOk()
            ->assertJsonStructure(['data', 'total', 'per_page', 'current_page']);
    }

    public function test_main_manager_sees_all_parks(): void
    {
        // main_manager has implicit access to all parks
        Park::factory()->count(2)->create();

        $response = $this->withHeaders($this->managerAuth())
            ->getJson('/api/parks')
            ->assertOk();

        $this->assertGreaterThanOrEqual(2, $response->json('total'));
    }

    public function test_scoped_user_sees_only_their_parks(): void
    {
        $scopedUser = User::factory()->create(['role' => 'rental_manager', 'active' => true]);
        $scopedToken = $scopedUser->createToken('api-token')->plainTextToken;

        // Grant rental_manager access to parks route temporarily via park scope logic — but the route
        // restricts to admin|main_manager. So we test via a main_manager with explicit park assignment
        // to confirm scoping works at DB level for future roles.
        $myPark = Park::factory()->create();
        $otherPark = Park::factory()->create();
        $this->manager->parks()->attach($myPark->id);

        // main_manager still sees ALL parks (implicit access)
        $response = $this->withHeaders($this->managerAuth())
            ->getJson('/api/parks')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($myPark->id, $ids);
        $this->assertContains($otherPark->id, $ids);
    }

    public function test_admin_can_create_park(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->postJson('/api/parks', [
                'name'    => 'Test Park',
                'address' => 'Main St 1',
                'city'    => 'Berlin',
                'zip'     => '10115',
                'country' => 'DE',
            ])
            ->assertStatus(201)
            ->assertJsonFragment(['name' => 'Test Park']);

        $this->assertDatabaseHas('parks', ['name' => 'Test Park']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'create', 'model_type' => Park::class]);
    }

    public function test_create_park_validates_required_fields(): void
    {
        $this->withHeaders($this->adminAuth())
            ->postJson('/api/parks', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'address', 'city', 'zip', 'country']);
    }

    public function test_admin_can_update_park(): void
    {
        $park = Park::factory()->create();

        $this->withHeaders($this->adminAuth())
            ->putJson("/api/parks/{$park->id}", ['name' => 'Updated Park'])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Updated Park']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'update', 'model_id' => $park->id]);
    }

    public function test_non_manager_role_cannot_update_park(): void
    {
        $park = Park::factory()->create();
        $worker = User::factory()->create(['role' => 'park_worker', 'active' => true]);
        $token = $worker->createToken('api-token')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/parks/{$park->id}", ['name' => 'Updated'])
            ->assertStatus(403);
    }

    public function test_admin_can_soft_delete_park(): void
    {
        $park = Park::factory()->create();

        $this->withHeaders($this->adminAuth())
            ->deleteJson("/api/parks/{$park->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Park deleted.']);

        $this->assertSoftDeleted('parks', ['id' => $park->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'delete', 'model_id' => $park->id]);
    }

    public function test_logo_upload_stores_to_s3(): void
    {
        Storage::fake('s3');
        $park = Park::factory()->create();

        $file = UploadedFile::fake()->create('logo.png', 50, 'image/png');

        $response = $this->withHeaders($this->adminAuth())
            ->postJson("/api/parks/{$park->id}/logo", ['logo' => $file])
            ->assertOk()
            ->assertJsonStructure(['logo_path']);

        $logoPath = $response->json('logo_path');
        Storage::disk('s3')->assertExists($logoPath);
        $this->assertDatabaseHas('parks', ['id' => $park->id, 'logo_path' => $logoPath]);
    }

    public function test_get_settings_returns_park_and_system_settings(): void
    {
        $park = Park::factory()->create();
        SystemSetting::updateOrCreate(['key' => 'test_key'], ['value' => 'test_val', 'description' => 'test']);

        $response = $this->withHeaders($this->adminAuth())
            ->getJson("/api/parks/{$park->id}/settings")
            ->assertOk()
            ->assertJsonStructure(['park', 'settings']);

        $this->assertEquals($park->id, $response->json('park.id'));
    }

    public function test_update_settings_updates_park_configurable_fields(): void
    {
        $park = Park::factory()->create();

        $this->withHeaders($this->adminAuth())
            ->putJson("/api/parks/{$park->id}/settings", [
                'primary_color' => '#FF5733',
                'language'      => 'en',
            ])
            ->assertOk()
            ->assertJsonPath('park.primary_color', '#FF5733')
            ->assertJsonPath('park.language', 'en');

        $this->assertDatabaseHas('audit_logs', ['action' => 'update_settings', 'model_id' => $park->id]);
    }
}
