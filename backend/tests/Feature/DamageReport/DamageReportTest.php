<?php

namespace Tests\Feature\DamageReport;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\DamageReport;
use App\Models\Park;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DamageReportTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $worker;
    private Park $park;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');

        $this->manager = User::factory()->create(['role' => 'main_manager']);
        $this->worker  = User::factory()->create(['role' => 'park_worker']);

        $this->park = Park::factory()->create();
        $unitType   = UnitType::factory()->create(['park_id' => $this->park->id]);
        $this->unit = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $unitType->id,
            'status'       => 'rented',
            'size_m2'      => 25.0,
        ]);

        // park_worker needs park access
        $this->worker->parks()->attach($this->park->id);
    }

    public function test_can_list_damage_reports(): void
    {
        DamageReport::factory()->count(3)->create(['unit_id' => $this->unit->id]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/damage-reports');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_filter_by_park(): void
    {
        DamageReport::factory()->create(['unit_id' => $this->unit->id]);

        $otherUnit = Unit::factory()->create(['size_m2' => 20.0]);
        DamageReport::factory()->create(['unit_id' => $otherUnit->id]);

        $response = $this->actingAs($this->manager)
            ->getJson("/api/damage-reports?park_id={$this->park->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_filter_by_status(): void
    {
        DamageReport::factory()->create(['unit_id' => $this->unit->id, 'status' => 'reported']);
        DamageReport::factory()->create(['unit_id' => $this->unit->id, 'status' => 'in_assessment']);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/damage-reports?status=reported');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_can_create_damage_report(): void
    {
        $response = $this->actingAs($this->worker)
            ->postJson('/api/damage-reports', [
                'unit_id'        => $this->unit->id,
                'description'    => 'Broken window',
                'estimated_cost' => 150.00,
            ]);

        $response->assertCreated();
        $response->assertJsonFragment(['status' => 'reported', 'description' => 'Broken window']);
        $this->assertDatabaseHas('damage_reports', [
            'unit_id'     => $this->unit->id,
            'description' => 'Broken window',
            'reported_by' => $this->worker->id,
        ]);
    }

    public function test_create_requires_unit_id_and_description(): void
    {
        $response = $this->actingAs($this->worker)
            ->postJson('/api/damage-reports', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['unit_id', 'description']);
    }

    public function test_can_update_damage_report(): void
    {
        $report = DamageReport::factory()->create(['unit_id' => $this->unit->id]);

        $response = $this->actingAs($this->manager)
            ->putJson("/api/damage-reports/{$report->id}", [
                'estimated_cost' => 500.00,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('damage_reports', ['id' => $report->id, 'estimated_cost' => 500.00]);
    }

    public function test_can_delete_damage_report(): void
    {
        $report = DamageReport::factory()->create(['unit_id' => $this->unit->id]);

        $response = $this->actingAs($this->manager)
            ->deleteJson("/api/damage-reports/{$report->id}");

        $response->assertOk();
        $this->assertSoftDeleted('damage_reports', ['id' => $report->id]);
    }

    public function test_can_upload_photo(): void
    {
        $report = DamageReport::factory()->create(['unit_id' => $this->unit->id]);
        $file   = UploadedFile::fake()->create('damage.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($this->worker)
            ->postJson("/api/damage-reports/{$report->id}/photos", [
                'photo'   => $file,
                'caption' => 'Front view',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('damage_photos', [
            'damage_report_id' => $report->id,
            'caption'          => 'Front view',
        ]);
    }

    public function test_can_update_status_with_valid_transition(): void
    {
        $report = DamageReport::factory()->create([
            'unit_id' => $this->unit->id,
            'status'  => 'reported',
        ]);

        $response = $this->actingAs($this->manager)
            ->putJson("/api/damage-reports/{$report->id}/status", [
                'status' => 'in_assessment',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('damage_reports', ['id' => $report->id, 'status' => 'in_assessment']);
    }

    public function test_invalid_status_transition_returns_422(): void
    {
        $report = DamageReport::factory()->create([
            'unit_id' => $this->unit->id,
            'status'  => 'reported',
        ]);

        $response = $this->actingAs($this->manager)
            ->putJson("/api/damage-reports/{$report->id}/status", [
                'status' => 'closed',
            ]);

        $response->assertUnprocessable();
    }

    public function test_resolved_sets_resolved_at(): void
    {
        $report = DamageReport::factory()->create([
            'unit_id' => $this->unit->id,
            'status'  => 'in_repair',
        ]);

        $this->actingAs($this->manager)
            ->putJson("/api/damage-reports/{$report->id}/status", [
                'status' => 'resolved',
            ]);

        $this->assertNotNull($report->fresh()->resolved_at);
    }

    public function test_can_assign_vendor(): void
    {
        $report = DamageReport::factory()->create(['unit_id' => $this->unit->id]);
        $vendor = Vendor::factory()->create(['park_id' => $this->park->id]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/damage-reports/{$report->id}/assign-vendor", [
                'vendor_id' => $vendor->id,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('damage_reports', [
            'id'                 => $report->id,
            'assigned_vendor_id' => $vendor->id,
        ]);
        $this->assertDatabaseHas('sent_emails', [
            'recipient_email' => $vendor->email,
        ]);
    }

    public function test_can_generate_damage_invoice(): void
    {
        $customer = Customer::factory()->create(['phone' => '555-1234']);
        $contract = Contract::factory()->create([
            'unit_id'     => $this->unit->id,
            'customer_id' => $customer->id,
            'status'      => 'active',
        ]);

        $report = DamageReport::factory()->create([
            'unit_id'        => $this->unit->id,
            'contract_id'    => $contract->id,
            'estimated_cost' => 300.00,
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/damage-reports/{$report->id}/invoice");

        $response->assertCreated();
        $response->assertJsonPath('total_amount', '300.00');
        $this->assertDatabaseHas('invoice_items', ['item_type' => 'damage']);
    }

    public function test_generate_invoice_requires_contract(): void
    {
        $report = DamageReport::factory()->create([
            'unit_id'     => $this->unit->id,
            'contract_id' => null,
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/damage-reports/{$report->id}/invoice");

        $response->assertUnprocessable();
    }
}
