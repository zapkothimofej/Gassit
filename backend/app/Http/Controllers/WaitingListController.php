<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyWaitingListEntries;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\MailTemplate;
use App\Models\SentEmail;
use App\Models\Unit;
use App\Models\WaitingList;
use App\Models\WaitingListNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WaitingListController extends Controller
{
    public function index(int $parkId): JsonResponse
    {
        $entries = WaitingList::with(['customer', 'unitType'])
            ->where('park_id', $parkId)
            ->orderByDesc('priority_score')
            ->orderBy('created_at')
            ->paginate(20);

        return response()->json($entries);
    }

    public function globalIndex(Request $request): JsonResponse
    {
        $query = WaitingList::with(['customer', 'unitType', 'park']);

        if ($request->filled('park_id')) {
            $query->where('park_id', $request->query('park_id'));
        }

        if ($request->filled('unit_type_id')) {
            $query->where('unit_type_id', $request->query('unit_type_id'));
        }

        return response()->json($query->orderByDesc('priority_score')->orderBy('created_at')->paginate(20));
    }

    public function store(Request $request, int $parkId): JsonResponse
    {
        $data = $request->validate([
            'customer_id'    => ['required', 'exists:customers,id'],
            'unit_type_id'   => ['required', 'exists:unit_types,id'],
            'priority_score' => ['nullable', 'integer'],
            'notes'          => ['nullable', 'string'],
        ]);

        $data['park_id'] = $parkId;

        $entry = WaitingList::create($data);

        $this->writeAuditLog($request, 'create', $entry, null, $entry->toArray());

        return response()->json($entry->load(['customer', 'unitType']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $entry = WaitingList::findOrFail($id);
        $old   = $entry->toArray();

        $data = $request->validate([
            'priority_score' => ['sometimes', 'integer'],
            'notes'          => ['sometimes', 'nullable', 'string'],
            'unit_type_id'   => ['sometimes', 'exists:unit_types,id'],
        ]);

        $entry->update($data);

        $this->writeAuditLog($request, 'update', $entry, $old, $entry->fresh()->toArray());

        return response()->json($entry->fresh()->load(['customer', 'unitType']));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $entry = WaitingList::findOrFail($id);
        $old   = $entry->toArray();
        $entry->delete();

        $this->writeAuditLog($request, 'delete', $entry, $old, null);

        return response()->json(['message' => 'Waiting list entry deleted.']);
    }

    public function notify(Request $request, int $id): JsonResponse
    {
        $entry = WaitingList::with('customer')->findOrFail($id);

        $data = $request->validate([
            'unit_id' => ['required', 'exists:units,id'],
            'method'  => ['sometimes', 'in:email,sms'],
        ]);

        $method = $data['method'] ?? 'email';

        $notification = WaitingListNotification::create([
            'waiting_list_id' => $entry->id,
            'unit_id'         => $data['unit_id'],
            'sent_at'         => now(),
            'method'          => $method,
            'response'        => 'no_response',
        ]);

        // Update notified_at on waiting list entry
        $entry->update(['notified_at' => now()]);

        // Create SentEmail record
        $template = MailTemplate::where('template_type', 'unit_available')
            ->where(function ($q) use ($entry) {
                $q->where('park_id', $entry->park_id)->orWhereNull('park_id');
            })
            ->where('active', true)
            ->first();

        SentEmail::create([
            'customer_id'     => $entry->customer_id,
            'template_id'     => $template?->id,
            'sent_by'         => $request->user()->id,
            'subject'         => $template?->subject ?? 'Unit Available',
            'body_html'       => $template?->body_html ?? '<p>A unit matching your preferences is now available.</p>',
            'recipient_email' => $entry->customer->email ?? '',
            'status'          => 'queued',
        ]);

        $this->writeAuditLog($request, 'notify', $entry, null, ['notification_id' => $notification->id]);

        return response()->json([
            'notification'  => $notification,
            'waiting_entry' => $entry->fresh(),
        ], 201);
    }

    public function convert(Request $request, int $id): JsonResponse
    {
        $entry = WaitingList::findOrFail($id);

        $data = $request->validate([
            'desired_start_date' => ['nullable', 'date'],
            'notes'              => ['nullable', 'string'],
        ]);

        $old = $entry->toArray();

        $application = Application::create([
            'park_id'            => $entry->park_id,
            'customer_id'        => $entry->customer_id,
            'unit_type_id'       => $entry->unit_type_id,
            'desired_start_date' => $data['desired_start_date'] ?? null,
            'notes'              => $data['notes'] ?? $entry->notes,
            'source'             => 'walk_in',
        ]);

        $application->refresh();

        $entry->update(['converted_application_id' => $application->id]);

        $this->writeAuditLog($request, 'convert', $entry, $old, ['application_id' => $application->id]);

        return response()->json([
            'application'  => $application->load(['customer', 'park', 'unitType']),
            'waiting_entry' => $entry->fresh(),
        ], 201);
    }

    private function writeAuditLog(Request $request, string $action, WaitingList $model, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id'    => $request->user()?->id,
            'action'     => $action,
            'model_type' => WaitingList::class,
            'model_id'   => $model->id,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
