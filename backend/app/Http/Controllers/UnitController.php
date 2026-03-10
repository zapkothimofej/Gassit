<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ParkScopeMiddleware;
use App\Jobs\NotifyWaitingListEntries;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Park;
use App\Models\Unit;
use App\Models\UnitPhoto;
use App\Traits\GeneratesSignedUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UnitController extends Controller
{
    use GeneratesSignedUrl;

    private string $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.default', 'local');
    }

    private const VALID_STATUSES = ['free', 'reserved', 'rented', 'maintenance', 'inactive'];

    private const TRANSITIONS = [
        'free'        => ['reserved', 'maintenance', 'inactive'],
        'reserved'    => ['rented', 'free', 'maintenance', 'inactive'],
        'rented'      => ['free', 'maintenance'],
        'maintenance' => ['free', 'inactive'],
        'inactive'    => ['free', 'maintenance'],
    ];

    public function index(Request $request, int $parkId): JsonResponse
    {
        Park::findOrFail($parkId);
        $this->checkParkAccess($request, $parkId);

        $query = Unit::with('unitType')
            ->where('park_id', $parkId);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('unit_type_id')) {
            $query->where('unit_type_id', $request->query('unit_type_id'));
        }

        return response()->json($query->paginate(20));
    }

    public function store(Request $request, int $parkId): JsonResponse
    {
        Park::findOrFail($parkId);
        $this->checkParkAccess($request, $parkId);

        $data = $request->validate([
            'unit_type_id'     => ['required', 'integer', 'exists:unit_types,id'],
            'unit_number'      => ['required', 'string', 'max:50'],
            'floor'            => ['nullable', 'integer'],
            'building'         => ['nullable', 'string', 'max:100'],
            'size_m2'          => ['nullable', 'numeric', 'min:0'],
            'rent_override'    => ['nullable', 'numeric', 'min:0'],
            'deposit_override' => ['nullable', 'numeric', 'min:0'],
            'notes'            => ['nullable', 'string'],
        ]);

        $data['park_id'] = $parkId;
        $data['status']  = 'free';

        $unit = Unit::create($data);

        $this->writeAuditLog($request, 'create', $unit, null, $unit->toArray());

        return response()->json($unit->load('unitType'), 201);
    }

    public function show(int $id): JsonResponse
    {
        $unit = Unit::with(['park', 'unitType', 'photos'])->findOrFail($id);
        $data = $unit->toArray();
        $data['photos'] = $unit->photos->map(function ($photo) {
            return array_merge($photo->toArray(), [
                'url' => $this->signedUrl($this->disk, $photo->path),
            ]);
        })->toArray();
        return response()->json($data);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $unit = Unit::findOrFail($id);
        $this->checkParkAccess($request, $unit->park_id);

        $old = $unit->toArray();

        $data = $request->validate([
            'unit_number'      => ['sometimes', 'string', 'max:50'],
            'floor'            => ['sometimes', 'nullable', 'integer'],
            'building'         => ['sometimes', 'nullable', 'string', 'max:100'],
            'size_m2'          => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'rent_override'    => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'deposit_override' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'notes'            => ['sometimes', 'nullable', 'string'],
        ]);

        $unit->update($data);

        $this->writeAuditLog($request, 'update', $unit, $old, $unit->fresh()->toArray());

        return response()->json($unit->fresh()->load('unitType'));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $unit = Unit::findOrFail($id);
        $this->checkParkAccess($request, $unit->park_id);

        $old = $unit->toArray();
        $unit->delete();

        $this->writeAuditLog($request, 'delete', $unit, $old, null);

        return response()->json(['message' => 'Unit deleted.']);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $unit = Unit::findOrFail($id);
        $this->checkParkAccess($request, $unit->park_id);

        $data = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', self::VALID_STATUSES)],
        ]);

        $newStatus  = $data['status'];
        $oldStatus  = $unit->status;

        $allowed = self::TRANSITIONS[$oldStatus] ?? [];
        if (! in_array($newStatus, $allowed, true)) {
            return response()->json([
                'message' => "Transition from '{$oldStatus}' to '{$newStatus}' is not allowed.",
            ], 422);
        }

        // reserved → rented: requires an active contract
        if ($oldStatus === 'reserved' && $newStatus === 'rented') {
            $hasContract = Contract::where('unit_id', $unit->id)
                ->whereIn('status', ['active', 'signed'])
                ->exists();

            if (! $hasContract) {
                return response()->json([
                    'message' => "Cannot set status to 'rented' without an active contract.",
                ], 422);
            }
        }

        // rented → free: requires a terminated contract
        if ($oldStatus === 'rented' && $newStatus === 'free') {
            $hasTerminated = Contract::where('unit_id', $unit->id)
                ->whereIn('status', ['terminated_by_customer', 'terminated_by_lfg', 'expired'])
                ->exists();

            if (! $hasTerminated) {
                return response()->json([
                    'message' => "Cannot set status to 'free' without a terminated contract.",
                ], 422);
            }
        }

        $old = ['status' => $oldStatus];
        $unit->update(['status' => $newStatus]);

        $this->writeAuditLog($request, 'status_change', $unit, $old, ['status' => $newStatus]);

        if ($newStatus === 'free') {
            NotifyWaitingListEntries::dispatch($unit->id);
        }

        return response()->json($unit->fresh());
    }

    public function uploadPhoto(Request $request, int $id): JsonResponse
    {
        $unit = Unit::findOrFail($id);
        $this->checkParkAccess($request, $unit->park_id);

        $request->validate([
            'photo'      => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'caption'    => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $path = $request->file('photo')->store("units/{$id}/photos", $this->disk);

        $photo = UnitPhoto::create([
            'unit_id'    => $unit->id,
            'path'       => $path,
            'caption'    => $request->input('caption'),
            'sort_order' => $request->input('sort_order', 0),
        ]);

        return response()->json($photo, 201);
    }

    public function deletePhoto(Request $request, int $id, int $photoId): JsonResponse
    {
        $unit  = Unit::findOrFail($id);
        $this->checkParkAccess($request, $unit->park_id);

        $photo = UnitPhoto::where('unit_id', $unit->id)->findOrFail($photoId);

        Storage::disk($this->disk)->delete($photo->path);
        $photo->delete();

        return response()->json(['message' => 'Photo deleted.']);
    }

    public function history(Request $request, int $id): JsonResponse
    {
        $unit = Unit::findOrFail($id);
        $this->checkParkAccess($request, $unit->park_id);

        $contracts = Contract::with('customer')
            ->where('unit_id', $unit->id)
            ->orderByDesc('start_date')
            ->get()
            ->map(fn(Contract $c) => [
                'id'            => $c->id,
                'customer_name' => $c->customer
                    ? ($c->customer->company_name ?: trim($c->customer->first_name . ' ' . $c->customer->last_name))
                    : null,
                'start_date'    => $c->start_date,
                'end_date'      => $c->end_date,
                'rent_amount'   => $c->rent_amount,
                'status'        => $c->status,
            ]);

        return response()->json($contracts);
    }

    private function checkParkAccess(Request $request, int $parkId): void
    {
        $user = $request->user();
        if (! ParkScopeMiddleware::hasAccessToPark($user, $parkId)) {
            abort(403, 'Unauthorized access to this park.');
        }
    }

    private function writeAuditLog(Request $request, string $action, Unit $model, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id'    => $request->user()?->id,
            'action'     => $action,
            'model_type' => Unit::class,
            'model_id'   => $model->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
