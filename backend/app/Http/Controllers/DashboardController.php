<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ParkScopeMiddleware;
use App\Models\Application;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\DamageReport;
use App\Models\DunningRecord;
use App\Models\Invoice;
use App\Models\RevenueTarget;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function kpis(Request $request)
    {
        $parkId = $request->query('park_id');
        $user   = $request->user();

        if ($parkId && ! ParkScopeMiddleware::hasAccessToPark($user, (int) $parkId)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $cacheKey = 'dashboard_kpis_' . ($parkId ?? 'all') . '_' . $user->id;

        $data = Cache::remember($cacheKey, 30, function () use ($parkId) {
            $now       = now();
            $monthStart = $now->copy()->startOfMonth();

            // new_requests: applications created this month
            $newRequests = Application::query()
                ->when($parkId, fn($q) => $q->where('park_id', $parkId))
                ->where('created_at', '>=', $monthStart)
                ->count();

            // new_customers: customers created this month
            $newCustomers = Customer::query()
                ->when($parkId, function ($q) use ($parkId) {
                    $q->whereHas('applications', fn($aq) => $aq->where('park_id', $parkId));
                })
                ->where('created_at', '>=', $monthStart)
                ->count();

            // new_invoices_count: invoices created this month
            $newInvoicesCount = Invoice::query()
                ->when($parkId, fn($q) => $q->where('park_id', $parkId))
                ->where('created_at', '>=', $monthStart)
                ->count();

            // free_units
            $freeUnits = Unit::query()
                ->when($parkId, fn($q) => $q->where('park_id', $parkId))
                ->where('status', 'free')
                ->count();

            // ongoing_contracts: active contracts
            $ongoingContracts = Contract::query()
                ->when($parkId, function ($q) use ($parkId) {
                    $q->whereHas('unit', fn($uq) => $uq->where('park_id', $parkId));
                })
                ->where('status', 'active')
                ->count();

            // cancellations: contracts terminated this month
            $cancellations = Contract::query()
                ->when($parkId, function ($q) use ($parkId) {
                    $q->whereHas('unit', fn($uq) => $uq->where('park_id', $parkId));
                })
                ->whereIn('status', ['terminated_by_customer', 'terminated_by_lfg'])
                ->where('terminated_at', '>=', $monthStart)
                ->count();

            // problem_clients
            $problemClients = Customer::query()
                ->when($parkId, function ($q) use ($parkId) {
                    $q->whereHas('applications', fn($aq) => $aq->where('park_id', $parkId));
                })
                ->whereIn('status', ['debtor', 'troublemaker', 'blacklisted'])
                ->count();

            // inactive_units
            $inactiveUnits = Unit::query()
                ->when($parkId, fn($q) => $q->where('park_id', $parkId))
                ->where('status', 'inactive')
                ->count();

            // debtors_count: customers with overdue invoices
            $debtorsCount = Customer::query()
                ->when($parkId, fn($q) => $q->where(function ($q2) use ($parkId) {
                    $q2->whereHas('applications', fn($aq) => $aq->where('park_id', $parkId));
                }))
                ->whereHas('invoices', fn($q) => $q->where('status', 'overdue'))
                ->count();

            // max_dunning_level
            $maxDunningLevel = DunningRecord::query()
                ->when($parkId, fn($q) => $q->whereHas('invoice', fn($iq) => $iq->where('park_id', $parkId)))
                ->max('level') ?? 0;

            // damages_open: damage reports not closed
            $damagesOpen = DamageReport::query()
                ->when($parkId, function ($q) use ($parkId) {
                    $q->whereHas('unit', fn($uq) => $uq->where('park_id', $parkId));
                })
                ->whereNotIn('status', ['closed'])
                ->count();

            // repair_jobs_open
            $repairJobsOpen = DamageReport::query()
                ->when($parkId, function ($q) use ($parkId) {
                    $q->whereHas('unit', fn($uq) => $uq->where('park_id', $parkId));
                })
                ->whereIn('status', ['repair_ordered', 'in_repair'])
                ->count();

            return [
                'new_requests'      => $newRequests,
                'new_customers'     => $newCustomers,
                'new_invoices_count' => $newInvoicesCount,
                'free_units'        => $freeUnits,
                'ongoing_contracts' => $ongoingContracts,
                'cancellations'     => $cancellations,
                'problem_clients'   => $problemClients,
                'inactive_units'    => $inactiveUnits,
                'debtors_count'     => $debtorsCount,
                'max_dunning_level' => (int) $maxDunningLevel,
                'damages_open'      => $damagesOpen,
                'repair_jobs_open'  => $repairJobsOpen,
            ];
        });

        return response()->json($data);
    }

    public function mahnstuffe(Request $request)
    {
        $parkId = $request->query('park_id');
        $user   = $request->user();

        if ($parkId && ! ParkScopeMiddleware::hasAccessToPark($user, (int) $parkId)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $rows = DunningRecord::with(['customer', 'invoice'])
            ->when($parkId, fn($q) => $q->whereHas('invoice', fn($iq) => $iq->where('park_id', $parkId)))
            ->get()
            ->groupBy('customer_id')
            ->map(function ($records) {
                $customer      = $records->first()->customer;
                $dunningLevel  = $records->max('level');
                $invoiceIds    = $records->pluck('invoice_id')->unique();
                $totalOwed     = Invoice::whereIn('id', $invoiceIds)
                    ->whereNotIn('status', ['paid', 'cancelled'])
                    ->sum('total_amount');
                $oldestInvoice = Invoice::whereIn('id', $invoiceIds)
                    ->whereNotIn('status', ['paid', 'cancelled'])
                    ->orderBy('due_date')
                    ->first();
                $daysOverdue   = $oldestInvoice && $oldestInvoice->due_date
                    ? max(0, (int) \Carbon\Carbon::parse($oldestInvoice->due_date)->diffInDays(now(), false))
                    : 0;

                return [
                    'customer_id'   => $customer->id,
                    'customer_name' => $customer->first_name . ' ' . $customer->last_name,
                    'total_owed'    => (float) $totalOwed,
                    'dunning_level' => (int) $dunningLevel,
                    'days_overdue'  => $daysOverdue,
                ];
            })
            ->values();

        return response()->json($rows);
    }

    public function revenue(Request $request)
    {
        $parkId = $request->query('park_id');
        $user   = $request->user();

        if ($parkId && ! ParkScopeMiddleware::hasAccessToPark($user, (int) $parkId)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $now   = now();
        $year  = (int) $now->year;
        $month = (int) $now->month;

        $targetsQuery = RevenueTarget::where('year', $year)->where('month', $month);
        if ($parkId) {
            $targetsQuery->where('park_id', $parkId);
        }
        $targets = $targetsQuery->get()->keyBy('park_id');

        $actualQuery = Invoice::query()
            ->selectRaw('park_id, SUM(total_amount) as actual_revenue')
            ->where('status', 'paid')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->when($parkId, fn($q) => $q->where('park_id', $parkId))
            ->groupBy('park_id')
            ->get()
            ->keyBy('park_id');

        $parkIds = $targets->keys()->merge($actualQuery->keys())->unique();

        $result = $parkIds->map(function ($pid) use ($targets, $actualQuery) {
            $planned = $targets->has($pid) ? (float) $targets[$pid]->target_amount : 0.0;
            $actual  = $actualQuery->has($pid) ? (float) $actualQuery[$pid]->actual_revenue : 0.0;

            return [
                'park_id' => $pid,
                'planned' => $planned,
                'actual'  => $actual,
            ];
        })->values();

        return response()->json($result);
    }
}
