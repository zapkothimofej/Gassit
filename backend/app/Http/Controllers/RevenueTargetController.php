<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ParkScopeMiddleware;
use App\Models\Invoice;
use App\Models\Park;
use App\Models\RevenueTarget;
use Illuminate\Http\Request;

class RevenueTargetController extends Controller
{
    public function index(Request $request, int $parkId)
    {
        if (! ParkScopeMiddleware::hasAccessToPark($request->user(), $parkId)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $year = $request->query('year', now()->year);

        $targets = RevenueTarget::where('park_id', $parkId)
            ->where('year', $year)
            ->orderBy('month')
            ->get();

        return response()->json($targets);
    }

    public function store(Request $request, int $parkId)
    {
        if (! ParkScopeMiddleware::hasAccessToPark($request->user(), $parkId)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        Park::findOrFail($parkId);

        $data = $request->validate([
            'year'          => 'required|integer|min:2000|max:2100',
            'month'         => 'required|integer|min:1|max:12',
            'target_amount' => 'required|numeric|min:0',
        ]);

        $target = RevenueTarget::updateOrCreate(
            ['park_id' => $parkId, 'year' => $data['year'], 'month' => $data['month']],
            ['target_amount' => $data['target_amount']]
        );

        return response()->json($target, 201);
    }

    public function update(Request $request, int $id)
    {
        $target = RevenueTarget::findOrFail($id);

        if (! ParkScopeMiddleware::hasAccessToPark($request->user(), $target->park_id)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'target_amount' => 'required|numeric|min:0',
        ]);

        $target->update($data);

        return response()->json($target);
    }

    public function actual(Request $request, int $parkId, int $year, int $month)
    {
        if (! ParkScopeMiddleware::hasAccessToPark($request->user(), $parkId)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $actual = Invoice::where('park_id', $parkId)
            ->where('status', 'paid')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->sum('total_amount');

        return response()->json([
            'park_id' => $parkId,
            'year'    => $year,
            'month'   => $month,
            'actual'  => (float) $actual,
        ]);
    }
}
