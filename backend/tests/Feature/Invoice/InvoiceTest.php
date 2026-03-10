<?php

namespace Tests\Feature\Invoice;

use App\Jobs\GenerateMonthlyInvoices;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Park;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Park $park;
    private Customer $customer;
    private Unit $unit;
    private Contract $contract;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');

        $this->admin    = User::factory()->create(['role' => 'admin', 'active' => true]);
        $this->park     = Park::factory()->create(['name' => 'TestPark']);
        $unitType       = UnitType::factory()->create(['park_id' => $this->park->id]);
        $this->unit     = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $unitType->id,
            'status'       => 'rented',
        ]);
        $this->customer = Customer::factory()->create();
        $this->contract = Contract::factory()->create([
            'customer_id' => $this->customer->id,
            'unit_id'     => $this->unit->id,
            'status'      => 'active',
            'rent_amount' => 500.00,
        ]);

        $this->token = $this->admin->createToken('test')->plainTextToken;
    }

    public function test_list_invoices(): void
    {
        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'park_id'     => $this->park->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/invoices');

        $response->assertOk()
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_list_invoices_filter_by_park(): void
    {
        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'park_id'     => $this->park->id,
            'status'      => 'draft',
        ]);

        $otherPark = Park::factory()->create(['name' => 'OtherPark']);
        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'park_id'     => $otherPark->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/invoices?park_id=' . $this->park->id);

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_create_manual_invoice(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/invoices', [
                'customer_id' => $this->customer->id,
                'park_id'     => $this->park->id,
                'contract_id' => $this->contract->id,
                'due_date'    => now()->addDays(14)->format('Y-m-d'),
                'tax_rate'    => 19,
                'items'       => [
                    [
                        'description' => 'Monthly Rent',
                        'quantity'    => 1,
                        'unit_price'  => 500.00,
                        'item_type'   => 'rent',
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('total_amount', '595.00');

        $this->assertDatabaseHas('invoices', [
            'customer_id' => $this->customer->id,
            'park_id'     => $this->park->id,
            'status'      => 'draft',
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'description' => 'Monthly Rent',
            'item_type'   => 'rent',
        ]);
    }

    public function test_invoice_number_format(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/invoices', [
                'customer_id' => $this->customer->id,
                'park_id'     => $this->park->id,
                'due_date'    => now()->addDays(14)->format('Y-m-d'),
                'items'       => [
                    ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100],
                ],
            ]);

        $response->assertCreated();
        $number = $response->json('invoice_number');

        // Format: PARK_CODE-YEAR-SEQ
        $this->assertMatchesRegularExpression('/^TEST-\d{4}-\d{4}$/', $number);
    }

    public function test_invoice_number_increments(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'park_id'     => $this->park->id,
            'due_date'    => now()->addDays(14)->format('Y-m-d'),
            'items'       => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100],
            ],
        ];

        $r1 = $this->withToken($this->token)->postJson('/api/invoices', $payload);
        $r2 = $this->withToken($this->token)->postJson('/api/invoices', $payload);

        $n1 = $r1->json('invoice_number');
        $n2 = $r2->json('invoice_number');

        $seq1 = (int) substr($n1, strrpos($n1, '-') + 1);
        $seq2 = (int) substr($n2, strrpos($n2, '-') + 1);

        $this->assertEquals($seq1 + 1, $seq2);
    }

    public function test_generate_monthly_invoices_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->withToken($this->token)
            ->postJson('/api/invoices/generate-monthly');

        $response->assertOk()
            ->assertJsonPath('message', 'Monthly invoice generation queued.');

        Queue::assertPushed(GenerateMonthlyInvoices::class);
    }

    public function test_cancel_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'customer_id'  => $this->customer->id,
            'park_id'      => $this->park->id,
            'status'       => 'sent',
            'total_amount' => 500.00,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/invoices/' . $invoice->id . '/cancel');

        $response->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->assertDatabaseHas('invoices', [
            'id'     => $invoice->id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'item_type'  => 'credit_note',
        ]);
    }

    public function test_cancel_already_cancelled_invoice_fails(): void
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'park_id'     => $this->park->id,
            'status'      => 'cancelled',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/invoices/' . $invoice->id . '/cancel');

        $response->assertUnprocessable();
    }

    public function test_send_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'park_id'     => $this->park->id,
            'status'      => 'draft',
        ]);

        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'description' => 'Rent',
            'quantity'    => 1,
            'unit_price'  => 500,
            'total'       => 500,
            'item_type'   => 'rent',
            'sort_order'  => 0,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/invoices/' . $invoice->id . '/send');

        $response->assertOk()
            ->assertJsonPath('status', 'sent');

        $this->assertDatabaseHas('sent_emails', [
            'customer_id' => $this->customer->id,
        ]);
    }

    public function test_datev_export(): void
    {
        Invoice::factory()->create([
            'customer_id'  => $this->customer->id,
            'park_id'      => $this->park->id,
            'issue_date'   => '2026-01-15',
            'total_amount' => 595.00,
            'status'       => 'sent',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/invoices/datev-export?park_id=' . $this->park->id . '&from=2026-01-01&to=2026-01-31');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $this->assertStringContainsString('EXTF', $response->getContent());
        $this->assertStringContainsString('595', $response->getContent());
    }

    public function test_datev_export_requires_date_range(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/invoices/datev-export?park_id=' . $this->park->id);

        $response->assertUnprocessable();
    }

    public function test_create_invoice_requires_items(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/invoices', [
                'customer_id' => $this->customer->id,
                'park_id'     => $this->park->id,
                'due_date'    => now()->addDays(14)->format('Y-m-d'),
                'items'       => [],
            ]);

        $response->assertUnprocessable();
    }

    public function test_unauthenticated_cannot_access_invoices(): void
    {
        $this->getJson('/api/invoices')->assertUnauthorized();
    }
}
