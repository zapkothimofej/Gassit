<?php

namespace Tests\Feature\Application;

use App\Models\Application;
use App\Models\Customer;
use App\Models\Park;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $officeWorker;
    private Park $park;
    private Customer $customer;
    private UnitType $unitType;
    private string $adminToken;
    private string $workerToken;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');

        $this->admin       = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->officeWorker = User::factory()->create(['role' => 'office_worker', 'active' => true]);
        $this->park        = Park::factory()->create();
        $this->customer    = Customer::factory()->create();
        $this->unitType    = UnitType::factory()->create(['park_id' => $this->park->id]);

        $this->officeWorker->parks()->attach($this->park->id);

        $this->adminToken  = $this->admin->createToken('api-token')->plainTextToken;
        $this->workerToken = $this->officeWorker->createToken('api-token')->plainTextToken;
    }

    private function adminAuth(): array
    {
        return ['Authorization' => "Bearer {$this->adminToken}"];
    }

    private function workerAuth(): array
    {
        return ['Authorization' => "Bearer {$this->workerToken}"];
    }

    private function applicationData(): array
    {
        return [
            'park_id'            => $this->park->id,
            'customer_id'        => $this->customer->id,
            'unit_type_id'       => $this->unitType->id,
            'desired_start_date' => '2026-06-01',
            'source'             => 'online',
        ];
    }

    // --- LIST ---

    public function test_unauthenticated_cannot_list_applications(): void
    {
        $this->getJson('/api/applications')->assertStatus(401);
    }

    public function test_admin_can_list_applications(): void
    {
        Application::factory()->count(3)->create([
            'park_id'       => $this->park->id,
            'customer_id'   => $this->customer->id,
            'unit_type_id'  => $this->unitType->id,
        ]);

        $this->withHeaders($this->adminAuth())
            ->getJson('/api/applications')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_can_filter_applications_by_park(): void
    {
        Application::factory()->create([
            'park_id'       => $this->park->id,
            'customer_id'   => $this->customer->id,
            'unit_type_id'  => $this->unitType->id,
        ]);

        $otherPark = Park::factory()->create();
        Application::factory()->create([
            'park_id'       => $otherPark->id,
            'customer_id'   => $this->customer->id,
            'unit_type_id'  => UnitType::factory()->create(['park_id' => $otherPark->id])->id,
        ]);

        $response = $this->withHeaders($this->adminAuth())
            ->getJson("/api/applications?park_id={$this->park->id}")
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('total'));
    }

    public function test_can_filter_applications_by_status(): void
    {
        Application::factory()->create([
            'park_id' => $this->park->id, 'customer_id' => $this->customer->id,
            'unit_type_id' => $this->unitType->id, 'status' => 'new',
        ]);
        Application::factory()->create([
            'park_id' => $this->park->id, 'customer_id' => $this->customer->id,
            'unit_type_id' => $this->unitType->id, 'status' => 'in_progress',
        ]);

        $response = $this->withHeaders($this->adminAuth())
            ->getJson('/api/applications?status=new')
            ->assertStatus(200);

        $this->assertEquals(1, $response->json('total'));
    }

    // --- CREATE ---

    public function test_admin_can_create_application(): void
    {
        $this->withHeaders($this->adminAuth())
            ->postJson('/api/applications', $this->applicationData())
            ->assertStatus(201)
            ->assertJsonPath('status', 'new');
    }

    public function test_create_application_sends_acknowledgment_email(): void
    {
        $this->withHeaders($this->adminAuth())
            ->postJson('/api/applications', $this->applicationData())
            ->assertStatus(201);

        $this->assertDatabaseHas('sent_emails', [
            'customer_id' => $this->customer->id,
            'status'      => 'queued',
        ]);
    }

    public function test_office_worker_can_create_application(): void
    {
        $this->withHeaders($this->workerAuth())
            ->postJson('/api/applications', $this->applicationData())
            ->assertStatus(201);
    }

    public function test_create_application_validation(): void
    {
        $this->withHeaders($this->adminAuth())
            ->postJson('/api/applications', [])
            ->assertStatus(422);
    }

    // --- UPDATE ---

    public function test_admin_can_update_application(): void
    {
        $application = Application::factory()->create([
            'park_id' => $this->park->id, 'customer_id' => $this->customer->id,
            'unit_type_id' => $this->unitType->id,
        ]);

        $this->withHeaders($this->adminAuth())
            ->putJson("/api/applications/{$application->id}", ['notes' => 'Updated notes'])
            ->assertStatus(200)
            ->assertJsonPath('notes', 'Updated notes');
    }

    // --- DELETE ---

    public function test_admin_can_delete_application(): void
    {
        $application = Application::factory()->create([
            'park_id' => $this->park->id, 'customer_id' => $this->customer->id,
            'unit_type_id' => $this->unitType->id,
        ]);

        $this->withHeaders($this->adminAuth())
            ->deleteJson("/api/applications/{$application->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('applications', ['id' => $application->id]);
    }

    // --- STATUS TRANSITION ---

    public function test_valid_status_transition(): void
    {
        $application = Application::factory()->create([
            'park_id' => $this->park->id, 'customer_id' => $this->customer->id,
            'unit_type_id' => $this->unitType->id, 'status' => 'new',
        ]);

        $this->withHeaders($this->adminAuth())
            ->putJson("/api/applications/{$application->id}/status", ['status' => 'in_progress'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'in_progress');
    }

    public function test_invalid_status_transition_returns_422(): void
    {
        $application = Application::factory()->create([
            'park_id' => $this->park->id, 'customer_id' => $this->customer->id,
            'unit_type_id' => $this->unitType->id, 'status' => 'new',
        ]);

        $this->withHeaders($this->adminAuth())
            ->putJson("/api/applications/{$application->id}/status", ['status' => 'completed'])
            ->assertStatus(422);
    }

    public function test_cannot_transition_from_completed(): void
    {
        $application = Application::factory()->create([
            'park_id' => $this->park->id, 'customer_id' => $this->customer->id,
            'unit_type_id' => $this->unitType->id, 'status' => 'completed',
        ]);

        $this->withHeaders($this->adminAuth())
            ->putJson("/api/applications/{$application->id}/status", ['status' => 'declined'])
            ->assertStatus(422);
    }

    // --- ASSIGN ---

    public function test_can_assign_application_to_user(): void
    {
        $application = Application::factory()->create([
            'park_id' => $this->park->id, 'customer_id' => $this->customer->id,
            'unit_type_id' => $this->unitType->id,
        ]);
        $worker = User::factory()->create(['role' => 'office_worker', 'active' => true]);

        $this->withHeaders($this->adminAuth())
            ->postJson("/api/applications/{$application->id}/assign", ['user_id' => $worker->id])
            ->assertStatus(200)
            ->assertJsonPath('assigned_to', $worker->id);
    }

    // --- CREDIT CHECK ---

    public function test_credit_check_returns_summary(): void
    {
        $application = Application::factory()->create([
            'park_id' => $this->park->id, 'customer_id' => $this->customer->id,
            'unit_type_id' => $this->unitType->id,
        ]);

        $this->withHeaders($this->adminAuth())
            ->postJson("/api/applications/{$application->id}/credit-check")
            ->assertStatus(200)
            ->assertJsonStructure(['status', 'score', 'risk_level', 'credit_check_path', 'checked_at']);
    }

    public function test_credit_check_stores_pdf_path(): void
    {
        $application = Application::factory()->create([
            'park_id' => $this->park->id, 'customer_id' => $this->customer->id,
            'unit_type_id' => $this->unitType->id,
        ]);

        $this->withHeaders($this->adminAuth())
            ->postJson("/api/applications/{$application->id}/credit-check")
            ->assertStatus(200);

        $this->assertNotNull($application->fresh()->credit_check_path);
    }

    // --- WAITING LIST ---

    public function test_can_move_application_to_waiting_list(): void
    {
        $application = Application::factory()->create([
            'park_id' => $this->park->id, 'customer_id' => $this->customer->id,
            'unit_type_id' => $this->unitType->id, 'status' => 'new',
        ]);

        $this->withHeaders($this->adminAuth())
            ->postJson("/api/applications/{$application->id}/waiting-list", ['priority_score' => 10])
            ->assertStatus(201)
            ->assertJsonStructure(['application', 'waiting_list']);

        $this->assertEquals('waiting', $application->fresh()->status);
        $this->assertDatabaseHas('waiting_list', ['customer_id' => $this->customer->id]);
    }

    public function test_cannot_move_completed_application_to_waiting_list(): void
    {
        $application = Application::factory()->create([
            'park_id' => $this->park->id, 'customer_id' => $this->customer->id,
            'unit_type_id' => $this->unitType->id, 'status' => 'completed',
        ]);

        $this->withHeaders($this->adminAuth())
            ->postJson("/api/applications/{$application->id}/waiting-list")
            ->assertStatus(422);
    }

    // --- CONVERT ---

    public function test_can_convert_application_to_contract(): void
    {
        $application = Application::factory()->create([
            'park_id' => $this->park->id, 'customer_id' => $this->customer->id,
            'unit_type_id' => $this->unitType->id, 'status' => 'in_progress',
        ]);
        $unit = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $this->unitType->id,
            'status'       => 'free',
        ]);

        $this->withHeaders($this->adminAuth())
            ->postJson("/api/applications/{$application->id}/convert", [
                'unit_id'        => $unit->id,
                'start_date'     => '2026-06-01',
                'rent_amount'    => 150.00,
                'deposit_amount' => 300.00,
            ])
            ->assertStatus(201)
            ->assertJsonStructure(['application', 'contract']);

        $this->assertEquals('completed', $application->fresh()->status);
        $this->assertEquals('reserved', $unit->fresh()->status);
        $this->assertDatabaseHas('contracts', ['application_id' => $application->id, 'status' => 'draft']);
    }

    public function test_cannot_convert_with_rented_unit(): void
    {
        $application = Application::factory()->create([
            'park_id' => $this->park->id, 'customer_id' => $this->customer->id,
            'unit_type_id' => $this->unitType->id,
        ]);
        $unit = Unit::factory()->create([
            'park_id' => $this->park->id, 'unit_type_id' => $this->unitType->id, 'status' => 'rented',
        ]);

        $this->withHeaders($this->adminAuth())
            ->postJson("/api/applications/{$application->id}/convert", [
                'unit_id'        => $unit->id,
                'start_date'     => '2026-06-01',
                'rent_amount'    => 150.00,
                'deposit_amount' => 300.00,
            ])
            ->assertStatus(422);
    }
}
