<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Models\DiscountRule;
use App\Models\ElectricityMeter;
use App\Models\ElectricityPricing;
use App\Models\ElectricityReading;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Park;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public readonly int $contractId,
        public readonly string $billingMonth, // Format: Y-m (e.g. "2025-01")
    ) {}

    public function handle(): void
    {
        $contract = Contract::with(['customer', 'unit.park', 'unit.unitType'])
            ->find($this->contractId);

        if (!$contract || $contract->status !== 'active') {
            Log::info("GenerateInvoiceJob: contract {$this->contractId} not found or not active, skipping.");
            return;
        }

        $unit = $contract->unit;
        $park = $unit?->park;

        if (!$unit || !$park) {
            Log::warning("GenerateInvoiceJob: contract {$this->contractId} has no unit or park, skipping.");
            return;
        }

        // Idempotency check: skip if invoice already exists for this contract+month
        $existing = Invoice::where('contract_id', $this->contractId)
            ->where('billing_month', $this->billingMonth)
            ->first();

        if ($existing) {
            Log::info("GenerateInvoiceJob: invoice already exists for contract {$this->contractId} month {$this->billingMonth}, skipping.");
            return;
        }

        $rentAmount = (float) $contract->rent_amount;

        // Apply active discount rules
        $monthsSinceStart = (int) now()->diffInMonths($contract->start_date);
        $discounts = DiscountRule::where('park_id', $park->id)
            ->where('active', true)
            ->where(function ($q) use ($unit) {
                $q->whereNull('unit_type_id')
                  ->orWhere('unit_type_id', $unit->unit_type_id);
            })
            ->get();

        $discountAmount = 0;
        foreach ($discounts as $rule) {
            $from = $rule->applies_from_month;
            $to   = $rule->applies_to_month;

            if ($from !== null && $monthsSinceStart < $from) {
                continue;
            }
            if ($to !== null && $monthsSinceStart > $to) {
                continue;
            }

            if ($rule->discount_type === 'percentage') {
                $discountAmount += round($rentAmount * (float) $rule->discount_value / 100, 2);
            } else {
                $discountAmount += (float) $rule->discount_value;
            }
        }

        $netRent  = round($rentAmount - $discountAmount, 2);
        $subtotal = $netRent;

        if ($contract->insurance_amount) {
            $subtotal += (float) $contract->insurance_amount;
        }

        // Add unbilled electricity charges
        $electricityTotal = $this->calculateElectricityCharges($unit->id, $park->id);
        $subtotal += $electricityTotal;

        $taxRate    = 0;
        $taxAmount  = round($subtotal * $taxRate / 100, 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        $invoiceNumber = $this->generateInvoiceNumber($park);

        $invoice = Invoice::create([
            'contract_id'    => $contract->id,
            'customer_id'    => $contract->customer_id,
            'park_id'        => $park->id,
            'invoice_number' => $invoiceNumber,
            'billing_month'  => $this->billingMonth,
            'issue_date'     => now()->format('Y-m-d'),
            'due_date'       => now()->addDays(14)->format('Y-m-d'),
            'subtotal'       => $subtotal,
            'tax_rate'       => $taxRate,
            'tax_amount'     => $taxAmount,
            'total_amount'   => $totalAmount,
            'status'         => 'draft',
        ]);

        $sortOrder = 0;

        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'description' => 'Monthly rent - ' . $this->billingMonth,
            'quantity'    => 1,
            'unit_price'  => $netRent,
            'total'       => $netRent,
            'item_type'   => 'rent',
            'sort_order'  => $sortOrder++,
        ]);

        if ($discountAmount > 0) {
            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => 'Discount',
                'quantity'    => 1,
                'unit_price'  => -$discountAmount,
                'total'       => -$discountAmount,
                'item_type'   => 'discount',
                'sort_order'  => $sortOrder++,
            ]);
        }

        if ($contract->insurance_amount) {
            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => 'Insurance',
                'quantity'    => 1,
                'unit_price'  => (float) $contract->insurance_amount,
                'total'       => (float) $contract->insurance_amount,
                'item_type'   => 'insurance',
                'sort_order'  => $sortOrder++,
            ]);
        }

        if ($electricityTotal > 0) {
            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => 'Electricity charges',
                'quantity'    => 1,
                'unit_price'  => $electricityTotal,
                'total'       => $electricityTotal,
                'item_type'   => 'electricity',
                'sort_order'  => $sortOrder,
            ]);

            // Mark readings as billed
            ElectricityMeter::where('unit_id', $unit->id)
                ->where('active', true)
                ->each(function (ElectricityMeter $meter) use ($invoice) {
                    ElectricityReading::where('meter_id', $meter->id)
                        ->whereNull('invoice_id')
                        ->update(['invoice_id' => $invoice->id]);
                });
        }

        Log::info("GenerateInvoiceJob: created invoice {$invoice->invoice_number} for contract {$this->contractId}.");
    }

    private function calculateElectricityCharges(int $unitId, int $parkId): float
    {
        $meters = ElectricityMeter::where('unit_id', $unitId)->where('active', true)->get();
        if ($meters->isEmpty()) {
            return 0;
        }

        $pricing = ElectricityPricing::where('park_id', $parkId)->orderByDesc('valid_from')->first();
        if (!$pricing) {
            return 0;
        }

        $pricePerKwh = (float) $pricing->price_per_kwh;
        $total = 0;

        foreach ($meters as $meter) {
            $unbilledReadings = ElectricityReading::where('meter_id', $meter->id)
                ->whereNull('invoice_id')
                ->orderBy('reading_date')
                ->get();

            foreach ($unbilledReadings as $reading) {
                if ($reading->consumption !== null) {
                    $total += (float) $reading->consumption * $pricePerKwh;
                }
            }
        }

        return round($total, 2);
    }

    private function generateInvoiceNumber(Park $park): string
    {
        $code = strtoupper(preg_replace('/[^A-Za-z]/', '', $park->name));
        $code = substr($code, 0, 4) ?: 'PARK';
        $year = now()->year;
        $prefix = $code . '-' . $year . '-';

        $last = Invoice::where('invoice_number', 'like', $prefix . '%')
            ->orderByDesc('invoice_number')
            ->value('invoice_number');

        $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
