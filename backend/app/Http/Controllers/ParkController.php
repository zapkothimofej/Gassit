<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ParkScopeMiddleware;
use App\Models\AuditLog;
use App\Models\Park;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ParkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (in_array($user->role, ['admin', 'main_manager'], true)) {
            $parks = Park::paginate(20);
        } else {
            $parks = $user->parks()->paginate(20);
        }

        return response()->json($parks);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'address'       => ['required', 'string', 'max:255'],
            'city'          => ['required', 'string', 'max:100'],
            'zip'           => ['required', 'string', 'max:20'],
            'country'       => ['required', 'string', 'size:2'],
            'phone'         => ['nullable', 'string', 'max:50'],
            'email'         => ['nullable', 'email', 'max:255'],
            'bank_iban'     => ['nullable', 'string', 'max:50'],
            'bank_bic'      => ['nullable', 'string', 'max:20'],
            'bank_owner'    => ['nullable', 'string', 'max:255'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'language'      => ['nullable', 'string', 'size:2'],
        ]);

        $park = Park::create($data);

        $this->writeAuditLog($request, 'create', $park, null, $park->toArray());

        return response()->json($park, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $park = Park::findOrFail($id);
        $this->checkParkAccess($request, $id);

        $old = $park->toArray();

        $data = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255'],
            'address'       => ['sometimes', 'string', 'max:255'],
            'city'          => ['sometimes', 'string', 'max:100'],
            'zip'           => ['sometimes', 'string', 'max:20'],
            'country'       => ['sometimes', 'string', 'size:2'],
            'phone'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'email'         => ['sometimes', 'nullable', 'email', 'max:255'],
            'bank_iban'     => ['sometimes', 'nullable', 'string', 'max:50'],
            'bank_bic'      => ['sometimes', 'nullable', 'string', 'max:20'],
            'bank_owner'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'primary_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'language'      => ['sometimes', 'nullable', 'string', 'size:2'],
        ]);

        $park->update($data);

        $this->writeAuditLog($request, 'update', $park, $old, $park->fresh()->toArray());

        return response()->json($park->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $park = Park::findOrFail($id);
        $this->checkParkAccess($request, $id);

        $old = $park->toArray();
        $park->delete();

        $this->writeAuditLog($request, 'delete', $park, $old, null);

        return response()->json(['message' => 'Park deleted.']);
    }

    public function uploadLogo(Request $request, int $id): JsonResponse
    {
        $park = Park::findOrFail($id);
        $this->checkParkAccess($request, $id);

        $request->validate([
            'logo' => ['required', 'file', 'mimes:jpeg,png,jpg,svg', 'max:2048'],
        ]);

        $path = $request->file('logo')->store("parks/{$id}/logos", 's3');

        $old = ['logo_path' => $park->logo_path];
        $park->update(['logo_path' => $path]);
        $this->writeAuditLog($request, 'upload_logo', $park, $old, ['logo_path' => $path]);

        return response()->json(['logo_path' => $path]);
    }

    public function getSettings(Request $request, int $id): JsonResponse
    {
        $park = Park::findOrFail($id);
        $this->checkParkAccess($request, $id);

        $settings = SystemSetting::all()->pluck('value', 'key');

        return response()->json([
            'park'     => $park,
            'settings' => $settings,
        ]);
    }

    public function updateSettings(Request $request, int $id): JsonResponse
    {
        $park = Park::findOrFail($id);
        $this->checkParkAccess($request, $id);

        $data = $request->validate([
            'name'          => ['sometimes', 'string', 'max:255'],
            'phone'         => ['sometimes', 'nullable', 'string', 'max:50'],
            'email'         => ['sometimes', 'nullable', 'email', 'max:255'],
            'bank_iban'     => ['sometimes', 'nullable', 'string', 'max:50'],
            'bank_bic'      => ['sometimes', 'nullable', 'string', 'max:20'],
            'bank_owner'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'primary_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'language'      => ['sometimes', 'nullable', 'string', 'size:2'],
        ]);

        $old = $park->toArray();
        $park->update($data);
        $this->writeAuditLog($request, 'update_settings', $park, $old, $park->fresh()->toArray());

        $settings = SystemSetting::all()->pluck('value', 'key');

        return response()->json([
            'park'     => $park->fresh(),
            'settings' => $settings,
        ]);
    }

    private function checkParkAccess(Request $request, int $parkId): void
    {
        $user = $request->user();
        if (! ParkScopeMiddleware::hasAccessToPark($user, $parkId)) {
            abort(403, 'Unauthorized access to this park.');
        }
    }

    private function writeAuditLog(Request $request, string $action, Park $model, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id'    => $request->user()?->id,
            'action'     => $action,
            'model_type' => Park::class,
            'model_id'   => $model->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
