<?php

namespace Tests\Feature\Electricity;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\ElectricityMeter;
use App\Models\ElectricityPricing;
use App\Models\ElectricityReading;
use App\Models\Park;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ElectricityTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Park $park;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');

        $this->manager = User::factory()->create(['role' => 'main_manager']);
        $this->park    = Park::factory()->create();
        $unitType      = UnitType::factory()->create(['park_id' => $this->park->id]);
        $this->unit    = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $unitType->id,
            'status'       => 'rented',
            'size_m2'      => 25.0,
        ]);
    }

    // --- Meter CRUD ---

    public function test_can_list_meters_for_unit(): void
    {
        ElectricityMeter::factory()->count(2)->create(['unit_id' => $this->unit->id]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/units/' . $this->unit->id . '/meters');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_can_create_meter(): void
    {
        $response = $this->actingAs($this->manager)
            ->postJson('/api/units/' . $this->unit->id . '/meters', [
                'meter_number' => 'MTR-001',
                'meter_type'   => 'main',
                'installed_at' => '2024-01-01',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('electricity_meters', [
            'unit_id'      => $this->unit->id,
            'meter_number' => 'MTR-001',
            'meter_type'   => 'main',
        ]);
    }

    public function test_can_update_meter(): void
    {
        $meter = ElectricityMeter::factory()->create(['unit_id' => $this->unit->id]);

        $response = $this->actingAs($this->manager)
            ->putJson('/api/meters/' . $meter->id, [
                'active' => false,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('electricity_meters', ['id' => $meter->id, 'active' => false]);
    }

    public function test_can_delete_meter(): void
    {
        $meter = ElectricityMeter::factory()->create(['unit_id' => $this->unit->id]);

        $response = $this->actingAs($this->manager)
            ->deleteJson('/api/meters/' . $meter->id);

        $response->assertNoContent();
        $this->assertDatabaseMissing('electricity_meters', ['id' => $meter->id]);
    }

    // --- Readings ---

    public function test_can_record_reading_without_photo(): void
    {
        $meter = ElectricityMeter::factory()->create(['unit_id' => $this->unit->id]);

        $response = $this->actingAs($this->manager)
            ->postJson('/api/meters/' . $meter->id . '/readings', [
                'reading_date'  => '2024-03-01',
                'reading_value' => 1000.0,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('electricity_readings', [
            'meter_id'      => $meter->id,
            'reading_value' => '1000.0000',
        ]);
    }

    public function test_can_record_reading_with_photo(): void
    {
        $meter = ElectricityMeter::factory()->create(['unit_id' => $this->unit->id]);

        $response = $this->actingAs($this->manager)
            ->postJson('/api/meters/' . $meter->id . '/readings', [
                'reading_date'  => '2024-03-01',
                'reading_value' => 1000.0,
                'photo'         => UploadedFile::fake()->create('meter.jpg', 100, 'image/jpeg'),
            ]);

        $response->assertCreated();
        $this->assertNotNull($response->json('photo_path'));
    }

    public function test_consumption_computed_from_previous_reading(): void
    {
        $meter = ElectricityMeter::factory()->create(['unit_id' => $this->unit->id]);

        // First reading — no previous, consumption null
        $r1 = $this->actingAs($this->manager)
            ->postJson('/api/meters/' . $meter->id . '/readings', [
                'reading_date'  => '2024-02-01',
                'reading_value' => 1000.0,
            ]);
        $r1->assertCreated();
        $this->assertNull($r1->json('consumption'));

        // Second reading — consumption = 1200 - 1000 = 200
        $r2 = $this->actingAs($this->manager)
            ->postJson('/api/meters/' . $meter->id . '/readings', [
                'reading_date'  => '2024-03-01',
                'reading_value' => 1200.0,
            ]);
        $r2->assertCreated();
        $this->assertEquals('200.0000', $r2->json('consumption'));
    }

    public function test_can_list_readings(): void
    {
        $meter = ElectricityMeter::factory()->create(['unit_id' => $this->unit->id]);
        $user  = User::factory()->create();
        ElectricityReading::factory()->count(3)->create([
            'meter_id'    => $meter->id,
            'recorded_by' => $user->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/meters/' . $meter->id . '/readings');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    // --- Billing ---

    public function test_bill_reading_creates_invoice_item(): void
    {
        $meter = ElectricityMeter::factory()->create(['unit_id' => $this->unit->id]);

        // Create pricing
        ElectricityPricing::create([
            'park_id'       => $this->park->id,
            'price_per_kwh' => 0.30,
            'valid_from'    => '2024-01-01',
            'valid_to'      => null,
        ]);

        // Create active contract for unit
        $customer = Customer::factory()->create();
        Contract::factory()->create([
            'unit_id'     => $this->unit->id,
            'customer_id' => $customer->id,
            'status'      => 'active',
        ]);

        // Create reading with consumption
        $user    = User::factory()->create();
        $reading = ElectricityReading::factory()->create([
            'meter_id'      => $meter->id,
            'reading_date'  => '2024-03-15',
            'reading_value' => 1200.0,
            'consumption'   => 100.0,
            'recorded_by'   => $user->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson('/api/meters/' . $meter->id . '/readings/' . $reading->id . '/bill');

        $response->assertOk();
        $this->assertNotNull($response->json('invoice_id'));
        $this->assertEquals(30.0, $response->json('item_charge'));

        $this->assertDatabaseHas('invoice_items', [
            'item_type' => 'electricity',
            'total'     => '30.00',
        ]);
    }

    public function test_bill_reading_fails_without_active_contract(): void
    {
        $meter = ElectricityMeter::factory()->create(['unit_id' => $this->unit->id]);

        ElectricityPricing::create([
            'park_id'       => $this->park->id,
            'price_per_kwh' => 0.30,
            'valid_from'    => '2024-01-01',
            'valid_to'      => null,
        ]);

        $user    = User::factory()->create();
        $reading = ElectricityReading::factory()->create([
            'meter_id'      => $meter->id,
            'reading_date'  => '2024-03-15',
            'reading_value' => 1200.0,
            'consumption'   => 100.0,
            'recorded_by'   => $user->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson('/api/meters/' . $meter->id . '/readings/' . $reading->id . '/bill');

        $response->assertUnprocessable();
    }

    public function test_bill_reading_fails_without_pricing(): void
    {
        $meter = ElectricityMeter::factory()->create(['unit_id' => $this->unit->id]);

        $customer = Customer::factory()->create();
        Contract::factory()->create([
            'unit_id'     => $this->unit->id,
            'customer_id' => $customer->id,
            'status'      => 'active',
        ]);

        $user    = User::factory()->create();
        $reading = ElectricityReading::factory()->create([
            'meter_id'      => $meter->id,
            'reading_date'  => '2024-03-15',
            'reading_value' => 1200.0,
            'consumption'   => 100.0,
            'recorded_by'   => $user->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson('/api/meters/' . $meter->id . '/readings/' . $reading->id . '/bill');

        $response->assertUnprocessable();
    }

    // --- Pricing ---

    public function test_can_list_pricing(): void
    {
        ElectricityPricing::factory()->count(2)->create(['park_id' => $this->park->id]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/parks/' . $this->park->id . '/electricity-pricing');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_can_create_pricing_and_closes_previous(): void
    {
        // Create an open pricing period
        $old = ElectricityPricing::factory()->create([
            'park_id'    => $this->park->id,
            'valid_from' => '2024-01-01',
            'valid_to'   => null,
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson('/api/parks/' . $this->park->id . '/electricity-pricing', [
                'price_per_kwh' => 0.35,
            ]);

        $response->assertCreated();
        $this->assertNotNull(ElectricityPricing::find($old->id)->valid_to);
        $this->assertNull($response->json('valid_to'));
    }

    public function test_pricing_requires_valid_price(): void
    {
        $response = $this->actingAs($this->manager)
            ->postJson('/api/parks/' . $this->park->id . '/electricity-pricing', [
                'price_per_kwh' => -1,
            ]);

        $response->assertUnprocessable();
    }
}
