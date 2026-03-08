<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Vendor;
use App\Models\VendorInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VendorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Vendor::with('park');

        if ($request->filled('park_id')) {
            $query->where('park_id', $request->query('park_id'));
        }

        if ($request->filled('active')) {
            $query->where('active', filter_var($request->query('active'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->orderBy('name')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'park_id'      => 'nullable|exists:parks,id',
            'name'         => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'phone'        => 'required|string|max:50',
            'email'        => 'required|email|max:255',
            'specialty'    => 'required|string|max:255',
            'hourly_rate'  => 'nullable|numeric|min:0',
            'active'       => 'boolean',
        ]);

        $vendor = Vendor::create($data);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'create',
            'model_type' => 'Vendor',
            'model_id'   => $vendor->id,
            'old_values' => null,
            'new_values' => $vendor->toArray(),
        ]);

        return response()->json($vendor->load('park'), 201);
    }

    public function show(int $id): JsonResponse
    {
        $vendor = Vendor::with(['park', 'damageReports', 'invoices'])->findOrFail($id);
        return response()->json($vendor);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $vendor = Vendor::findOrFail($id);
        $old = $vendor->toArray();

        $data = $request->validate([
            'park_id'      => 'nullable|exists:parks,id',
            'name'         => 'sometimes|string|max:255',
            'contact_name' => 'sometimes|string|max:255',
            'phone'        => 'sometimes|string|max:50',
            'email'        => 'sometimes|email|max:255',
            'specialty'    => 'sometimes|string|max:255',
            'hourly_rate'  => 'nullable|numeric|min:0',
            'active'       => 'boolean',
        ]);

        $vendor->update($data);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'update',
            'model_type' => 'Vendor',
            'model_id'   => $vendor->id,
            'old_values' => $old,
            'new_values' => $vendor->fresh()->toArray(),
        ]);

        return response()->json($vendor->load('park'));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $vendor = Vendor::findOrFail($id);
        $old = $vendor->toArray();

        $vendor->delete();

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'delete',
            'model_type' => 'Vendor',
            'model_id'   => $id,
            'old_values' => $old,
            'new_values' => null,
        ]);

        return response()->json(['message' => 'Vendor deleted']);
    }

    // Vendor invoices

    public function invoicesIndex(int $id): JsonResponse
    {
        $vendor = Vendor::findOrFail($id);
        $invoices = $vendor->invoices()->with('damageReport')->orderByDesc('created_at')->get();
        return response()->json($invoices);
    }

    public function invoicesStore(Request $request, int $id): JsonResponse
    {
        $vendor = Vendor::findOrFail($id);

        $data = $request->validate([
            'damage_report_id' => 'nullable|exists:damage_reports,id',
            'amount'           => 'required|numeric|min:0',
            'pdf_path'         => 'nullable|string|max:500',
        ]);

        $invoice = $vendor->invoices()->create($data);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'create',
            'model_type' => 'VendorInvoice',
            'model_id'   => $invoice->id,
            'old_values' => null,
            'new_values' => $invoice->toArray(),
        ]);

        return response()->json($invoice->load('damageReport'), 201);
    }

    public function invoicesUpdate(Request $request, int $id, int $invoiceId): JsonResponse
    {
        $vendor = Vendor::findOrFail($id);
        $invoice = VendorInvoice::where('vendor_id', $vendor->id)->findOrFail($invoiceId);
        $old = $invoice->toArray();

        $data = $request->validate([
            'damage_report_id' => 'nullable|exists:damage_reports,id',
            'amount'           => 'sometimes|numeric|min:0',
            'pdf_path'         => 'nullable|string|max:500',
        ]);

        $invoice->update($data);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'update',
            'model_type' => 'VendorInvoice',
            'model_id'   => $invoice->id,
            'old_values' => $old,
            'new_values' => $invoice->fresh()->toArray(),
        ]);

        return response()->json($invoice->load('damageReport'));
    }

    public function invoicesPay(Request $request, int $id, int $invoiceId): JsonResponse
    {
        $vendor = Vendor::findOrFail($id);
        $invoice = VendorInvoice::where('vendor_id', $vendor->id)->findOrFail($invoiceId);

        if ($invoice->paid_at !== null) {
            return response()->json(['message' => 'Invoice already paid'], 422);
        }

        $old = $invoice->toArray();
        $invoice->update(['paid_at' => now()]);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'pay',
            'model_type' => 'VendorInvoice',
            'model_id'   => $invoice->id,
            'old_values' => $old,
            'new_values' => $invoice->fresh()->toArray(),
        ]);

        return response()->json($invoice->fresh());
    }

    public function damageReports(int $id): JsonResponse
    {
        $vendor = Vendor::findOrFail($id);
        $reports = $vendor->damageReports()->with(['unit', 'contract', 'photos'])->orderByDesc('created_at')->get();
        return response()->json($reports);
    }
}
