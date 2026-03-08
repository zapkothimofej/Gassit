<?php

namespace Tests\Feature\Dunning;

use App\Console\Commands\ProcessDunning;
use App\Models\Customer;
use App\Models\DunningRecord;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Park;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DunningTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accountant = User::factory()->create(['role' => 'accountant']);
    }

    private function makeOverdueInvoice(Customer $customer, Park $park, int $daysOverdue = 10): Invoice
    {
        return Invoice::factory()->create([
            'customer_id'  => $customer->id,
            'park_id'      => $park->id,
            'due_date'     => now()->subDays($daysOverdue)->format('Y-m-d'),
            'status'       => 'sent',
            'total_amount' => 100.00,
            'subtotal'     => 84.03,
            'tax_rate'     => 19.00,
            'tax_amount'   => 15.97,
        ]);
    }

    public function test_debtors_list_returns_customers_with_overdue_invoices(): void
    {
        $park     = Park::factory()->create();
        $customer = Customer::factory()->create(['status' => 'tenant']);
        $this->makeOverdueInvoice($customer, $park);

        // Non-overdue invoice — should not appear
        $other = Customer::factory()->create();
        Invoice::factory()->create([
            'customer_id' => $other->id,
            'park_id'     => $park->id,
            'due_date'    => now()->addDays(10)->format('Y-m-d'),
            'status'      => 'sent',
        ]);

        $response = $this->actingAs($this->accountant)
            ->getJson('/api/debtors');

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals($customer->id, $data[0]['customer']['id']);
        $this->assertEquals(0, $data[0]['dunning_level']);
        $this->assertGreaterThan(0, $data[0]['days_overdue']);
    }

    public function test_debtors_filtered_by_park(): void
    {
        $park1 = Park::factory()->create();
        $park2 = Park::factory()->create();
        $c1    = Customer::factory()->create();
        $c2    = Customer::factory()->create();

        $this->makeOverdueInvoice($c1, $park1);
        $this->makeOverdueInvoice($c2, $park2);

        $response = $this->actingAs($this->accountant)
            ->getJson('/api/debtors?park_id=' . $park1->id);

        $response->assertOk();
        $ids = collect($response->json())->pluck('customer.id');
        $this->assertContains($c1->id, $ids);
        $this->assertNotContains($c2->id, $ids);
    }

    public function test_pause_sets_dunning_paused_until(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->accountant)
            ->postJson('/api/debtors/' . $customer->id . '/pause');

        $response->assertOk();
        $this->assertNotNull($response->json('dunning_paused_until'));

        $customer->refresh();
        $this->assertNotNull($customer->dunning_paused_until);
        $this->assertTrue($customer->dunning_paused_until->greaterThan(now()->addDays(29)));
    }

    public function test_escalate_creates_dunning_record_and_fee(): void
    {
        $park     = Park::factory()->create();
        $customer = Customer::factory()->create(['status' => 'new']);
        $invoice  = $this->makeOverdueInvoice($customer, $park);

        $response = $this->actingAs($this->accountant)
            ->postJson('/api/debtors/' . $customer->id . '/escalate');

        $response->assertOk();

        $this->assertDatabaseHas('dunning_records', [
            'invoice_id'  => $invoice->id,
            'customer_id' => $customer->id,
            'level'       => 1,
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'item_type'  => 'dunning_fee',
            'total'      => '5.00',
        ]);

        $customer->refresh();
        $this->assertEquals('debtor', $customer->status);
    }

    public function test_escalate_increments_level(): void
    {
        $park     = Park::factory()->create();
        $customer = Customer::factory()->create();
        $invoice  = $this->makeOverdueInvoice($customer, $park);

        // Existing level 1 dunning
        DunningRecord::create([
            'invoice_id'  => $invoice->id,
            'customer_id' => $customer->id,
            'level'       => 1,
            'sent_at'     => now()->subDays(8),
            'fee_amount'  => 5.00,
        ]);

        $response = $this->actingAs($this->accountant)
            ->postJson('/api/debtors/' . $customer->id . '/escalate');

        $response->assertOk();

        $this->assertDatabaseHas('dunning_records', [
            'invoice_id' => $invoice->id,
            'level'      => 2,
        ]);
    }

    public function test_escalate_fails_when_no_overdue_invoices(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->accountant)
            ->postJson('/api/debtors/' . $customer->id . '/escalate');

        $response->assertStatus(422);
    }

    public function test_escalate_fails_at_max_dunning_level(): void
    {
        $park     = Park::factory()->create();
        $customer = Customer::factory()->create();
        $invoice  = $this->makeOverdueInvoice($customer, $park);

        DunningRecord::create([
            'invoice_id'  => $invoice->id,
            'customer_id' => $customer->id,
            'level'       => 3,
            'sent_at'     => now()->subDays(5),
            'fee_amount'  => 30.00,
        ]);

        $response = $this->actingAs($this->accountant)
            ->postJson('/api/debtors/' . $customer->id . '/escalate');

        $response->assertStatus(422);
    }

    public function test_resolve_marks_invoices_paid_and_resets_customer(): void
    {
        $park     = Park::factory()->create();
        $customer = Customer::factory()->create(['status' => 'debtor']);
        $invoice  = $this->makeOverdueInvoice($customer, $park);

        $response = $this->actingAs($this->accountant)
            ->postJson('/api/debtors/' . $customer->id . '/resolve');

        $response->assertOk();

        $invoice->refresh();
        $this->assertEquals('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);

        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'status'     => 'paid',
        ]);

        $customer->refresh();
        $this->assertEquals('tenant', $customer->status);
        $this->assertNull($customer->dunning_paused_until);
    }

    public function test_resolve_fails_when_no_overdue_invoices(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($this->accountant)
            ->postJson('/api/debtors/' . $customer->id . '/resolve');

        $response->assertStatus(422);
    }

    public function test_process_dunning_command_escalates_overdue_invoice(): void
    {
        $park     = Park::factory()->create();
        $customer = Customer::factory()->create(['status' => 'new']);
        $invoice  = $this->makeOverdueInvoice($customer, $park, 10);

        $this->artisan('dunning:process')->assertSuccessful();

        $this->assertDatabaseHas('dunning_records', [
            'invoice_id'  => $invoice->id,
            'customer_id' => $customer->id,
            'level'       => 1,
        ]);

        $customer->refresh();
        $this->assertEquals('debtor', $customer->status);
    }

    public function test_process_dunning_skips_paused_customers(): void
    {
        $park     = Park::factory()->create();
        $customer = Customer::factory()->create([
            'dunning_paused_until' => now()->addDays(20),
        ]);
        $invoice = $this->makeOverdueInvoice($customer, $park, 10);

        $this->artisan('dunning:process')->assertSuccessful();

        $this->assertDatabaseMissing('dunning_records', [
            'invoice_id' => $invoice->id,
        ]);
    }

    public function test_process_dunning_skips_if_delay_not_met(): void
    {
        $park     = Park::factory()->create();
        $customer = Customer::factory()->create();
        // Only 3 days overdue, delay_1 = 7 days
        $invoice = $this->makeOverdueInvoice($customer, $park, 3);

        $this->artisan('dunning:process')->assertSuccessful();

        $this->assertDatabaseMissing('dunning_records', [
            'invoice_id' => $invoice->id,
        ]);
    }

    public function test_unauthorized_user_cannot_access_debtors(): void
    {
        $customer = User::factory()->create(['role' => 'customer_service']);

        $this->actingAs($customer)->getJson('/api/debtors')->assertStatus(403);
    }
}
