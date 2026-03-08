<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Deposit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepositController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Deposit::with(['contract', 'customer', 'park']);

        if ($request->filled('park_id')) {
            $query->where('park_id', $request->query('park_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return response()->json($query->paginate(20));
    }

    public function show(int $contractId): JsonResponse
    {
        $contract = Contract::findOrFail($contractId);
        $deposit = Deposit::where('contract_id', $contractId)->firstOrFail();

        return response()->json($deposit->load(['contract', 'customer']));
    }

    public function markReceived(Request $request, int $id): JsonResponse
    {
        $deposit = Deposit::findOrFail($id);

        if ($deposit->status !== 'pending') {
            return response()->json(['message' => 'Deposit can only be marked received when pending.'], 422);
        }

        $data = $request->validate([
            'received_at' => ['required', 'date'],
        ]);

        $old = $deposit->toArray();
        $deposit->update([
            'status'      => 'received',
            'received_at' => $data['received_at'],
        ]);

        $this->writeAuditLog($request, 'deposit_received', $deposit, $old, ['status' => 'received']);

        return response()->json($deposit->fresh());
    }

    public function processReturn(Request $request, int $id): JsonResponse
    {
        $deposit = Deposit::with('contract')->findOrFail($id);

        if (!in_array($deposit->status, ['received', 'held'])) {
            return response()->json(['message' => 'Deposit must be received or held before returning.'], 422);
        }

        $contract = $deposit->contract;
        $terminatedStatuses = ['terminated_by_customer', 'terminated_by_lfg'];
        if (!in_array($contract->status, $terminatedStatuses)) {
            return response()->json(['message' => 'Can only return deposit for terminated contracts.'], 422);
        }

        $data = $request->validate([
            'deduction_amount' => ['nullable', 'numeric', 'min:0'],
            'deduction_reason' => ['nullable', 'string'],
            'return_method'    => ['required', 'in:bank_transfer,mollie_payout'],
        ]);

        $deduction = (float) ($data['deduction_amount'] ?? 0);
        $returnAmount = max(0, (float) $deposit->amount - $deduction);
        $newStatus = $deduction > 0 ? 'partially_returned' : 'returned';

        $old = $deposit->toArray();
        $deposit->update([
            'status'           => $newStatus,
            'return_amount'    => $returnAmount,
            'deduction_amount' => $deduction,
            'deduction_reason' => $data['deduction_reason'] ?? null,
            'return_method'    => $data['return_method'],
            'returned_at'      => now(),
        ]);

        $this->writeAuditLog($request, 'deposit_return', $deposit, $old, [
            'status'        => $newStatus,
            'return_amount' => $returnAmount,
        ]);

        return response()->json($deposit->fresh());
    }

    public function molliePayout(Request $request, int $id): JsonResponse
    {
        $deposit = Deposit::with('customer')->findOrFail($id);

        if (!in_array($deposit->status, ['returned', 'partially_returned'])) {
            return response()->json(['message' => 'Deposit must be processed for return before Mollie payout.'], 422);
        }

        if ($deposit->return_method !== 'mollie_payout') {
            return response()->json(['message' => 'Deposit return method is not Mollie payout.'], 422);
        }

        if ($deposit->mollie_payment_id) {
            return response()->json(['message' => 'Mollie payout already initiated.'], 422);
        }

        $data = $request->validate([
            'customer_iban' => ['required', 'string'],
        ]);

        // Mollie payout stub
        $molliePaymentId = 'mollie-payout-' . uniqid();

        $old = $deposit->toArray();
        $deposit->update(['mollie_payment_id' => $molliePaymentId]);

        $this->writeAuditLog($request, 'deposit_mollie_payout', $deposit, $old, [
            'mollie_payment_id' => $molliePaymentId,
            'customer_iban'     => $data['customer_iban'],
        ]);

        return response()->json([
            'deposit'           => $deposit->fresh(),
            'mollie_payment_id' => $molliePaymentId,
            'amount'            => $deposit->return_amount,
            'customer_iban'     => $data['customer_iban'],
        ]);
    }

    private function writeAuditLog(Request $request, string $action, Deposit $deposit, ?array $old, array $new): void
    {
        AuditLog::create([
            'user_id'    => $request->user()?->id,
            'action'     => $action,
            'model_type' => Deposit::class,
            'model_id'   => $deposit->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
        ]);
    }
}
