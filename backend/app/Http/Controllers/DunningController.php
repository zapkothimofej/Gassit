<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Services\DunningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DunningController extends Controller
{
    public function __construct(private readonly DunningService $dunningService) {}

    public function debtors(Request $request): JsonResponse
    {
        $query = Customer::query()
            ->with(['invoices' => function ($q) {
                $q->whereDate('due_date', '<', now())
                  ->whereNotIn('status', ['paid', 'cancelled'])
                  ->with('dunningRecords');
            }])
            ->whereHas('invoices', function ($q) {
                $q->whereDate('due_date', '<', now())
                  ->whereNotIn('status', ['paid', 'cancelled']);
            });

        if ($request->filled('park_id')) {
            $query->whereHas('invoices', function ($q) use ($request) {
                $q->where('park_id', $request->query('park_id'))
                  ->whereDate('due_date', '<', now())
                  ->whereNotIn('status', ['paid', 'cancelled']);
            });
        }

        $filterLevel = $request->filled('dunning_level') ? (int) $request->query('dunning_level') : null;

        $customers = $query->get()->map(function (Customer $customer) {
            $overdueInvoices = $customer->invoices;

            $totalOwed     = $overdueInvoices->sum('total_amount');
            $maxLevel      = $overdueInvoices->flatMap->dunningRecords->max('level') ?? 0;
            $oldestDueDate = $overdueInvoices->min('due_date');
            $daysOverdue   = $oldestDueDate ? \Carbon\Carbon::parse($oldestDueDate)->diffInDays(now()) : 0;

            return [
                'customer'       => $customer->only(['id', 'first_name', 'last_name', 'company_name', 'email', 'status', 'dunning_paused_until']),
                'total_owed'     => round((float) $totalOwed, 2),
                'dunning_level'  => (int) $maxLevel,
                'days_overdue'   => (int) $daysOverdue,
                'invoice_count'  => $overdueInvoices->count(),
            ];
        });

        if ($filterLevel !== null) {
            $customers = $customers->filter(fn ($d) => $d['dunning_level'] === $filterLevel)->values();
        }

        return response()->json($customers);
    }

    public function pause(Request $request, int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);

        $pauseUntil = now()->addDays(30);
        $old = $customer->only(['dunning_paused_until', 'status']);

        $customer->update(['dunning_paused_until' => $pauseUntil]);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'dunning_paused',
            'model_type' => Customer::class,
            'model_id'   => $customer->id,
            'old_values' => $old,
            'new_values' => ['dunning_paused_until' => $pauseUntil],
        ]);

        return response()->json([
            'message'              => 'Dunning paused for 30 days.',
            'dunning_paused_until' => $pauseUntil,
        ]);
    }

    public function escalate(Request $request, int $customerId): JsonResponse
    {
        $customer = Customer::findOrFail($customerId);

        $overdueInvoices = Invoice::with(['dunningRecords', 'items'])
            ->where('customer_id', $customerId)
            ->whereDate('due_date', '<', now())
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->get();

        if ($overdueInvoices->isEmpty()) {
            return response()->json(['message' => 'No overdue invoices found.'], 422);
        }

        $escalated = 0;

        foreach ($overdueInvoices as $invoice) {
            $currentLevel = $invoice->dunningRecords->max('level') ?? 0;

            if ($currentLevel >= DunningService::MAX_LEVEL) {
                continue;
            }

            $nextLevel = $currentLevel + 1;
            $this->dunningService->escalateInvoice($invoice, $customer, $nextLevel, DunningService::FEES[$nextLevel]);
            $escalated++;
        }

        if ($escalated === 0) {
            return response()->json(['message' => 'All overdue invoices are already at maximum dunning level.'], 422);
        }

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'dunning_escalated',
            'model_type' => Customer::class,
            'model_id'   => $customer->id,
            'old_values' => null,
            'new_values' => ['escalated_invoices' => $escalated],
        ]);

        return response()->json(['message' => 'Escalated ' . $escalated . ' invoice(s).']);
    }

    public function resolve(Request $request, int $customerId): JsonResponse
    {
        $data = $request->validate([
            'notes'     => ['required', 'string', 'max:1000'],
            'reference' => ['required', 'string', 'max:255'],
        ]);

        $customer = Customer::findOrFail($customerId);

        $count = $this->dunningService->resolveCustomer($customer, $data['reference']);

        if ($count === 0) {
            return response()->json(['message' => 'No overdue invoices to resolve.'], 422);
        }

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'dunning_resolved',
            'model_type' => Customer::class,
            'model_id'   => $customer->id,
            'old_values' => ['status' => $customer->getOriginal('status')],
            'new_values' => [
                'status'            => 'tenant',
                'resolved_invoices' => $count,
                'reference'         => $data['reference'],
                'notes'             => $data['notes'],
            ],
        ]);

        return response()->json(['message' => 'Resolved ' . $count . ' invoice(s). Customer status reset to tenant.']);
    }
}
