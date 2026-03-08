<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Models\DiscountRule;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Park;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateMonthlyInvoices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $contracts = Contract::with(['customer', 'unit.park', 'unit.unitType'])
            ->where('status', 'active')
            ->get();

        foreach ($contracts as $contract) {
            $this->generateForContract($contract);
        }
    }

    private function generateForContract(Contract $contract): void
    {
        $unit = $contract->unit;
        if (!$unit) {
            return;
        }

        $park = $unit->park;
        if (!$park) {
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
        $taxRate  = 0;
        $subtotal = $netRent;

        if ($contract->insurance_amount) {
            $subtotal += (float) $contract->insurance_amount;
        }

        $taxAmount   = round($subtotal * $taxRate / 100, 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        $invoiceNumber = $this->generateInvoiceNumber($park);

        $invoice = Invoice::create([
            'contract_id'    => $contract->id,
            'customer_id'    => $contract->customer_id,
            'park_id'        => $park->id,
            'invoice_number' => $invoiceNumber,
            'issue_date'     => now()->format('Y-m-d'),
            'due_date'       => now()->addDays(14)->format('Y-m-d'),
            'subtotal'       => $subtotal,
            'tax_rate'       => $taxRate,
            'tax_amount'     => $taxAmount,
            'total_amount'   => $totalAmount,
            'status'         => 'draft',
        ]);

        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'description' => 'Monthly rent - ' . now()->format('F Y'),
            'quantity'    => 1,
            'unit_price'  => $netRent,
            'total'       => $netRent,
            'item_type'   => 'rent',
            'sort_order'  => 0,
        ]);

        if ($discountAmount > 0) {
            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => 'Discount',
                'quantity'    => 1,
                'unit_price'  => -$discountAmount,
                'total'       => -$discountAmount,
                'item_type'   => 'discount',
                'sort_order'  => 1,
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
                'sort_order'  => 2,
            ]);
        }
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

        if ($last) {
            $seq = (int) substr($last, strlen($prefix)) + 1;
        } else {
            $seq = 1;
        }

        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
