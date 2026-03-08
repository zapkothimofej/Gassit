<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ParkScopeMiddleware;
use App\Models\AuditLog;
use App\Models\InsuranceOption;
use App\Models\Park;
use App\Models\UnitType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsuranceOptionController extends Controller
{
    public function index(Request $request, int $parkId): JsonResponse
    {
        Park::findOrFail($parkId);
        $this->checkParkAccess($request, $parkId);

        $options = InsuranceOption::where('park_id', $parkId)->get();

        return response()->json($options);
    }

    public function store(Request $request, int $parkId): JsonResponse
    {
        Park::findOrFail($parkId);
        $this->checkParkAccess($request, $parkId);

        $data = $request->validate([
            'unit_type_id'    => ['nullable', 'integer', 'exists:unit_types,id'],
            'name'            => ['required', 'string', 'max:255'],
            'provider'        => ['required', 'string', 'max:255'],
            'monthly_premium' => ['required', 'numeric', 'min:0'],
            'coverage_amount' => ['required', 'numeric', 'min:0'],
            'active'          => ['sometimes', 'boolean'],
        ]);

        $data['park_id'] = $parkId;
        $option = InsuranceOption::create($data);

        $this->writeAuditLog($request, 'create', $option, null, $option->toArray());

        return response()->json($option, 201);
    }

    public function update(Request $request, int $parkId, int $id): JsonResponse
    {
        $option = InsuranceOption::where('park_id', $parkId)->findOrFail($id);
        $this->checkParkAccess($request, $parkId);

        $old = $option->toArray();

        $data = $request->validate([
            'unit_type_id'    => ['nullable', 'integer', 'exists:unit_types,id'],
            'name'            => ['sometimes', 'string', 'max:255'],
            'provider'        => ['sometimes', 'string', 'max:255'],
            'monthly_premium' => ['sometimes', 'numeric', 'min:0'],
            'coverage_amount' => ['sometimes', 'numeric', 'min:0'],
            'active'          => ['sometimes', 'boolean'],
        ]);

        $option->update($data);

        $this->writeAuditLog($request, 'update', $option, $old, $option->fresh()->toArray());

        return response()->json($option->fresh());
    }

    public function destroy(Request $request, int $parkId, int $id): JsonResponse
    {
        $option = InsuranceOption::where('park_id', $parkId)->findOrFail($id);
        $this->checkParkAccess($request, $parkId);

        $old = $option->toArray();
        $option->delete();

        $this->writeAuditLog($request, 'delete', $option, $old, null);

        return response()->json(['message' => 'Insurance option deleted.']);
    }

    public function forUnitType(Request $request, int $unitTypeId): JsonResponse
    {
        $unitType = UnitType::findOrFail($unitTypeId);
        $this->checkParkAccess($request, $unitType->park_id);

        $options = InsuranceOption::where(function ($q) use ($unitType) {
            $q->where('park_id', $unitType->park_id)
              ->where(function ($q2) use ($unitType) {
                  $q2->whereNull('unit_type_id')
                     ->orWhere('unit_type_id', $unitType->id);
              });
        })->where('active', true)->get();

        return response()->json($options);
    }

    private function checkParkAccess(Request $request, int $parkId): void
    {
        if (! ParkScopeMiddleware::hasAccessToPark($request->user(), $parkId)) {
            abort(403, 'Unauthorized access to this park.');
        }
    }

    private function writeAuditLog(Request $request, string $action, InsuranceOption $model, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id'    => $request->user()?->id,
            'action'     => $action,
            'model_type' => InsuranceOption::class,
            'model_id'   => $model->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
