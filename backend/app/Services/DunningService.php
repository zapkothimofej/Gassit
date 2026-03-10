<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\DunningRecord;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MailTemplate;
use App\Models\Payment;
use App\Models\SentEmail;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DunningService
{
    public const LEVEL_DAYS = [1 => 7, 2 => 14, 3 => 21];
    public const MAX_LEVEL = 3;
    public const FEES = [1 => 5.00, 2 => 10.00, 3 => 30.00];

    public function processDunning(): int
    {
        $delays = [
            1 => (int) SystemSetting::where('key', 'dunning_delay_1')->value('value') ?: self::LEVEL_DAYS[1],
            2 => (int) SystemSetting::where('key', 'dunning_delay_2')->value('value') ?: self::LEVEL_DAYS[2],
            3 => (int) SystemSetting::where('key', 'dunning_delay_3')->value('value') ?: self::LEVEL_DAYS[3],
        ];

        $overdueInvoices = Invoice::with(['customer', 'dunningRecords', 'items'])
            ->whereDate('due_date', '<', now())
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->get();

        $escalated = 0;

        foreach ($overdueInvoices as $invoice) {
            $customer = $invoice->customer;

            if (!$customer) {
                continue;
            }

            if ($customer->dunning_paused_until && $customer->dunning_paused_until > now()) {
                continue;
            }

            $currentLevel = $invoice->dunningRecords->max('level') ?? 0;

            if ($currentLevel >= self::MAX_LEVEL) {
                continue;
            }

            $nextLevel = $currentLevel + 1;
            $daysOverdue = Carbon::parse($invoice->due_date)->diffInDays(now());

            if ($daysOverdue < $delays[$nextLevel]) {
                continue;
            }

            $this->escalateInvoice($invoice, $customer, $nextLevel, self::FEES[$nextLevel]);
            $escalated++;
        }

        return $escalated;
    }

    public function escalateInvoice(Invoice $invoice, Customer $customer, int $level, float $fee): void
    {
        DB::transaction(function () use ($invoice, $customer, $level, $fee) {
            $templateType = 'dunning_' . $level;

            $template = MailTemplate::where('template_type', $templateType)
                ->where(function ($q) use ($invoice) {
                    $q->where('park_id', $invoice->park_id)->orWhereNull('park_id');
                })
                ->where('active', true)
                ->orderByRaw('park_id IS NULL ASC')
                ->first();

            DunningRecord::create([
                'invoice_id'    => $invoice->id,
                'customer_id'   => $customer->id,
                'level'         => $level,
                'sent_at'       => now(),
                'fee_amount'    => $fee,
                'template_used' => $template?->template_type,
            ]);

            $sortOrder = $invoice->items()->count();
            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => 'Dunning fee level ' . $level,
                'quantity'    => 1,
                'unit_price'  => $fee,
                'total'       => $fee,
                'item_type'   => 'dunning_fee',
                'sort_order'  => $sortOrder,
            ]);

            $invoice->increment('total_amount', $fee);

            SentEmail::create([
                'customer_id'     => $customer->id,
                'template_id'     => $template?->id,
                'sent_by'         => null,
                'subject'         => $template?->subject ?? 'Dunning notice level ' . $level,
                'body_html'       => $template?->body_html ?? '<p>Dunning notice level ' . $level . '</p>',
                'recipient_email' => $customer->email,
                'status'          => 'queued',
            ]);

            if (!in_array($customer->status, ['debtor', 'troublemaker', 'blacklisted'])) {
                $customer->update(['status' => 'debtor']);
            }
        });
    }

    public function resolveCustomer(Customer $customer, string $reference): int
    {
        $overdueInvoices = Invoice::where('customer_id', $customer->id)
            ->whereDate('due_date', '<', now())
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->get();

        $count = $overdueInvoices->count();

        DB::transaction(function () use ($overdueInvoices, $customer, $reference) {
            foreach ($overdueInvoices as $invoice) {
                $invoice->update([
                    'status'  => 'paid',
                    'paid_at' => now(),
                ]);

                Payment::create([
                    'invoice_id'        => $invoice->id,
                    'amount'            => $invoice->total_amount,
                    'currency'          => 'EUR',
                    'payment_method'    => 'bank_transfer',
                    'status'            => 'paid',
                    'paid_at'           => now(),
                    'mollie_payment_id' => $reference,
                ]);
            }

            $customer->update([
                'status'               => 'tenant',
                'dunning_paused_until' => null,
            ]);
        });

        return $count;
    }
}
