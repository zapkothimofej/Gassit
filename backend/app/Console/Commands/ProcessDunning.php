<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\DunningRecord;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MailTemplate;
use App\Models\SentEmail;
use App\Models\SystemSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessDunning extends Command
{
    protected $signature = 'dunning:process';
    protected $description = 'Find overdue invoices and escalate dunning levels';

    public function handle(): int
    {
        $delays = [
            1 => (int) SystemSetting::where('key', 'dunning_delay_1')->value('value') ?: 7,
            2 => (int) SystemSetting::where('key', 'dunning_delay_2')->value('value') ?: 14,
            3 => (int) SystemSetting::where('key', 'dunning_delay_3')->value('value') ?: 30,
        ];

        $fees = [1 => 5.00, 2 => 10.00, 3 => 30.00];

        $overdueInvoices = Invoice::with(['customer', 'dunningRecords', 'items'])
            ->whereDate('due_date', '<', now())
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->get();

        foreach ($overdueInvoices as $invoice) {
            $customer = $invoice->customer;

            if (!$customer) {
                continue;
            }

            // Skip if dunning is paused
            if ($customer->dunning_paused_until && $customer->dunning_paused_until > now()) {
                continue;
            }

            $currentLevel = $invoice->dunningRecords->max('level') ?? 0;

            if ($currentLevel >= 3) {
                continue;
            }

            $nextLevel = $currentLevel + 1;
            $daysOverdue = \Carbon\Carbon::parse($invoice->due_date)->diffInDays(now());

            if ($daysOverdue < $delays[$nextLevel]) {
                continue;
            }

            $this->escalateInvoice($invoice, $customer, $nextLevel, $fees[$nextLevel]);
        }

        return Command::SUCCESS;
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
                'invoice_id'   => $invoice->id,
                'customer_id'  => $customer->id,
                'level'        => $level,
                'sent_at'      => now(),
                'fee_amount'   => $fee,
                'template_used' => $template?->template_type,
            ]);

            // Add dunning fee to invoice
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

            // Update invoice total
            $invoice->increment('total_amount', $fee);

            // Send email
            SentEmail::create([
                'customer_id'     => $customer->id,
                'template_id'     => $template?->id,
                'sent_by'         => null,
                'subject'         => $template?->subject ?? 'Dunning notice level ' . $level,
                'body_html'       => $template?->body_html ?? '<p>Dunning notice level ' . $level . '</p>',
                'recipient_email' => $customer->email,
                'status'          => 'queued',
            ]);

            // Update customer status to debtor
            if (!in_array($customer->status, ['debtor', 'troublemaker', 'blacklisted'])) {
                $customer->update(['status' => 'debtor']);
            }
        });
    }
}
