<?php

namespace Tests\Feature\Vendor;

use App\Models\DamageReport;
use App\Models\Park;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private Park $park;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->create(['role' => 'main_manager']);
        $this->park    = Park::factory()->create();
    }

    public function test_can_list_vendors(): void
    {
        Vendor::factory()->count(3)->create(['park_id' => $this->park->id]);

        $response = $this->actingAs($this->manager)->getJson('/api/vendors');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_filter_vendors_by_park(): void
    {
        Vendor::factory()->count(2)->create(['park_id' => $this->park->id]);
        $other = Park::factory()->create();
        Vendor::factory()->create(['park_id' => $other->id]);

        $response = $this->actingAs($this->manager)
            ->getJson('/api/vendors?park_id=' . $this->park->id);

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_can_create_vendor(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/api/vendors', [
            'park_id'      => $this->park->id,
            'name'         => 'Acme Repairs',
            'contact_name' => 'John Doe',
            'phone'        => '+49123456789',
            'email'        => 'john@acme.com',
            'specialty'    => 'electrical',
            'hourly_rate'  => 75.00,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('name', 'Acme Repairs');
        $this->assertDatabaseHas('vendors', ['name' => 'Acme Repairs']);
    }

    public function test_create_vendor_requires_required_fields(): void
    {
        $response = $this->actingAs($this->manager)->postJson('/api/vendors', []);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['name', 'contact_name', 'phone', 'email', 'specialty']);
    }

    public function test_can_show_vendor(): void
    {
        $vendor = Vendor::factory()->create(['park_id' => $this->park->id]);

        $response = $this->actingAs($this->manager)->getJson("/api/vendors/{$vendor->id}");

        $response->assertOk();
        $response->assertJsonPath('id', $vendor->id);
    }

    public function test_can_update_vendor(): void
    {
        $vendor = Vendor::factory()->create(['park_id' => $this->park->id]);

        $response = $this->actingAs($this->manager)->putJson("/api/vendors/{$vendor->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk();
        $response->assertJsonPath('name', 'Updated Name');
    }

    public function test_can_delete_vendor(): void
    {
        $vendor = Vendor::factory()->create(['park_id' => $this->park->id]);

        $response = $this->actingAs($this->manager)->deleteJson("/api/vendors/{$vendor->id}");

        $response->assertOk();
        $this->assertSoftDeleted('vendors', ['id' => $vendor->id]);
    }

    public function test_can_list_vendor_invoices(): void
    {
        $vendor = Vendor::factory()->create(['park_id' => $this->park->id]);
        VendorInvoice::factory()->count(3)->create(['vendor_id' => $vendor->id]);

        $response = $this->actingAs($this->manager)->getJson("/api/vendors/{$vendor->id}/invoices");

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_can_create_vendor_invoice(): void
    {
        $vendor = Vendor::factory()->create(['park_id' => $this->park->id]);

        $response = $this->actingAs($this->manager)->postJson("/api/vendors/{$vendor->id}/invoices", [
            'amount' => 500.00,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('amount', '500.00');
        $this->assertDatabaseHas('vendor_invoices', ['vendor_id' => $vendor->id, 'amount' => 500.00]);
    }

    public function test_can_update_vendor_invoice(): void
    {
        $vendor  = Vendor::factory()->create(['park_id' => $this->park->id]);
        $invoice = VendorInvoice::factory()->create(['vendor_id' => $vendor->id, 'amount' => 100.00]);

        $response = $this->actingAs($this->manager)
            ->putJson("/api/vendors/{$vendor->id}/invoices/{$invoice->id}", [
                'amount' => 250.00,
            ]);

        $response->assertOk();
        $response->assertJsonPath('amount', '250.00');
    }

    public function test_can_pay_vendor_invoice(): void
    {
        $vendor  = Vendor::factory()->create(['park_id' => $this->park->id]);
        $invoice = VendorInvoice::factory()->create(['vendor_id' => $vendor->id]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/vendors/{$vendor->id}/invoices/{$invoice->id}/pay");

        $response->assertOk();
        $this->assertDatabaseMissing('vendor_invoices', ['id' => $invoice->id, 'paid_at' => null]);
    }

    public function test_cannot_pay_already_paid_invoice(): void
    {
        $vendor  = Vendor::factory()->create(['park_id' => $this->park->id]);
        $invoice = VendorInvoice::factory()->create([
            'vendor_id' => $vendor->id,
            'paid_at'   => now(),
        ]);

        $response = $this->actingAs($this->manager)
            ->postJson("/api/vendors/{$vendor->id}/invoices/{$invoice->id}/pay");

        $response->assertUnprocessable();
    }

    public function test_can_list_vendor_damage_reports(): void
    {
        $vendor   = Vendor::factory()->create(['park_id' => $this->park->id]);
        $unitType = UnitType::factory()->create(['park_id' => $this->park->id]);
        $unit     = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $unitType->id,
            'size_m2'      => 20.0,
        ]);

        DamageReport::factory()->count(2)->create([
            'unit_id'            => $unit->id,
            'assigned_vendor_id' => $vendor->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->getJson("/api/vendors/{$vendor->id}/damage-reports");

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_rental_manager_can_access_vendors(): void
    {
        $rentalManager = User::factory()->create(['role' => 'rental_manager']);
        $rentalManager->parks()->attach($this->park->id);

        $response = $this->actingAs($rentalManager)->getJson('/api/vendors');

        $response->assertOk();
    }

    public function test_park_worker_cannot_access_vendors(): void
    {
        $worker = User::factory()->create(['role' => 'park_worker']);

        $response = $this->actingAs($worker)->getJson('/api/vendors');

        $response->assertForbidden();
    }
}
