<?php

namespace Tests\Feature\Pdf;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\DocumentTemplate;
use App\Models\DunningRecord;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Park;
use App\Models\Unit;
use App\Models\UnitType;
use App\Services\PdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfServiceTest extends TestCase
{
    use RefreshDatabase;

    private PdfService $service;
    private Park $park;
    private Customer $customer;
    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');

        $this->service  = new PdfService();
        $this->park     = Park::factory()->create(['name' => 'Test Park', 'bank_iban' => 'DE89000000001234567890']);
        $unitType       = UnitType::factory()->create(['park_id' => $this->park->id]);
        $this->unit     = Unit::factory()->create([
            'park_id'      => $this->park->id,
            'unit_type_id' => $unitType->id,
            'unit_number'  => 'A-101',
        ]);
        $this->customer = Customer::factory()->create([
            'first_name' => 'Max',
            'last_name'  => 'Mustermann',
            'email'      => 'max@example.com',
        ]);
    }

    public function test_generate_contract_creates_pdf_and_stores_to_s3(): void
    {
        $contract = Contract::factory()->create([
            'customer_id' => $this->customer->id,
            'unit_id'     => $this->unit->id,
            'status'      => 'draft',
        ]);

        $path = $this->service->generateContract($contract);

        $this->assertStringStartsWith('contracts/contract-', $path);
        $this->assertStringEndsWith('.pdf', $path);
        Storage::disk('s3')->assertExists($path);

        $contract->refresh();
        $this->assertEquals($path, $contract->signed_pdf_path);
    }

    public function test_generate_contract_uses_document_template_when_available(): void
    {
        DocumentTemplate::create([
            'park_id'       => null,
            'name'          => 'Contract Template',
            'document_type' => 'rental_contract',
            'template_html' => '<html><body><p>Vertrag für {customer_name}</p></body></html>',
            'version'       => 1,
            'active'        => true,
        ]);

        $contract = Contract::factory()->create([
            'customer_id' => $this->customer->id,
            'unit_id'     => $this->unit->id,
        ]);

        $path = $this->service->generateContract($contract);

        Storage::disk('s3')->assertExists($path);
        $this->assertStringStartsWith('contracts/', $path);
    }

    public function test_generate_invoice_creates_pdf_and_stores_to_s3(): void
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'park_id'     => $this->park->id,
        ]);

        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'description' => 'Monatsmiete',
            'quantity'    => 1,
            'unit_price'  => 500.00,
            'total'       => 500.00,
            'item_type'   => 'rent',
            'sort_order'  => 1,
        ]);

        $path = $this->service->generateInvoice($invoice);

        $this->assertStringStartsWith('invoices/invoice-', $path);
        $this->assertStringEndsWith('.pdf', $path);
        Storage::disk('s3')->assertExists($path);

        $invoice->refresh();
        $this->assertEquals($path, $invoice->pdf_path);
    }

    public function test_generate_invoice_uses_document_template_when_available(): void
    {
        DocumentTemplate::create([
            'park_id'       => $this->park->id,
            'name'          => 'Invoice Template',
            'document_type' => 'invoice',
            'template_html' => '<html><body><p>Rechnung {invoice_number} für {customer_name}</p></body></html>',
            'version'       => 1,
            'active'        => true,
        ]);

        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'park_id'     => $this->park->id,
        ]);

        $path = $this->service->generateInvoice($invoice);

        Storage::disk('s3')->assertExists($path);
    }

    public function test_generate_dunning_letter_creates_pdf_and_stores_to_s3(): void
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'park_id'     => $this->park->id,
        ]);

        $dunning = DunningRecord::factory()->create([
            'invoice_id'  => $invoice->id,
            'customer_id' => $this->customer->id,
            'level'       => 1,
            'fee_amount'  => 10.00,
        ]);

        $path = $this->service->generateDunningLetter($dunning);

        $this->assertStringStartsWith('dunning/dunning-', $path);
        $this->assertStringContainsString('level1', $path);
        $this->assertStringEndsWith('.pdf', $path);
        Storage::disk('s3')->assertExists($path);

        $dunning->refresh();
        $this->assertEquals($path, $dunning->template_used);
    }

    public function test_generate_dunning_letter_for_all_levels(): void
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'park_id'     => $this->park->id,
        ]);

        foreach ([1, 2, 3] as $level) {
            $dunning = DunningRecord::factory()->create([
                'invoice_id'  => $invoice->id,
                'customer_id' => $this->customer->id,
                'level'       => $level,
                'fee_amount'  => $level * 10.00,
            ]);

            $path = $this->service->generateDunningLetter($dunning);
            $this->assertStringContainsString("level{$level}", $path);
            Storage::disk('s3')->assertExists($path);
        }
    }

    public function test_generate_deposit_return_creates_pdf_and_stores_to_s3(): void
    {
        $contract = Contract::factory()->create([
            'customer_id' => $this->customer->id,
            'unit_id'     => $this->unit->id,
            'status'      => 'terminated_by_customer',
        ]);

        $deposit = Deposit::factory()->create([
            'contract_id'      => $contract->id,
            'customer_id'      => $this->customer->id,
            'park_id'          => $this->park->id,
            'amount'           => 500.00,
            'return_amount'    => 450.00,
            'deduction_amount' => 50.00,
            'deduction_reason' => 'Schäden',
            'return_method'    => 'bank_transfer',
            'status'           => 'partially_returned',
        ]);

        $path = $this->service->generateDepositReturn($deposit);

        $this->assertStringStartsWith('deposits/deposit-return-', $path);
        $this->assertStringEndsWith('.pdf', $path);
        Storage::disk('s3')->assertExists($path);
    }

    public function test_generate_deposit_return_uses_document_template_when_available(): void
    {
        DocumentTemplate::create([
            'park_id'       => null,
            'name'          => 'Deposit Return Template',
            'document_type' => 'deposit_return',
            'template_html' => '<html><body><p>Kaution für {customer_name}: {return_amount}</p></body></html>',
            'version'       => 1,
            'active'        => true,
        ]);

        $contract = Contract::factory()->create([
            'customer_id' => $this->customer->id,
            'unit_id'     => $this->unit->id,
        ]);

        $deposit = Deposit::factory()->create([
            'contract_id' => $contract->id,
            'customer_id' => $this->customer->id,
            'park_id'     => $this->park->id,
        ]);

        $path = $this->service->generateDepositReturn($deposit);

        Storage::disk('s3')->assertExists($path);
    }

    public function test_park_specific_template_takes_priority_over_global(): void
    {
        DocumentTemplate::create([
            'park_id'       => null,
            'name'          => 'Global Template',
            'document_type' => 'rental_contract',
            'template_html' => '<html><body>Global: {customer_name}</body></html>',
            'version'       => 1,
            'active'        => true,
        ]);

        DocumentTemplate::create([
            'park_id'       => $this->park->id,
            'name'          => 'Park Template',
            'document_type' => 'rental_contract',
            'template_html' => '<html><body>Park: {customer_name}</body></html>',
            'version'       => 1,
            'active'        => true,
        ]);

        $contract = Contract::factory()->create([
            'customer_id' => $this->customer->id,
            'unit_id'     => $this->unit->id,
        ]);

        $path = $this->service->generateContract($contract);

        Storage::disk('s3')->assertExists($path);
    }
}
