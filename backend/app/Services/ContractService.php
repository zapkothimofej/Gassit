<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\DamageReport;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ContractService
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    public const STATUS_TRANSITIONS = [
        'draft'               => ['awaiting_signature', 'declined'],
        'awaiting_signature'  => ['signed', 'declined'],
        'signed'              => ['active', 'declined'],
        'active'              => ['terminated_by_customer', 'terminated_by_lfg', 'expired'],
        'terminated_by_customer' => [],
        'terminated_by_lfg'   => [],
        'declined'            => [],
        'expired'             => [],
    ];

    public function canTransition(Contract $contract, string $newStatus): bool
    {
        return in_array($newStatus, self::STATUS_TRANSITIONS[$contract->status] ?? [], true);
    }

    /**
     * @return array{contract: Contract}|array{error: string, earliest: string}
     */
    public function terminate(Contract $contract, string $type, string $noticeDateStr, ?int $reasonId, ?int $actorId): array
    {
        $newStatus = $type === 'customer'
            ? 'terminated_by_customer'
            : 'terminated_by_lfg';

        if (!$this->canTransition($contract, $newStatus)) {
            return ['error' => "Cannot transition contract from '{$contract->status}' to '{$newStatus}'."];
        }

        $noticeDate   = Carbon::parse($noticeDateStr);
        $earliest     = $noticeDate->copy()->addDays($contract->notice_period_days);
        $terminatedAt = now();

        if ($terminatedAt->lt($earliest)) {
            return [
                'error'    => "Termination requires {$contract->notice_period_days} days notice. Earliest termination: {$earliest->toDateString()}.",
                'earliest' => $earliest->toDateString(),
            ];
        }

        return DB::transaction(function () use ($contract, $newStatus, $noticeDateStr, $reasonId, $actorId, $terminatedAt) {
            $contract->update([
                'status'                  => $newStatus,
                'terminated_at'           => $terminatedAt,
                'termination_notice_date' => $noticeDateStr,
                'termination_reason_id'   => $reasonId,
            ]);

            $unit = $contract->unit()->with('park')->first();
            if ($unit) {
                $unit->update(['status' => 'maintenance']);
            }

            $this->createFinalInvoice($contract, $unit, $terminatedAt);
            $this->createTerminationInspection($contract, $unit, $actorId);

            return ['contract' => $contract->fresh()->load(['customer', 'unit'])];
        });
    }

    private function createFinalInvoice(Contract $contract, ?Unit $unit, Carbon $terminatedAt): void
    {
        $park = $unit?->park;
        if (!$park) {
            return;
        }

        $today        = $terminatedAt->copy()->startOfDay();
        $daysInMonth  = $today->daysInMonth;
        $daysUsed     = $today->day;
        $dailyRate    = round((float) $contract->rent_amount / $daysInMonth, 4);
        $proratedRent = round($dailyRate * $daysUsed, 2);

        $billingMonth = $today->format('Y-m') . '-final';
        $existing = Invoice::where('contract_id', $contract->id)
            ->where('billing_month', $billingMonth)
            ->first();

        if ($existing) {
            return;
        }

        $invoiceNumber = $this->invoiceService->generateInvoiceNumber($park);

        $invoice = Invoice::create([
            'contract_id'    => $contract->id,
            'customer_id'    => $contract->customer_id,
            'park_id'        => $park->id,
            'invoice_number' => $invoiceNumber,
            'billing_month'  => $billingMonth,
            'issue_date'     => $today->format('Y-m-d'),
            'due_date'       => $today->copy()->addDays(14)->format('Y-m-d'),
            'subtotal'       => $proratedRent,
            'tax_rate'       => 0,
            'tax_amount'     => 0,
            'total_amount'   => $proratedRent,
            'status'         => 'draft',
        ]);

        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'description' => "Final invoice (pro-rated {$daysUsed}/{$daysInMonth} days) - {$today->format('Y-m')}",
            'quantity'    => 1,
            'unit_price'  => $proratedRent,
            'total'       => $proratedRent,
            'item_type'   => 'rent',
            'sort_order'  => 0,
        ]);
    }

    private function createTerminationInspection(Contract $contract, ?Unit $unit, ?int $actorId): void
    {
        if (!$unit) {
            return;
        }

        DamageReport::create([
            'unit_id'                   => $unit->id,
            'contract_id'               => $contract->id,
            'reported_by'               => $actorId,
            'description'               => 'Termination inspection',
            'status'                    => 'reported',
            'is_termination_inspection' => true,
        ]);
    }
}
