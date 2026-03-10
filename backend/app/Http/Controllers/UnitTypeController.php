<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ParkScopeMiddleware;
use App\Models\AuditLog;
use App\Models\Park;
use App\Models\Unit;
use App\Models\UnitFeature;
use App\Models\UnitType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UnitTypeController extends Controller
{
    public function index(Request $request, int $parkId): JsonResponse
    {
        $park = Park::findOrFail($parkId);
        $this->checkParkAccess($request, $parkId);

        $unitTypes = $park->unitTypes()->with('features')->paginate(20);

        return response()->json($unitTypes);
    }

    public function store(Request $request, int $parkId): JsonResponse
    {
        Park::findOrFail($parkId);
        $this->checkParkAccess($request, $parkId);

        $data = $request->validate([
            'name'           => ['required', 'string', 'max:255'],
            'description'    => ['nullable', 'string'],
            'base_rent'      => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['required', 'numeric', 'min:0'],
            'size_m2'        => ['required', 'numeric', 'min:0'],
        ]);

        $data['park_id'] = $parkId;
        $unitType = UnitType::create($data);

        $this->writeAuditLog($request, 'create', $unitType, null, $unitType->toArray());

        return response()->json($unitType->load('features'), 201);
    }

    public function update(Request $request, int $parkId, int $id): JsonResponse
    {
        $unitType = UnitType::where('park_id', $parkId)->findOrFail($id);
        $this->checkParkAccess($request, $parkId);

        $old = $unitType->toArray();

        $data = $request->validate([
            'name'           => ['sometimes', 'string', 'max:255'],
            'description'    => ['sometimes', 'nullable', 'string'],
            'base_rent'      => ['sometimes', 'numeric', 'min:0'],
            'deposit_amount' => ['sometimes', 'numeric', 'min:0'],
            'size_m2'        => ['sometimes', 'numeric', 'min:0'],
        ]);

        $unitType->update($data);

        $this->writeAuditLog($request, 'update', $unitType, $old, $unitType->fresh()->toArray());

        return response()->json($unitType->fresh()->load('features'));
    }

    public function destroy(Request $request, int $parkId, int $id): JsonResponse
    {
        $unitType = UnitType::where('park_id', $parkId)->findOrFail($id);
        $this->checkParkAccess($request, $parkId);

        $old = $unitType->toArray();
        $unitType->delete();

        $this->writeAuditLog($request, 'delete', $unitType, $old, null);

        return response()->json(['message' => 'Unit type deleted.']);
    }

    public function uploadFloorPlan(Request $request, int $id): JsonResponse
    {
        $unitType = UnitType::findOrFail($id);
        $this->checkParkAccess($request, $unitType->park_id);

        $request->validate([
            'floor_plan' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $path = $request->file('floor_plan')->store("unit-types/{$id}/floor-plans", 's3');

        $old = ['floor_plan_path' => $unitType->floor_plan_path];
        $unitType->update(['floor_plan_path' => $path]);
        $this->writeAuditLog($request, 'upload_floor_plan', $unitType, $old, ['floor_plan_path' => $path]);

        return response()->json(['floor_plan_path' => $path]);
    }

    public function syncFeatures(Request $request, int $id): JsonResponse
    {
        $unitType = UnitType::findOrFail($id);
        $this->checkParkAccess($request, $unitType->park_id);

        $data = $request->validate([
            'features'   => ['required', 'array'],
            'features.*' => ['required', 'string', 'max:255'],
        ]);

        $unitType->features()->delete();

        $features = array_map(
            fn(string $f) => ['unit_type_id' => $unitType->id, 'feature' => $f, 'created_at' => now(), 'updated_at' => now()],
            $data['features']
        );

        UnitFeature::insert($features);

        $this->writeAuditLog($request, 'sync_features', $unitType, null, ['features' => $data['features']]);

        return response()->json(['features' => $unitType->fresh()->features]);
    }

    public function availability(Request $request, int $id): JsonResponse
    {
        $unitType = UnitType::findOrFail($id);
        $this->checkParkAccess($request, $unitType->park_id);

        $freeCount = $unitType->units()->where('status', 'free')->count();

        return response()->json([
            'unit_type_id' => $unitType->id,
            'free_units'   => $freeCount,
        ]);
    }

    private function checkParkAccess(Request $request, int $parkId): void
    {
        $user = $request->user();
        if (! ParkScopeMiddleware::hasAccessToPark($user, $parkId)) {
            abort(403, 'Unauthorized access to this park.');
        }
    }

    private function writeAuditLog(Request $request, string $action, UnitType $model, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id'    => $request->user()?->id,
            'action'     => $action,
            'model_type' => UnitType::class,
            'model_id'   => $model->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
