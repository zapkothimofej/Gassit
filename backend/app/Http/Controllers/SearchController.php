<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ParkScopeMiddleware;
use App\Models\Application;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => 'required|string|min:1']);

        $q = $request->string('q');
        $parkId = $request->query('park_id');
        $user = $request->user();

        if ($parkId && !ParkScopeMiddleware::hasAccessToPark($user, (int) $parkId)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        try {
            return $this->scoutSearch((string) $q, $parkId ? (int) $parkId : null);
        } catch (Throwable) {
            return $this->likeSearch((string) $q, $parkId ? (int) $parkId : null);
        }
    }

    private function scoutSearch(string $q, ?int $parkId): JsonResponse
    {
        $customers = Customer::search($q);
        $units = Unit::search($q);
        $applications = Application::search($q);
        $contracts = Contract::search($q);
        $invoices = Invoice::search($q);

        if ($parkId) {
            $customers->where('park_id', $parkId);
            $units->where('park_id', $parkId);
            $applications->where('park_id', $parkId);
            $contracts->where('park_id', $parkId);
            $invoices->where('park_id', $parkId);
        }

        return response()->json([
            'customers' => $customers->take(5)->get(['id', 'first_name', 'last_name', 'email', 'company_name', 'type']),
            'units' => $units->take(5)->get(['id', 'unit_number', 'park_id', 'status']),
            'applications' => $applications->take(5)->get()->load('customer:id,first_name,last_name'),
            'contracts' => $contracts->take(5)->get(['id', 'customer_id', 'unit_id', 'status']),
            'invoices' => $invoices->take(5)->get(['id', 'invoice_number', 'customer_id', 'park_id', 'status', 'total_amount']),
        ]);
    }

    private function likeSearch(string $q, ?int $parkId): JsonResponse
    {
        $customers = Customer::where(function ($query) use ($q) {
            $query->where('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('company_name', 'like', "%{$q}%");
        });

        $units = Unit::where('unit_number', 'like', "%{$q}%");

        $applications = Application::where(function ($query) use ($q) {
            $query->whereHas('customer', function ($cq) use ($q) {
                $cq->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%");
            });
        });

        $contracts = Contract::where('id', 'like', "%{$q}%");

        $invoices = Invoice::where('invoice_number', 'like', "%{$q}%");

        if ($parkId) {
            $customers->whereHas('applications', fn ($q2) => $q2->where('park_id', $parkId));
            $units->where('park_id', $parkId);
            $applications->where('park_id', $parkId);
            $contracts->whereHas('unit', fn ($q2) => $q2->where('park_id', $parkId));
            $invoices->where('park_id', $parkId);
        }

        return response()->json([
            'customers' => $customers->limit(5)->get(['id', 'first_name', 'last_name', 'email', 'company_name', 'type']),
            'units' => $units->limit(5)->get(['id', 'unit_number', 'park_id', 'status']),
            'applications' => $applications->with('customer:id,first_name,last_name')->limit(5)->get(['id', 'park_id', 'customer_id', 'status']),
            'contracts' => $contracts->limit(5)->get(['id', 'customer_id', 'unit_id', 'status']),
            'invoices' => $invoices->limit(5)->get(['id', 'invoice_number', 'customer_id', 'park_id', 'status', 'total_amount']),
        ]);
    }
}
