<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\MailTemplate;
use App\Models\SentEmail;
use App\Models\Unit;
use App\Models\WaitingList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ApplicationController extends Controller
{
    private const STATUS_TRANSITIONS = [
        'new'         => ['in_progress'],
        'in_progress' => ['waiting', 'completed', 'declined'],
        'waiting'     => ['completed', 'declined'],
        'completed'   => [],
        'declined'    => [],
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Application::with(['customer', 'park', 'unitType', 'unit', 'assignedTo']);

        if ($request->filled('park_id')) {
            $query->where('park_id', (int) $request->query('park_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', (int) $request->query('assigned_to'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return response()->json($query->paginate(20));
    }

    public function show(int $id): JsonResponse
    {
        $application = Application::with(['customer', 'park', 'unitType', 'unit', 'assignedTo'])->findOrFail($id);
        return response()->json($application);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'park_id'            => ['required', 'exists:parks,id'],
            'customer_id'        => ['required', 'exists:customers,id'],
            'unit_type_id'       => ['required', 'exists:unit_types,id'],
            'unit_id'            => ['nullable', 'exists:units,id'],
            'desired_start_date' => ['nullable', 'date'],
            'notes'              => ['nullable', 'string'],
            'source'             => ['sometimes', 'in:walk_in,phone,online,referral'],
        ]);

        $application = Application::create($data);
        $application->refresh();

        $this->writeAuditLog($request, 'create', $application, null, $application->toArray());

        // Auto-send acknowledgment email (create SentEmail record)
        $template = MailTemplate::where('template_type', 'welcome')
            ->where(function ($q) use ($data) {
                $q->where('park_id', $data['park_id'])->orWhereNull('park_id');
            })
            ->where('active', true)
            ->first();

        SentEmail::create([
            'customer_id'     => $application->customer_id,
            'template_id'     => $template?->id,
            'sent_by'         => $request->user()->id,
            'subject'         => $template?->subject ?? 'Application Acknowledgment',
            'body_html'       => $template?->body_html ?? '<p>Thank you for your application.</p>',
            'recipient_email' => $application->customer->email ?? '',
            'status'          => 'queued',
        ]);

        return response()->json($application->load(['customer', 'park', 'unitType']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $application = Application::findOrFail($id);
        $old = $application->toArray();

        $data = $request->validate([
            'park_id'            => ['sometimes', 'exists:parks,id'],
            'customer_id'        => ['sometimes', 'exists:customers,id'],
            'unit_type_id'       => ['sometimes', 'exists:unit_types,id'],
            'unit_id'            => ['sometimes', 'nullable', 'exists:units,id'],
            'desired_start_date' => ['sometimes', 'nullable', 'date'],
            'notes'              => ['sometimes', 'nullable', 'string'],
            'source'             => ['sometimes', 'in:walk_in,phone,online,referral'],
        ]);

        $application->update($data);

        $this->writeAuditLog($request, 'update', $application, $old, $application->fresh()->toArray());

        return response()->json($application->fresh()->load(['customer', 'park', 'unitType']));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $application = Application::findOrFail($id);
        $old = $application->toArray();
        $application->delete();

        $this->writeAuditLog($request, 'delete', $application, $old, null);

        return response()->json(['message' => 'Application deleted.']);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $application = Application::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'in:new,in_progress,waiting,completed,declined'],
        ]);

        $newStatus = $data['status'];
        $allowed = self::STATUS_TRANSITIONS[$application->status] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            return response()->json([
                'message' => "Cannot transition from '{$application->status}' to '{$newStatus}'.",
            ], 422);
        }

        $old = $application->toArray();
        $application->update(['status' => $newStatus]);

        $this->writeAuditLog($request, 'status_change', $application, $old, ['status' => $newStatus]);

        return response()->json($application->fresh());
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $application = Application::findOrFail($id);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $old = $application->toArray();
        $application->update(['assigned_to' => $data['user_id']]);

        $this->writeAuditLog($request, 'assign', $application, $old, ['assigned_to' => $data['user_id']]);

        return response()->json($application->fresh());
    }

    public function creditCheck(Request $request, int $id): JsonResponse
    {
        $application = Application::findOrFail($id);

        // Credit bureau stub — generate fake result
        $fakePath = "applications/{$id}/credit-check-" . now()->format('Ymd_His') . '.pdf';
        Storage::disk('s3')->put($fakePath, '%PDF-1.4 stub credit check report');

        $application->update(['credit_check_path' => $fakePath]);

        $this->writeAuditLog($request, 'credit_check', $application, null, ['credit_check_path' => $fakePath]);

        return response()->json([
            'status'             => 'completed',
            'score'              => 750,
            'risk_level'         => 'low',
            'credit_check_path'  => $fakePath,
            'checked_at'         => now()->toIso8601String(),
        ]);
    }

    public function moveToWaitingList(Request $request, int $id): JsonResponse
    {
        $application = Application::findOrFail($id);

        if (!in_array($application->status, ['new', 'in_progress'], true)) {
            return response()->json(['message' => 'Application cannot be moved to waiting list from current status.'], 422);
        }

        $data = $request->validate([
            'notes'          => ['nullable', 'string'],
            'priority_score' => ['nullable', 'integer'],
        ]);

        $old = $application->toArray();
        $application->update(['status' => 'waiting']);

        $waitingList = WaitingList::create([
            'park_id'                   => $application->park_id,
            'customer_id'               => $application->customer_id,
            'unit_type_id'              => $application->unit_type_id,
            'priority_score'            => $data['priority_score'] ?? 0,
            'notes'                     => $data['notes'] ?? null,
            'converted_application_id'  => $application->id,
        ]);

        $this->writeAuditLog($request, 'waiting_list', $application, $old, ['waiting_list_id' => $waitingList->id]);

        return response()->json([
            'application'  => $application->fresh(),
            'waiting_list' => $waitingList,
        ], 201);
    }

    public function convert(Request $request, int $id): JsonResponse
    {
        $application = Application::findOrFail($id);

        $data = $request->validate([
            'unit_id'             => ['required', 'exists:units,id'],
            'start_date'          => ['required', 'date'],
            'rent_amount'         => ['required', 'numeric', 'min:0'],
            'deposit_amount'      => ['required', 'numeric', 'min:0'],
            'insurance_amount'    => ['nullable', 'numeric', 'min:0'],
            'notice_period_days'  => ['sometimes', 'integer', 'min:1'],
        ]);

        $unit = Unit::findOrFail($data['unit_id']);

        if (!in_array($unit->status, ['free', 'reserved'], true)) {
            return response()->json(['message' => 'Unit is not available for rental.'], 422);
        }

        $old = $application->toArray();

        // Assign unit and mark application completed
        $application->update([
            'unit_id' => $data['unit_id'],
            'status'  => 'completed',
        ]);

        // Reserve unit
        $unit->update(['status' => 'reserved']);

        // Create draft contract
        $contract = Contract::create([
            'application_id'     => $application->id,
            'customer_id'        => $application->customer_id,
            'unit_id'            => $data['unit_id'],
            'start_date'         => $data['start_date'],
            'rent_amount'        => $data['rent_amount'],
            'deposit_amount'     => $data['deposit_amount'],
            'insurance_amount'   => $data['insurance_amount'] ?? 0,
            'notice_period_days' => $data['notice_period_days'] ?? 30,
            'status'             => 'draft',
        ]);

        $this->writeAuditLog($request, 'convert', $application, $old, [
            'status'      => 'completed',
            'unit_id'     => $data['unit_id'],
            'contract_id' => $contract->id,
        ]);

        return response()->json([
            'application' => $application->fresh(),
            'contract'    => $contract,
        ], 201);
    }

    private function writeAuditLog(Request $request, string $action, Application $model, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id'    => $request->user()?->id,
            'action'     => $action,
            'model_type' => Application::class,
            'model_id'   => $model->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
