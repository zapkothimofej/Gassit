<?php

namespace Tests\Feature\Notification;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Notification;
use App\Models\Park;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user  = User::factory()->create(['role' => 'rental_manager']);
        $this->other = User::factory()->create(['role' => 'rental_manager']);
    }

    // Notification list

    public function test_list_notifications_for_current_user(): void
    {
        Notification::create(['user_id' => $this->user->id, 'type' => 'info', 'title' => 'Hello', 'body' => 'World']);
        Notification::create(['user_id' => $this->other->id, 'type' => 'info', 'title' => 'Other', 'body' => 'Body']);

        $response = $this->actingAs($this->user)->getJson('/api/notifications');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Hello', $response->json('data.0.title'));
    }

    public function test_filter_unread_notifications(): void
    {
        Notification::create(['user_id' => $this->user->id, 'type' => 'info', 'title' => 'Unread', 'body' => 'Body']);
        Notification::create(['user_id' => $this->user->id, 'type' => 'info', 'title' => 'Read', 'body' => 'Body', 'read_at' => now()]);

        $response = $this->actingAs($this->user)->getJson('/api/notifications?read=0');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Unread', $response->json('data.0.title'));
    }

    public function test_filter_read_notifications(): void
    {
        Notification::create(['user_id' => $this->user->id, 'type' => 'info', 'title' => 'Unread', 'body' => 'Body']);
        Notification::create(['user_id' => $this->user->id, 'type' => 'info', 'title' => 'Read', 'body' => 'Body', 'read_at' => now()]);

        $response = $this->actingAs($this->user)->getJson('/api/notifications?read=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Read', $response->json('data.0.title'));
    }

    // Mark single as read

    public function test_mark_single_notification_as_read(): void
    {
        $notif = Notification::create(['user_id' => $this->user->id, 'type' => 'info', 'title' => 'Test', 'body' => 'Body']);

        $response = $this->actingAs($this->user)->postJson("/api/notifications/{$notif->id}/read");

        $response->assertOk();
        $this->assertNotNull($response->json('read_at'));
    }

    public function test_cannot_mark_other_users_notification_as_read(): void
    {
        $notif = Notification::create(['user_id' => $this->other->id, 'type' => 'info', 'title' => 'Test', 'body' => 'Body']);

        $this->actingAs($this->user)->postJson("/api/notifications/{$notif->id}/read")
            ->assertNotFound();
    }

    // Mark all as read

    public function test_mark_all_as_read(): void
    {
        Notification::create(['user_id' => $this->user->id, 'type' => 'info', 'title' => 'A', 'body' => 'Body']);
        Notification::create(['user_id' => $this->user->id, 'type' => 'info', 'title' => 'B', 'body' => 'Body']);

        $this->actingAs($this->user)->postJson('/api/notifications/read-all')->assertOk();

        $this->assertEquals(0, Notification::where('user_id', $this->user->id)->whereNull('read_at')->count());
    }

    // Unread count

    public function test_unread_count(): void
    {
        Notification::create(['user_id' => $this->user->id, 'type' => 'info', 'title' => 'A', 'body' => 'Body']);
        Notification::create(['user_id' => $this->user->id, 'type' => 'info', 'title' => 'B', 'body' => 'Body', 'read_at' => now()]);

        $response = $this->actingAs($this->user)->getJson('/api/notifications/unread-count');

        $response->assertOk()->assertJson(['count' => 1]);
    }

    // Global search

    public function test_search_customers_by_company(): void
    {
        Customer::factory()->create(['company_name' => 'FindableFirmaGmbH', 'phone' => '123']);
        Customer::factory()->create(['company_name' => 'OtherCompany', 'phone' => '456']);

        $response = $this->actingAs($this->user)->getJson('/api/search?q=FindableFirmaGmbH');

        $response->assertOk();
        $this->assertCount(1, $response->json('customers'));
        $this->assertEquals('FindableFirmaGmbH', $response->json('customers.0.company_name'));
    }

    public function test_search_units_by_number(): void
    {
        $park = Park::factory()->create();
        Unit::factory()->create(['unit_number' => 'A-101', 'park_id' => $park->id, 'size_m2' => 25]);
        Unit::factory()->create(['unit_number' => 'B-202', 'park_id' => $park->id, 'size_m2' => 30]);

        $response = $this->actingAs($this->user)->getJson('/api/search?q=A-101');

        $response->assertOk();
        $this->assertCount(1, $response->json('units'));
        $this->assertEquals('A-101', $response->json('units.0.unit_number'));
    }

    public function test_search_invoices_by_number(): void
    {
        $park = Park::factory()->create();
        $customer = Customer::factory()->create(['phone' => '000']);
        Invoice::factory()->create(['invoice_number' => 'PARK-2026-0001', 'park_id' => $park->id, 'customer_id' => $customer->id]);

        $response = $this->actingAs($this->user)->getJson('/api/search?q=PARK-2026-0001');

        $response->assertOk();
        $this->assertCount(1, $response->json('invoices'));
        $this->assertEquals('PARK-2026-0001', $response->json('invoices.0.invoice_number'));
    }

    public function test_search_requires_q_param(): void
    {
        $this->actingAs($this->user)->getJson('/api/search')
            ->assertUnprocessable();
    }

    public function test_search_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/search?q=test')->assertUnauthorized();
    }
}
