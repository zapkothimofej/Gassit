<?php

namespace Tests\Feature\Reference;

use App\Models\LlmAccessCode;
use App\Models\Park;
use App\Models\ReferenceItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenceItemTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $rentalManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin         = User::factory()->create(['role' => 'admin']);
        $this->rentalManager = User::factory()->create(['role' => 'rental_manager']);
    }

    // --- Reference Items ---

    public function test_can_list_reference_items(): void
    {
        ReferenceItem::create(['category' => 'country', 'value' => 'DE', 'label' => 'Germany', 'sort_order' => 1, 'active' => true]);
        ReferenceItem::create(['category' => 'city', 'value' => 'berlin', 'label' => 'Berlin', 'sort_order' => 1, 'active' => true]);

        $response = $this->actingAs($this->rentalManager)->getJson('/api/reference-items');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, count($response->json()));
    }

    public function test_can_filter_reference_items_by_category(): void
    {
        ReferenceItem::create(['category' => 'country', 'value' => 'DE', 'label' => 'Germany', 'sort_order' => 1, 'active' => true]);
        ReferenceItem::create(['category' => 'city', 'value' => 'berlin', 'label' => 'Berlin', 'sort_order' => 1, 'active' => true]);

        $response = $this->actingAs($this->rentalManager)->getJson('/api/reference-items?category=country');

        $response->assertOk();
        foreach ($response->json() as $item) {
            $this->assertEquals('country', $item['category']);
        }
    }

    public function test_admin_can_create_reference_item(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/reference-items', [
            'category'   => 'termination_reason',
            'value'      => 'non_payment',
            'label'      => 'Non Payment',
            'sort_order' => 5,
            'active'     => true,
        ]);

        $response->assertCreated();
        $response->assertJsonFragment(['value' => 'non_payment', 'category' => 'termination_reason']);
        $this->assertDatabaseHas('reference_items', ['value' => 'non_payment']);
    }

    public function test_non_admin_cannot_create_reference_item(): void
    {
        $response = $this->actingAs($this->rentalManager)->postJson('/api/reference-items', [
            'category' => 'country',
            'value'    => 'FR',
            'label'    => 'France',
        ]);

        $response->assertForbidden();
    }

    public function test_admin_can_update_reference_item(): void
    {
        $item = ReferenceItem::create(['category' => 'city', 'value' => 'hamburg', 'label' => 'Hamburg', 'sort_order' => 1, 'active' => true]);

        $response = $this->actingAs($this->admin)->putJson("/api/reference-items/{$item->id}", [
            'label' => 'Hamburg City',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['label' => 'Hamburg City']);
    }

    public function test_admin_can_deactivate_reference_item(): void
    {
        $item = ReferenceItem::create(['category' => 'city', 'value' => 'dortmund', 'label' => 'Dortmund', 'sort_order' => 1, 'active' => true]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/reference-items/{$item->id}");

        $response->assertOk();
        $this->assertDatabaseHas('reference_items', ['id' => $item->id, 'active' => false]);
    }

    public function test_create_reference_item_validates_category(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/reference-items', [
            'category' => 'invalid_category',
            'value'    => 'test',
            'label'    => 'Test',
        ]);

        $response->assertUnprocessable();
    }

    // --- LLM Access Codes ---

    public function test_can_list_access_codes_for_park(): void
    {
        $park = Park::factory()->create();
        $this->admin->parks()->attach($park->id);

        LlmAccessCode::create(['park_id' => $park->id, 'code' => 'CODE123', 'description' => 'Test', 'valid_from' => now()->toDateString(), 'active' => true]);

        $response = $this->actingAs($this->admin)->getJson("/api/parks/{$park->id}/access-codes");

        $response->assertOk();
        $response->assertJsonFragment(['code' => 'CODE123']);
    }

    public function test_can_create_access_code(): void
    {
        $park = Park::factory()->create();

        $response = $this->actingAs($this->admin)->postJson("/api/parks/{$park->id}/access-codes", [
            'code'        => 'ACCESS001',
            'description' => 'Main entrance',
            'valid_from'  => now()->toDateString(),
            'valid_to'    => now()->addMonths(3)->toDateString(),
            'active'      => true,
        ]);

        $response->assertCreated();
        $response->assertJsonFragment(['code' => 'ACCESS001']);
        $this->assertDatabaseHas('llm_access_codes', ['code' => 'ACCESS001', 'park_id' => $park->id]);
    }

    public function test_can_update_access_code(): void
    {
        $park = Park::factory()->create();
        $code = LlmAccessCode::create(['park_id' => $park->id, 'code' => 'OLD_CODE', 'valid_from' => now()->toDateString(), 'active' => true]);

        $response = $this->actingAs($this->admin)->putJson("/api/parks/{$park->id}/access-codes/{$code->id}", [
            'code' => 'NEW_CODE',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['code' => 'NEW_CODE']);
    }

    public function test_can_delete_access_code(): void
    {
        $park = Park::factory()->create();
        $code = LlmAccessCode::create(['park_id' => $park->id, 'code' => 'DELETE_ME', 'valid_from' => now()->toDateString(), 'active' => true]);

        $response = $this->actingAs($this->admin)->deleteJson("/api/parks/{$park->id}/access-codes/{$code->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('llm_access_codes', ['id' => $code->id]);
    }

    public function test_can_sync_access_codes(): void
    {
        $park = Park::factory()->create();
        LlmAccessCode::create(['park_id' => $park->id, 'code' => 'SYNC001', 'valid_from' => now()->toDateString(), 'active' => true]);
        LlmAccessCode::create(['park_id' => $park->id, 'code' => 'SYNC002', 'valid_from' => now()->toDateString(), 'active' => false]);

        $response = $this->actingAs($this->admin)->postJson("/api/parks/{$park->id}/access-codes/sync");

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Active codes pushed to LLM lock system']);

        $synced = $response->json('synced');
        $this->assertCount(1, $synced);
        $this->assertEquals('SYNC001', $synced[0]['code']);
    }

    public function test_sync_excludes_expired_codes(): void
    {
        $park = Park::factory()->create();
        LlmAccessCode::create(['park_id' => $park->id, 'code' => 'EXPIRED', 'valid_from' => now()->subDays(10)->toDateString(), 'valid_to' => now()->subDays(1)->toDateString(), 'active' => true]);
        LlmAccessCode::create(['park_id' => $park->id, 'code' => 'VALID', 'valid_from' => now()->toDateString(), 'active' => true]);

        $response = $this->actingAs($this->admin)->postJson("/api/parks/{$park->id}/access-codes/sync");

        $response->assertOk();
        $synced = $response->json('synced');
        $codes = collect($synced)->pluck('code')->toArray();
        $this->assertContains('VALID', $codes);
        $this->assertNotContains('EXPIRED', $codes);
    }

    public function test_non_admin_cannot_manage_access_codes(): void
    {
        $park = Park::factory()->create();

        $response = $this->actingAs($this->rentalManager)->postJson("/api/parks/{$park->id}/access-codes", [
            'code'       => 'DENIED',
            'valid_from' => now()->toDateString(),
        ]);

        $response->assertForbidden();
    }
}
