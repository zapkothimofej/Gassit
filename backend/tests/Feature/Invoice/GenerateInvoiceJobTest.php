<?php

namespace Tests\Feature\Invoice;

use App\Jobs\GenerateInvoiceJob;
use App\Jobs\GenerateMonthlyInvoices;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Park;
use App\Models\Unit;
use App\Models\UnitType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenerateInvoiceJobTest extends TestCase
{
    use RefreshDatabase;

    private Park $park;
    private Customer $customer;
    private Unit $unit;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');

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
    }

    public function test_generate_invoice_job_creates_invoice_with_correct_amount(): void
    {
        $month = '2025-01';

        GenerateInvoiceJob::dispatchSync($this->contract->id, $month);

        $this->assertDatabaseHas('invoices', [
            'contract_id'   => $this->contract->id,
            'billing_month' => $month,
        ]);

        $invoice = Invoice::where('contract_id', $this->contract->id)
            ->where('billing_month', $month)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertEquals(500.00, (float) $invoice->subtotal);
        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'item_type'  => 'rent',
        ]);
    }

    public function test_generate_invoice_job_skips_if_already_generated(): void
    {
        $month = '2025-01';

        // Generate once
        GenerateInvoiceJob::dispatchSync($this->contract->id, $month);

        $countBefore = Invoice::where('contract_id', $this->contract->id)->count();

        // Try to generate again for same month — should be idempotent
        GenerateInvoiceJob::dispatchSync($this->contract->id, $month);

        $countAfter = Invoice::where('contract_id', $this->contract->id)->count();

        $this->assertEquals($countBefore, $countAfter, 'Duplicate invoice should not be created for same contract+month.');
    }

    public function test_generate_invoice_job_skips_inactive_contract(): void
    {
        $this->contract->update(['status' => 'terminated_by_customer']);

        GenerateInvoiceJob::dispatchSync($this->contract->id, '2025-01');

        $this->assertDatabaseMissing('invoices', [
            'contract_id' => $this->contract->id,
        ]);
    }

    public function test_generate_monthly_invoices_dispatches_per_contract_job(): void
    {
        Queue::fake();

        $month = '2025-01';

        // Call the job handle directly to test inner dispatch
        $job = new GenerateMonthlyInvoices($month);
        $job->handle();

        Queue::assertPushed(GenerateInvoiceJob::class, function ($job) use ($month) {
            return $job->billingMonth === $month;
        });
    }
}
