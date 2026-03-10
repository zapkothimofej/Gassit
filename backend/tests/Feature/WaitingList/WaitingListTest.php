<?php

namespace Tests\Feature\WaitingList;

use App\Jobs\NotifyWaitingListEntries;
use App\Models\Application;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Park;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Models\WaitingList;
use App\Models\WaitingListNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WaitingListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Park $park;
    private Customer $customer;
    private UnitType $unitType;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        Queue::fake();

        $this->admin    = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->park     = Park::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->unitType = UnitType::factory()->create(['park_id' => $this->park->id]);

        $this->adminToken = $this->admin->createToken('api-token')->plainTextToken;
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    // --- LIST ---

    public function test_unauthenticated_cannot_list_waiting_list(): void
    {
        $this->getJson("/api/parks/{$this->park->id}/waiting-list")->assertStatus(401);
    }

    public function test_admin_can_list_waiting_list(): void
    {
        WaitingList::factory()->count(3)->create([
            'park_id'       => $this->park->id,
            'customer_id'   => $this->customer->id,
            'unit_type_id'  => $this->unitType->id,
        ]);

        $this->withHeaders($this->auth())
            ->getJson("/api/parks/{$this->park->id}/waiting-list")
            ->assertOk()
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_list_sorted_by_priority_score_desc_then_created_at_asc(): void
    {
        $low  = WaitingList::factory()->create(['park_id' => $this->park->id, 'customer_id' => $this->customer->id, 'unit_type_id' => $this->unitType->id, 'priority_score' => 10]);
        $high = WaitingList::factory()->create(['park_id' => $this->park->id, 'customer_id' => $this->customer->id, 'unit_type_id' => $this->unitType->id, 'priority_score' => 90]);

        $response = $this->withHeaders($this->auth())
            ->getJson("/api/parks/{$this->park->id}/waiting-list")
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertEquals($high->id, $ids[0]);
        $this->assertEquals($low->id, $ids[1]);
    }

    // --- CREATE ---

    public function test_admin_can_create_waiting_list_entry(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/parks/{$this->park->id}/waiting-list", [
                'customer_id'    => $this->customer->id,
                'unit_type_id'   => $this->unitType->id,
                'priority_score' => 50,
                'notes'          => 'Urgent need',
            ])
            ->assertCreated()
            ->assertJsonPath('park_id', $this->park->id)
            ->assertJsonPath('customer_id', $this->customer->id);

        $this->assertDatabaseHas('waiting_list', [
            'park_id'        => $this->park->id,
            'customer_id'    => $this->customer->id,
            'priority_score' => 50,
        ]);
    }

    public function test_create_requires_customer_and_unit_type(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/parks/{$this->park->id}/waiting-list", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id', 'unit_type_id']);
    }

    // --- UPDATE ---

    public function test_admin_can_update_waiting_list_entry(): void
    {
        $entry = WaitingList::factory()->create([
            'park_id'        => $this->park->id,
            'customer_id'    => $this->customer->id,
            'unit_type_id'   => $this->unitType->id,
            'priority_score' => 20,
        ]);

        $this->withHeaders($this->auth())
            ->putJson("/api/waiting-list/{$entry->id}", ['priority_score' => 80, 'notes' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('priority_score', 80);
    }

    // --- DELETE ---

    public function test_admin_can_delete_waiting_list_entry(): void
    {
        $entry = WaitingList::factory()->create([
            'park_id'      => $this->park->id,
            'customer_id'  => $this->customer->id,
            'unit_type_id' => $this->unitType->id,
        ]);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/waiting-list/{$entry->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Waiting list entry deleted.');

        $this->assertSoftDeleted('waiting_list', ['id' => $entry->id]);
    }

    // --- NOTIFY ---

    public function test_notify_creates_notification_and_sent_email(): void
    {
        $entry = WaitingList::factory()->create([
            'park_id'      => $this->park->id,
            'customer_id'  => $this->customer->id,
            'unit_type_id' => $this->unitType->id,
        ]);

        $unit = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $this->unitType->id,
            'status'       => 'free',
        ]);

        $this->withHeaders($this->auth())
            ->postJson("/api/waiting-list/{$entry->id}/notify", ['unit_id' => $unit->id])
            ->assertCreated()
            ->assertJsonStructure(['notification', 'waiting_entry']);

        $this->assertDatabaseHas('waiting_list_notifications', [
            'waiting_list_id' => $entry->id,
            'unit_id'         => $unit->id,
            'method'          => 'email',
        ]);

        $this->assertDatabaseHas('sent_emails', [
            'customer_id' => $this->customer->id,
        ]);
    }

    public function test_notify_updates_notified_at(): void
    {
        $entry = WaitingList::factory()->create([
            'park_id'      => $this->park->id,
            'customer_id'  => $this->customer->id,
            'unit_type_id' => $this->unitType->id,
        ]);

        $unit = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $this->unitType->id,
            'status'       => 'free',
        ]);

        $this->withHeaders($this->auth())
            ->postJson("/api/waiting-list/{$entry->id}/notify", ['unit_id' => $unit->id])
            ->assertCreated();

        $this->assertNotNull($entry->fresh()->notified_at);
    }

    // --- CONVERT ---

    public function test_convert_creates_application(): void
    {
        $entry = WaitingList::factory()->create([
            'park_id'      => $this->park->id,
            'customer_id'  => $this->customer->id,
            'unit_type_id' => $this->unitType->id,
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/waiting-list/{$entry->id}/convert", [
                'desired_start_date' => '2026-07-01',
            ])
            ->assertCreated()
            ->assertJsonStructure(['application', 'waiting_entry']);

        $this->assertDatabaseHas('applications', [
            'park_id'      => $this->park->id,
            'customer_id'  => $this->customer->id,
            'unit_type_id' => $this->unitType->id,
        ]);

        $appId = $response->json('application.id');
        $this->assertEquals($appId, $entry->fresh()->converted_application_id);
    }

    // --- AUTO-TRIGGER ---

    public function test_unit_status_change_to_free_dispatches_notify_job(): void
    {
        $unit = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $this->unitType->id,
            'status'       => 'rented',
        ]);

        // Create a terminated contract to allow free transition
        Contract::factory()->create([
            'unit_id'    => $unit->id,
            'customer_id' => $this->customer->id,
            'status'     => 'terminated_by_customer',
        ]);

        $this->withHeaders($this->auth())
            ->putJson("/api/units/{$unit->id}/status", ['status' => 'free'])
            ->assertOk();

        Queue::assertPushed(NotifyWaitingListEntries::class, function ($job) use ($unit) {
            return $job->unitId === $unit->id;
        });
    }

    public function test_unit_status_change_to_non_free_does_not_dispatch_job(): void
    {
        $unit = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $this->unitType->id,
            'status'       => 'free',
        ]);

        $this->withHeaders($this->auth())
            ->putJson("/api/units/{$unit->id}/status", ['status' => 'maintenance'])
            ->assertOk();

        Queue::assertNotPushed(NotifyWaitingListEntries::class);
    }
}
