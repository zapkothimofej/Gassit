<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('active')) {
            $query->where('active', filter_var($request->active, FILTER_VALIDATE_BOOLEAN));
        }

        $users = $query->with('parks:id,name')->paginate(20);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role'  => ['required', Rule::in(['admin', 'main_manager', 'rental_manager', 'park_worker', 'accountant', 'office_worker', 'customer_service'])],
        ]);

        $tempPassword = Str::random(12);
        $data['password'] = Hash::make($tempPassword);
        $data['active'] = true;

        $user = User::create($data);

        $this->writeAuditLog($request, 'create', $user, null, $user->toArray());

        // In a real setup, dispatch a welcome email job here with $tempPassword
        // Mail::to($user->email)->send(new WelcomeUserMail($user, $tempPassword));

        return response()->json($user->load('parks:id,name'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $old = $user->toArray();

        $data = $request->validate([
            'name'   => ['sometimes', 'string', 'max:255'],
            'email'  => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($id)],
            'role'   => ['sometimes', Rule::in(['admin', 'main_manager', 'rental_manager', 'park_worker', 'accountant', 'office_worker', 'customer_service'])],
            'active' => ['sometimes', 'boolean'],
        ]);

        $user->update($data);

        $this->writeAuditLog($request, 'update', $user, $old, $user->fresh()->toArray());

        return response()->json($user->fresh()->load('parks:id,name'));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $old = $user->toArray();

        $user->update(['active' => false]);
        $user->tokens()->delete();

        $this->writeAuditLog($request, 'deactivate', $user, $old, $user->fresh()->toArray());

        return response()->json(['message' => 'User deactivated.']);
    }

    public function syncParks(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        $old = $user->parks()->pluck('parks.id')->toArray();

        $data = $request->validate([
            'park_ids'   => ['required', 'array'],
            'park_ids.*' => ['integer', 'exists:parks,id'],
        ]);

        $user->parks()->sync($data['park_ids']);

        $this->writeAuditLog($request, 'sync_parks', $user, ['park_ids' => $old], ['park_ids' => $data['park_ids']]);

        return response()->json(['message' => 'Parks synced.', 'park_ids' => $data['park_ids']]);
    }

    private function writeAuditLog(Request $request, string $action, User $model, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id'    => $request->user()?->id,
            'action'     => $action,
            'model_type' => User::class,
            'model_id'   => $model->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
