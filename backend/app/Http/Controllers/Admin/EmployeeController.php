<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index(): JsonResponse
    {
        $employees = Employee::with(['user:id,name,email', 'park:id,name'])->paginate(20);

        return response()->json($employees);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'    => ['nullable', 'integer', 'exists:users,id'],
            'park_id'    => ['nullable', 'integer', 'exists:parks,id'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['required', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:50'],
            'role_title' => ['required', 'string', 'max:255'],
            'hire_date'  => ['required', 'date'],
            'active'     => ['boolean'],
        ]);

        $employee = Employee::create($data);

        $this->writeAuditLog($request, 'create', $employee, null, $employee->toArray());

        return response()->json($employee->load(['user:id,name,email', 'park:id,name']), 201);
    }

    public function show(int $id): JsonResponse
    {
        $employee = Employee::with(['user:id,name,email', 'park:id,name'])->findOrFail($id);

        return response()->json($employee);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $old = $employee->toArray();

        $data = $request->validate([
            'user_id'    => ['nullable', 'integer', 'exists:users,id'],
            'park_id'    => ['nullable', 'integer', 'exists:parks,id'],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name'  => ['sometimes', 'string', 'max:255'],
            'email'      => ['sometimes', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:50'],
            'role_title' => ['sometimes', 'string', 'max:255'],
            'hire_date'  => ['sometimes', 'date'],
            'active'     => ['sometimes', 'boolean'],
        ]);

        $employee->update($data);

        $this->writeAuditLog($request, 'update', $employee, $old, $employee->fresh()->toArray());

        return response()->json($employee->fresh()->load(['user:id,name,email', 'park:id,name']));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $employee = Employee::findOrFail($id);
        $old = $employee->toArray();

        $employee->delete();

        $this->writeAuditLog($request, 'delete', $employee, $old, null);

        return response()->json(['message' => 'Employee deleted.']);
    }

    private function writeAuditLog(Request $request, string $action, Employee $model, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id'    => $request->user()?->id,
            'action'     => $action,
            'model_type' => Employee::class,
            'model_id'   => $model->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
