<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ParkScopeMiddleware;
use App\Models\AuditLog;
use App\Models\DiscountRule;
use App\Models\Park;
use App\Models\UnitType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiscountRuleController extends Controller
{
    public function index(Request $request, int $parkId): JsonResponse
    {
        Park::findOrFail($parkId);
        $this->checkParkAccess($request, $parkId);

        $rules = DiscountRule::where('park_id', $parkId)->get();

        return response()->json($rules);
    }

    public function store(Request $request, int $parkId): JsonResponse
    {
        Park::findOrFail($parkId);
        $this->checkParkAccess($request, $parkId);

        $data = $request->validate([
            'unit_type_id'      => ['nullable', 'integer', 'exists:unit_types,id'],
            'name'              => ['required', 'string', 'max:255'],
            'discount_type'     => ['required', 'in:percentage,fixed'],
            'discount_value'    => ['required', 'numeric', 'min:0'],
            'applies_from_month'=> ['required', 'integer', 'min:1'],
            'applies_to_month'  => ['nullable', 'integer', 'min:1'],
            'active'            => ['sometimes', 'boolean'],
        ]);

        $data['park_id'] = $parkId;
        $rule = DiscountRule::create($data);

        $this->writeAuditLog($request, 'create', $rule, null, $rule->toArray());

        return response()->json($rule, 201);
    }

    public function update(Request $request, int $parkId, int $id): JsonResponse
    {
        $rule = DiscountRule::where('park_id', $parkId)->findOrFail($id);
        $this->checkParkAccess($request, $parkId);

        $old = $rule->toArray();

        $data = $request->validate([
            'unit_type_id'      => ['nullable', 'integer', 'exists:unit_types,id'],
            'name'              => ['sometimes', 'string', 'max:255'],
            'discount_type'     => ['sometimes', 'in:percentage,fixed'],
            'discount_value'    => ['sometimes', 'numeric', 'min:0'],
            'applies_from_month'=> ['sometimes', 'integer', 'min:1'],
            'applies_to_month'  => ['nullable', 'integer', 'min:1'],
            'active'            => ['sometimes', 'boolean'],
        ]);

        $rule->update($data);

        $this->writeAuditLog($request, 'update', $rule, $old, $rule->fresh()->toArray());

        return response()->json($rule->fresh());
    }

    public function destroy(Request $request, int $parkId, int $id): JsonResponse
    {
        $rule = DiscountRule::where('park_id', $parkId)->findOrFail($id);
        $this->checkParkAccess($request, $parkId);

        $old = $rule->toArray();
        $rule->delete();

        $this->writeAuditLog($request, 'delete', $rule, $old, null);

        return response()->json(['message' => 'Discount rule deleted.']);
    }

    public function forUnitType(Request $request, int $unitTypeId): JsonResponse
    {
        $unitType = UnitType::findOrFail($unitTypeId);
        $this->checkParkAccess($request, $unitType->park_id);

        $rules = DiscountRule::where(function ($q) use ($unitType) {
            $q->where('park_id', $unitType->park_id)
              ->where(function ($q2) use ($unitType) {
                  $q2->whereNull('unit_type_id')
                     ->orWhere('unit_type_id', $unitType->id);
              });
        })->where('active', true)->get();

        return response()->json($rules);
    }

    private function checkParkAccess(Request $request, int $parkId): void
    {
        if (! ParkScopeMiddleware::hasAccessToPark($request->user(), $parkId)) {
            abort(403, 'Unauthorized access to this park.');
        }
    }

    private function writeAuditLog(Request $request, string $action, DiscountRule $model, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id'    => $request->user()?->id,
            'action'     => $action,
            'model_type' => DiscountRule::class,
            'model_id'   => $model->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
