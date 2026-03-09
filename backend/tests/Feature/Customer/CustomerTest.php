<?php

namespace Tests\Feature\Customer;

use App\Models\Blacklist;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\Park;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomerTest extends TestCase
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
        $this->manager = User::factory()->create(['role' => 'rental_manager', 'active' => true]);
        $this->park    = Park::factory()->create();
        $this->manager->parks()->attach($this->park->id);

        $this->adminToken   = $this->admin->createToken('api-token')->plainTextToken;
        $this->managerToken = $this->manager->createToken('api-token')->plainTextToken;

        Storage::fake('s3');
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

    public function test_unauthenticated_cannot_list_customers(): void
    {
        $this->getJson('/api/customers')->assertStatus(401);
    }

    public function test_admin_can_list_customers(): void
    {
        Customer::factory()->count(3)->create();

        $this->withHeaders($this->adminAuth())
            ->getJson('/api/customers')
            ->assertOk()
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_list_customers_filter_by_status(): void
    {
        Customer::factory()->create(['status' => 'new']);
        Customer::factory()->create(['status' => 'tenant']);

        $response = $this->withHeaders($this->adminAuth())
            ->getJson('/api/customers?status=tenant')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('tenant', $response->json('data.0.status'));
    }

    public function test_list_customers_search_by_email(): void
    {
        Customer::factory()->create(['email' => 'unique@test.com']);
        Customer::factory()->create(['email' => 'other@example.com']);

        $response = $this->withHeaders($this->adminAuth())
            ->getJson('/api/customers?search=unique')
            ->assertOk();

        $this->assertCount(1, $response->json('data'));
    }

    // --- CREATE ---

    public function test_admin_can_create_customer(): void
    {
        $response = $this->withHeaders($this->adminAuth())
            ->postJson('/api/customers', [
                'type'       => 'private',
                'first_name' => 'Max',
                'last_name'  => 'Mustermann',
                'email'      => 'max@example.com',
                'phone'      => '0301234567',
                'address'    => 'Musterstr. 1',
                'city'       => 'Berlin',
                'zip'        => '10115',
                'country'    => 'DE',
            ])
            ->assertStatus(201);

        $this->assertEquals('Max', $response->json('first_name'));
        $this->assertDatabaseHas('customers', ['email' => 'max@example.com']);
    }

    public function test_create_customer_requires_address(): void
    {
        $this->withHeaders($this->adminAuth())
            ->postJson('/api/customers', [
                'type'       => 'private',
                'first_name' => 'Max',
                'last_name'  => 'Mustermann',
                'email'      => 'max@example.com',
            ])
            ->assertStatus(422);
    }

    // --- UPDATE ---

    public function test_admin_can_update_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->withHeaders($this->adminAuth())
            ->putJson("/api/customers/{$customer->id}", ['city' => 'Munich'])
            ->assertOk()
            ->assertJsonPath('city', 'Munich');
    }

    // --- DELETE ---

    public function test_admin_can_soft_delete_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->withHeaders($this->adminAuth())
            ->deleteJson("/api/customers/{$customer->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Customer deleted.');

        $this->assertSoftDeleted('customers', ['id' => $customer->id]);
    }

    // --- DOCUMENTS ---

    public function test_admin_can_upload_document(): void
    {
        $customer = Customer::factory()->create();
        $file = UploadedFile::fake()->create('id.pdf', 100, 'application/pdf');

        $response = $this->withHeaders($this->adminAuth())
            ->postJson("/api/customers/{$customer->id}/documents", [
                'document'      => $file,
                'document_type' => 'id_card',
            ])
            ->assertStatus(201);

        $this->assertEquals('id_card', $response->json('document_type'));
        $this->assertDatabaseHas('customer_documents', ['customer_id' => $customer->id]);
    }

    public function test_admin_can_list_documents(): void
    {
        $customer = Customer::factory()->create();
        CustomerDocument::factory()->count(2)->create(['customer_id' => $customer->id]);

        $this->withHeaders($this->adminAuth())
            ->getJson("/api/customers/{$customer->id}/documents")
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_admin_can_delete_document(): void
    {
        $customer = Customer::factory()->create();
        $doc = CustomerDocument::factory()->create(['customer_id' => $customer->id]);

        $this->withHeaders($this->adminAuth())
            ->deleteJson("/api/customers/{$customer->id}/documents/{$doc->id}")
            ->assertOk();

        $this->assertDatabaseMissing('customer_documents', ['id' => $doc->id]);
    }

    // --- GDPR DELETE ---

    public function test_admin_can_gdpr_delete_customer(): void
    {
        $customer = Customer::factory()->create([
            'first_name' => 'Real',
            'email'      => 'real@example.com',
        ]);

        $this->withHeaders($this->adminAuth())
            ->postJson("/api/customers/{$customer->id}/gdpr-delete")
            ->assertOk()
            ->assertJsonPath('message', 'Customer data anonymized.');

        $customer->refresh();
        $this->assertEquals('[gelöscht]', $customer->first_name);
        $this->assertNotNull($customer->gdpr_deleted_at);
    }

    // --- BLACKLIST ---

    public function test_admin_can_blacklist_customer(): void
    {
        $customer = Customer::factory()->create();

        $this->withHeaders($this->adminAuth())
            ->postJson("/api/customers/{$customer->id}/blacklist", [
                'reason' => 'Non-payment',
            ])
            ->assertStatus(201);

        $customer->refresh();
        $this->assertEquals('blacklisted', $customer->status);
        $this->assertDatabaseHas('blacklist', ['customer_id' => $customer->id]);
    }

    public function test_admin_can_remove_from_blacklist(): void
    {
        $customer = Customer::factory()->create(['status' => 'blacklisted']);
        Blacklist::create([
            'customer_id' => $customer->id,
            'reason'      => 'Test',
            'added_by'    => $this->admin->id,
            'added_at'    => now(),
        ]);

        $this->withHeaders($this->adminAuth())
            ->deleteJson("/api/customers/{$customer->id}/blacklist")
            ->assertOk();

        $this->assertNotNull(Blacklist::where('customer_id', $customer->id)->first()->removed_at);
    }

    public function test_admin_can_list_blacklist(): void
    {
        $customer = Customer::factory()->create(['status' => 'blacklisted']);
        Blacklist::create([
            'customer_id' => $customer->id,
            'reason'      => 'Test',
            'added_by'    => $this->admin->id,
            'added_at'    => now(),
        ]);

        $this->withHeaders($this->adminAuth())
            ->getJson('/api/customers/blacklist')
            ->assertOk()
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_manager_can_access_customers(): void
    {
        Customer::factory()->count(2)->create();

        $this->withHeaders($this->managerAuth())
            ->getJson('/api/customers')
            ->assertOk();
    }
}
