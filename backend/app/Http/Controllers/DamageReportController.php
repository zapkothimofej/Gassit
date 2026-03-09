<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\DamagePhoto;
use App\Models\DamageReport;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Park;
use App\Models\SentEmail;
use App\Models\Unit;
use App\Models\Vendor;
use App\Jobs\NotifyWaitingListEntries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DamageReportController extends Controller
{
    private const TRANSITIONS = [
        'reported'       => ['in_assessment'],
        'in_assessment'  => ['repair_ordered', 'reported'],
        'repair_ordered' => ['in_repair'],
        'in_repair'      => ['resolved'],
        'resolved'       => ['closed'],
        'closed'         => [],
    ];

    public function index(Request $request): JsonResponse
    {
        $query = DamageReport::with(['unit', 'contract', 'reportedBy', 'assignedVendor', 'photos']);

        if ($request->filled('park_id')) {
            $query->whereHas('unit', fn ($q) => $q->where('park_id', $request->query('park_id')));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('unit_id')) {
            $query->where('unit_id', $request->query('unit_id'));
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'unit_id'        => ['required', 'integer', 'exists:units,id'],
            'contract_id'    => ['nullable', 'integer', 'exists:contracts,id'],
            'description'    => ['required', 'string'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['reported_by'] = $request->user()->id;
        $data['status'] = 'reported';

        $report = DamageReport::create($data);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'create',
            'model_type' => DamageReport::class,
            'model_id'   => $report->id,
            'new_values' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json($report->load(['unit', 'reportedBy']), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $report = DamageReport::findOrFail($id);

        $data = $request->validate([
            'description'    => ['sometimes', 'string'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'actual_cost'    => ['nullable', 'numeric', 'min:0'],
        ]);

        $old = $report->only(array_keys($data));
        $report->update($data);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'update',
            'model_type' => DamageReport::class,
            'model_id'   => $report->id,
            'old_values' => $old,
            'new_values' => $data,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json($report->fresh(['unit', 'assignedVendor']));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $report = DamageReport::findOrFail($id);
        $report->delete();

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'delete',
            'model_type' => DamageReport::class,
            'model_id'   => $id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['message' => 'Deleted']);
    }

    public function uploadPhoto(Request $request, int $id): JsonResponse
    {
        $report = DamageReport::findOrFail($id);

        $request->validate([
            'photo'    => ['required', 'file', 'mimes:jpg,jpeg,png,webp'],
            'caption'  => ['nullable', 'string'],
            'taken_at' => ['nullable', 'date'],
        ]);

        $path = Storage::disk('s3')->put("damage-photos/{$id}", $request->file('photo'));

        $photo = DamagePhoto::create([
            'damage_report_id' => $report->id,
            'path'             => $path,
            'caption'          => $request->input('caption'),
            'taken_at'         => $request->input('taken_at'),
        ]);

        return response()->json($photo, 201);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $report = DamageReport::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', 'string'],
        ]);

        $newStatus = $data['status'];
        $allowed = self::TRANSITIONS[$report->status] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            return response()->json([
                'message' => "Cannot transition from '{$report->status}' to '{$newStatus}'",
            ], 422);
        }

        $old = $report->status;
        $update = ['status' => $newStatus];
        if ($newStatus === 'resolved') {
            $update['resolved_at'] = now();
        }

        $report->update($update);

        // When termination inspection is resolved, free the unit and notify waiting list
        if ($newStatus === 'resolved' && $report->is_termination_inspection) {
            $unit = $report->unit;
            if ($unit) {
                $unit->update(['status' => 'free']);
                NotifyWaitingListEntries::dispatch($unit->id);
            }
        }

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'status_change',
            'model_type' => DamageReport::class,
            'model_id'   => $report->id,
            'old_values' => ['status' => $old],
            'new_values' => ['status' => $newStatus],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json($report->fresh());
    }

    public function assignVendor(Request $request, int $id): JsonResponse
    {
        $report = DamageReport::findOrFail($id);

        $data = $request->validate([
            'vendor_id' => ['required', 'integer', 'exists:vendors,id'],
        ]);

        $vendor = Vendor::findOrFail($data['vendor_id']);
        $old = $report->assigned_vendor_id;
        $report->update(['assigned_vendor_id' => $vendor->id]);

        // Notify vendor by email (log to sent_emails)
        SentEmail::create([
            'recipient_email' => $vendor->email,
            'subject'         => "Damage Report #{$report->id} Assigned to You",
            'body_html'       => "<p>Dear {$vendor->contact_name},<br>Damage report #{$report->id} has been assigned to you. Description: {$report->description}</p>",
            'status'          => 'queued',
        ]);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'assign_vendor',
            'model_type' => DamageReport::class,
            'model_id'   => $report->id,
            'old_values' => ['assigned_vendor_id' => $old],
            'new_values' => ['assigned_vendor_id' => $vendor->id],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json($report->fresh(['assignedVendor']));
    }

    public function generateInvoice(Request $request, int $id): JsonResponse
    {
        $report = DamageReport::with(['unit.park', 'contract.customer'])->findOrFail($id);

        if (!$report->contract_id) {
            return response()->json(['message' => 'No contract linked to this damage report'], 422);
        }

        $contract = $report->contract;
        $unit = $report->unit;
        $park = $unit->park;
        $customer = $contract->customer;

        $amount = $report->actual_cost ?? $report->estimated_cost ?? 0;

        $invoice = DB::transaction(function () use ($report, $contract, $park, $customer, $amount) {
            $parkCode = strtoupper(preg_replace('/[^a-zA-Z]/', '', $park->name));
            $parkCode = substr($parkCode, 0, 4) ?: 'PARK';
            $year = now()->year;

            $lastInvoice = Invoice::where('invoice_number', 'LIKE', "{$parkCode}-{$year}-%")
                ->orderByDesc('invoice_number')
                ->first();

            $seq = $lastInvoice
                ? (int) substr($lastInvoice->invoice_number, strrpos($lastInvoice->invoice_number, '-') + 1) + 1
                : 1;

            $invoiceNumber = "{$parkCode}-{$year}-" . str_pad($seq, 4, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'contract_id'    => $contract->id,
                'customer_id'    => $customer->id,
                'park_id'        => $park->id,
                'invoice_number' => $invoiceNumber,
                'issue_date'     => now()->format('Y-m-d'),
                'due_date'       => now()->addDays(14)->format('Y-m-d'),
                'subtotal'       => $amount,
                'tax_rate'       => 0,
                'tax_amount'     => 0,
                'total_amount'   => $amount,
                'status'         => 'draft',
            ]);

            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => "Damage repair — Report #{$report->id}: {$report->description}",
                'quantity'    => 1,
                'unit_price'  => $amount,
                'total'       => $amount,
                'item_type'   => 'damage',
                'sort_order'  => 1,
            ]);

            return $invoice;
        });

        return response()->json($invoice->load('items'), 201);
    }
}
