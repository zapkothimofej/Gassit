<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\ContractRenewal;
use App\Models\ContractSignature;
use App\Models\DamageReport;
use App\Models\Deposit;
use App\Models\DocumentTemplate;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Unit;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContractController extends Controller
{
    private const STATUS_TRANSITIONS = [
        'draft'               => ['awaiting_signature', 'declined'],
        'awaiting_signature'  => ['signed', 'declined'],
        'signed'              => ['active', 'declined'],
        'active'              => ['terminated_by_customer', 'terminated_by_lfg', 'expired'],
        'terminated_by_customer' => [],
        'terminated_by_lfg'   => [],
        'declined'            => [],
        'expired'             => [],
    ];

    public function generateFromApplication(Request $request, int $applicationId): JsonResponse
    {
        $application = Application::with(['customer', 'unit'])->findOrFail($applicationId);

        $data = $request->validate([
            'start_date'         => ['required', 'date'],
            'rent_amount'        => ['required', 'numeric', 'min:0'],
            'deposit_amount'     => ['required', 'numeric', 'min:0'],
            'insurance_amount'   => ['nullable', 'numeric', 'min:0'],
            'notice_period_days' => ['sometimes', 'integer', 'min:1'],
            'unit_id'            => ['required', 'exists:units,id'],
        ]);

        // Stub PDF generation from document template
        $template = DocumentTemplate::where('document_type', 'rental_contract')
            ->where(function ($q) use ($application) {
                $q->where('park_id', $application->park_id)->orWhereNull('park_id');
            })
            ->where('active', true)
            ->first();

        $pdfPath = "contracts/draft-{$applicationId}-" . now()->format('Ymd_His') . '.pdf';
        $pdfContent = $template?->template_html ?? '<html><body>Contract for application ' . $applicationId . '</body></html>';
        Storage::disk('s3')->put($pdfPath, $pdfContent);

        $contract = Contract::create([
            'application_id'     => $application->id,
            'customer_id'        => $application->customer_id,
            'unit_id'            => $data['unit_id'],
            'start_date'         => $data['start_date'],
            'rent_amount'        => $data['rent_amount'],
            'deposit_amount'     => $data['deposit_amount'],
            'insurance_amount'   => $data['insurance_amount'] ?? 0,
            'notice_period_days' => $data['notice_period_days'] ?? 30,
            'status'             => 'draft',
            'signed_pdf_path'    => $pdfPath,
        ]);

        $this->writeAuditLog($request, 'generate_contract', $contract, null, $contract->toArray());

        return response()->json($contract->load(['customer', 'unit', 'application']), 201);
    }

    public function index(Request $request): JsonResponse
    {
        $query = Contract::with(['customer', 'unit', 'application']);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->query('customer_id'));
        }

        if ($request->filled('park_id')) {
            $parkId = (int) $request->query('park_id');
            $query->whereHas('unit', fn ($q) => $q->where('park_id', $parkId));
        }

        return response()->json($query->paginate(20));
    }

    public function show(int $id): JsonResponse
    {
        $contract = Contract::with(['customer', 'unit', 'application', 'signatures', 'renewals'])->findOrFail($id);
        return response()->json($contract);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $contract = Contract::findOrFail($id);
        $old = $contract->toArray();

        $data = $request->validate([
            'start_date'         => ['sometimes', 'date'],
            'end_date'           => ['sometimes', 'nullable', 'date'],
            'rent_amount'        => ['sometimes', 'numeric', 'min:0'],
            'deposit_amount'     => ['sometimes', 'numeric', 'min:0'],
            'insurance_amount'   => ['sometimes', 'numeric', 'min:0'],
            'notice_period_days' => ['sometimes', 'integer', 'min:1'],
            'notes'              => ['sometimes', 'nullable', 'string'],
        ]);

        $contract->update($data);

        $this->writeAuditLog($request, 'update', $contract, $old, $contract->fresh()->toArray());

        return response()->json($contract->fresh()->load(['customer', 'unit']));
    }

    public function sendForSignature(Request $request, int $id): JsonResponse
    {
        $contract = Contract::findOrFail($id);

        if (!in_array('awaiting_signature', self::STATUS_TRANSITIONS[$contract->status] ?? [], true)) {
            return response()->json(['message' => "Cannot transition contract from '{$contract->status}' to 'awaiting_signature'."], 422);
        }

        // E-sign provider stub (DocuSign/HelloSign)
        $esignProviderId = 'esign-stub-' . uniqid();
        $old = $contract->toArray();

        $contract->update([
            'status'              => 'awaiting_signature',
        ]);

        $this->writeAuditLog($request, 'send_for_signature', $contract, $old, [
            'status'            => 'awaiting_signature',
            'esign_provider_id' => $esignProviderId,
        ]);

        return response()->json([
            'contract'          => $contract->fresh(),
            'esign_provider_id' => $esignProviderId,
            'sign_url'          => 'https://esign-stub.example.com/sign/' . $esignProviderId,
        ]);
    }

    public function esignWebhook(Request $request): JsonResponse
    {
        $secret = config('services.esign.webhook_secret');
        if ($secret && $request->header('X-Esign-Secret') !== $secret) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $data = $request->validate([
            'esign_provider_id' => ['required', 'string'],
            'contract_id'       => ['required', 'exists:contracts,id'],
            'signer_type'       => ['required', 'in:customer,lfg'],
            'signer_name'       => ['required', 'string'],
            'signed_at'         => ['required', 'date'],
            'signed_pdf_url'    => ['nullable', 'string'],
        ]);

        $contract = Contract::findOrFail($data['contract_id']);

        if ($contract->status !== 'awaiting_signature') {
            return response()->json(['message' => 'Contract is not awaiting signature.'], 422);
        }

        // Store signed PDF stub
        $pdfPath = "contracts/signed-{$contract->id}-" . now()->format('Ymd_His') . '.pdf';
        Storage::disk('s3')->put($pdfPath, '%PDF-1.4 signed contract stub');

        $old = $contract->toArray();
        $contract->update([
            'status'          => 'signed',
            'signed_pdf_path' => $pdfPath,
            'signed_at'       => Carbon::parse($data['signed_at']),
        ]);

        ContractSignature::create([
            'contract_id'       => $contract->id,
            'signer_type'       => $data['signer_type'],
            'signer_name'       => $data['signer_name'],
            'signed_at'         => Carbon::parse($data['signed_at']),
            'ip_address'        => $request->ip(),
            'esign_provider_id' => $data['esign_provider_id'],
        ]);

        $this->writeAuditLog($request, 'esign_webhook', $contract, $old, ['status' => 'signed', 'pdf_path' => $pdfPath]);

        return response()->json(['message' => 'Signature recorded.', 'contract' => $contract->fresh()]);
    }

    public function activate(Request $request, int $id): JsonResponse
    {
        $contract = Contract::with('unit')->findOrFail($id);

        if (!in_array('active', self::STATUS_TRANSITIONS[$contract->status] ?? [], true)) {
            return response()->json(['message' => "Cannot transition contract from '{$contract->status}' to 'active'."], 422);
        }

        $old = $contract->toArray();
        $contract->update(['status' => 'active']);

        // Unit → rented
        $contract->unit->update(['status' => 'rented']);

        // Create deposit record
        $deposit = Deposit::create([
            'contract_id' => $contract->id,
            'customer_id' => $contract->customer_id,
            'park_id'     => $contract->unit->park_id,
            'amount'      => $contract->deposit_amount,
            'status'      => 'pending',
        ]);

        $this->writeAuditLog($request, 'activate', $contract, $old, [
            'status'     => 'active',
            'deposit_id' => $deposit->id,
        ]);

        return response()->json([
            'contract' => $contract->fresh()->load(['unit', 'customer']),
            'deposit'  => $deposit,
        ]);
    }

    public function terminate(Request $request, int $id): JsonResponse
    {
        $contract = Contract::findOrFail($id);

        $data = $request->validate([
            'termination_type'        => ['required', 'in:customer,lfg'],
            'termination_notice_date' => ['required', 'date'],
            'termination_reason_id'   => ['nullable', 'integer'],
        ]);

        $newStatus = $data['termination_type'] === 'customer'
            ? 'terminated_by_customer'
            : 'terminated_by_lfg';

        if (!in_array($newStatus, self::STATUS_TRANSITIONS[$contract->status] ?? [], true)) {
            return response()->json(['message' => "Cannot transition contract from '{$contract->status}' to '{$newStatus}'."], 422);
        }

        // Validate: termination date must be >= notice_date + notice_period_days
        $noticeDate   = Carbon::parse($data['termination_notice_date']);
        $earliest     = $noticeDate->copy()->addDays($contract->notice_period_days);
        $terminatedAt = now();

        if ($terminatedAt->lt($earliest)) {
            return response()->json([
                'message'              => "Termination requires {$contract->notice_period_days} days notice. Earliest termination: {$earliest->toDateString()}.",
                'earliest_termination' => $earliest->toDateString(),
            ], 422);
        }

        $old = $contract->toArray();
        $contract->update([
            'status'                  => $newStatus,
            'terminated_at'           => $terminatedAt,
            'termination_notice_date' => $data['termination_notice_date'],
            'termination_reason_id'   => $data['termination_reason_id'] ?? null,
        ]);

        // Set unit to maintenance until inspection completes
        $unit = $contract->unit()->with('park')->first();
        if ($unit) {
            $unit->update(['status' => 'maintenance']);
        }

        // Create pro-rated final invoice for current month
        $today        = $terminatedAt->copy()->startOfDay();
        $daysInMonth  = $today->daysInMonth;
        $daysUsed     = $today->day;
        $dailyRate    = round((float) $contract->rent_amount / $daysInMonth, 4);
        $proratedRent = round($dailyRate * $daysUsed, 2);

        $park = $unit?->park;
        if ($park) {
            $billingMonth = $today->format('Y-m') . '-final';
            $existing = Invoice::where('contract_id', $contract->id)
                ->where('billing_month', $billingMonth)
                ->first();

            if (!$existing) {
                $invoiceService = new InvoiceService();
                $invoiceNumber  = $invoiceService->generateInvoiceNumber($park);

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
        }

        // Create termination inspection damage report
        if ($unit) {
            DamageReport::create([
                'unit_id'                   => $unit->id,
                'contract_id'               => $contract->id,
                'reported_by'               => $request->user()->id,
                'description'               => 'Termination inspection',
                'status'                    => 'reported',
                'is_termination_inspection' => true,
            ]);
        }

        $this->writeAuditLog($request, 'terminate', $contract, $old, [
            'status'        => $newStatus,
            'terminated_at' => $terminatedAt->toIso8601String(),
        ]);

        return response()->json($contract->fresh()->load(['customer', 'unit']));
    }

    public function renew(Request $request, int $id): JsonResponse
    {
        $contract = Contract::with('unit')->findOrFail($id);

        if (!in_array('expired', self::STATUS_TRANSITIONS[$contract->status] ?? [], true)) {
            return response()->json(['message' => "Cannot renew a contract in '{$contract->status}' status."], 422);
        }

        $data = $request->validate([
            'start_date'         => ['required', 'date'],
            'rent_amount'        => ['required', 'numeric', 'min:0'],
            'deposit_amount'     => ['sometimes', 'numeric', 'min:0'],
            'insurance_amount'   => ['sometimes', 'numeric', 'min:0'],
            'notice_period_days' => ['sometimes', 'integer', 'min:1'],
        ]);

        // Create new contract
        $newContract = Contract::create([
            'application_id'     => $contract->application_id,
            'customer_id'        => $contract->customer_id,
            'unit_id'            => $contract->unit_id,
            'start_date'         => $data['start_date'],
            'rent_amount'        => $data['rent_amount'],
            'deposit_amount'     => $data['deposit_amount'] ?? $contract->deposit_amount,
            'insurance_amount'   => $data['insurance_amount'] ?? $contract->insurance_amount,
            'notice_period_days' => $data['notice_period_days'] ?? $contract->notice_period_days,
            'status'             => 'draft',
        ]);

        // Link via contract_renewals
        $renewal = ContractRenewal::create([
            'contract_id'     => $contract->id,
            'new_contract_id' => $newContract->id,
            'renewed_at'      => now(),
            'new_rent_amount' => $data['rent_amount'],
        ]);

        // Old contract → expired
        $old = $contract->toArray();
        $contract->update(['status' => 'expired']);

        $this->writeAuditLog($request, 'renew', $contract, $old, [
            'status'          => 'expired',
            'new_contract_id' => $newContract->id,
        ]);

        return response()->json([
            'old_contract' => $contract->fresh(),
            'new_contract' => $newContract->load(['customer', 'unit']),
            'renewal'      => $renewal,
        ], 201);
    }

    private function writeAuditLog(Request $request, string $action, Contract $model, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id'    => $request->user()?->id,
            'action'     => $action,
            'model_type' => Contract::class,
            'model_id'   => $model->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
