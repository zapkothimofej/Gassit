<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ElectricityMeter;
use App\Models\ElectricityPricing;
use App\Models\ElectricityReading;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Park;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ElectricityMeterController extends Controller
{
    // --- Meters CRUD ---

    public function index(int $unitId): JsonResponse
    {
        $unit = Unit::findOrFail($unitId);
        $meters = ElectricityMeter::where('unit_id', $unit->id)->get();

        return response()->json($meters);
    }

    public function store(Request $request, int $unitId): JsonResponse
    {
        $unit = Unit::findOrFail($unitId);

        $data = $request->validate([
            'meter_number' => ['required', 'string', 'max:100'],
            'meter_type'   => ['required', 'in:main,sub'],
            'active'       => ['sometimes', 'boolean'],
            'installed_at' => ['required', 'date'],
        ]);

        $meter = ElectricityMeter::create([
            'unit_id'      => $unit->id,
            'meter_number' => $data['meter_number'],
            'meter_type'   => $data['meter_type'],
            'active'       => $data['active'] ?? true,
            'installed_at' => $data['installed_at'],
        ]);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'electricity_meter_created',
            'model_type' => ElectricityMeter::class,
            'model_id'   => $meter->id,
            'old_values' => null,
            'new_values' => ['meter_number' => $meter->meter_number, 'unit_id' => $unit->id],
        ]);

        return response()->json($meter, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $meter = ElectricityMeter::findOrFail($id);

        $data = $request->validate([
            'meter_number' => ['sometimes', 'string', 'max:100'],
            'meter_type'   => ['sometimes', 'in:main,sub'],
            'active'       => ['sometimes', 'boolean'],
            'installed_at' => ['sometimes', 'date'],
        ]);

        $old = $meter->only(['meter_number', 'meter_type', 'active']);
        $meter->update($data);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'electricity_meter_updated',
            'model_type' => ElectricityMeter::class,
            'model_id'   => $meter->id,
            'old_values' => $old,
            'new_values' => $data,
        ]);

        return response()->json($meter->fresh());
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $meter = ElectricityMeter::findOrFail($id);
        $meter->delete();

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'electricity_meter_deleted',
            'model_type' => ElectricityMeter::class,
            'model_id'   => $id,
            'old_values' => ['meter_number' => $meter->meter_number],
            'new_values' => null,
        ]);

        return response()->json(null, 204);
    }

    // --- Readings ---

    public function storeReading(Request $request, int $meterId): JsonResponse
    {
        $meter = ElectricityMeter::findOrFail($meterId);

        $data = $request->validate([
            'reading_date'  => ['required', 'date'],
            'reading_value' => ['required', 'numeric', 'min:0'],
            'photo'         => ['nullable', 'file', 'mimes:jpg,jpeg,png'],
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = Storage::disk('s3')->putFile(
                'electricity-readings',
                $request->file('photo')
            );
        }

        // Compute consumption from previous reading
        $previous = ElectricityReading::where('meter_id', $meterId)
            ->orderByDesc('reading_date')
            ->orderByDesc('id')
            ->first();

        $consumption = null;
        if ($previous !== null) {
            $consumption = max(0, $data['reading_value'] - $previous->reading_value);
        }

        $reading = ElectricityReading::create([
            'meter_id'      => $meterId,
            'reading_date'  => $data['reading_date'],
            'reading_value' => $data['reading_value'],
            'photo_path'    => $photoPath,
            'recorded_by'   => $request->user()->id,
            'consumption'   => $consumption,
        ]);

        return response()->json($reading, 201);
    }

    public function indexReadings(int $meterId): JsonResponse
    {
        ElectricityMeter::findOrFail($meterId);

        $readings = ElectricityReading::where('meter_id', $meterId)
            ->orderByDesc('reading_date')
            ->orderByDesc('id')
            ->get();

        // Attach computed consumption (already stored, but ensure it's present)
        return response()->json($readings);
    }

    public function billReading(Request $request, int $meterId, int $readingId): JsonResponse
    {
        $meter = ElectricityMeter::with('unit.park')->findOrFail($meterId);
        $reading = ElectricityReading::findOrFail($readingId);

        if ($reading->meter_id !== $meter->id) {
            return response()->json(['message' => 'Reading does not belong to this meter.'], 422);
        }

        if ($reading->consumption === null || $reading->consumption <= 0) {
            return response()->json(['message' => 'No positive consumption to bill.'], 422);
        }

        $park = $meter->unit->park;

        // Find applicable pricing
        $pricing = ElectricityPricing::where('park_id', $park->id)
            ->where('valid_from', '<=', $reading->reading_date)
            ->where(function ($q) use ($reading) {
                $q->whereNull('valid_to')->orWhere('valid_to', '>=', $reading->reading_date);
            })
            ->orderByDesc('valid_from')
            ->first();

        if ($pricing === null) {
            return response()->json(['message' => 'No electricity pricing found for this period.'], 422);
        }

        $charge = round((float) $reading->consumption * (float) $pricing->price_per_kwh, 2);

        // Find active contract for the unit
        $contract = \App\Models\Contract::where('unit_id', $meter->unit_id)
            ->whereIn('status', ['active'])
            ->first();

        $customerId = $contract?->customer_id;
        $contractId = $contract?->id;

        if ($customerId === null) {
            return response()->json(['message' => 'No active contract found for this unit.'], 422);
        }

        // Try to find an existing open (draft/sent) invoice to add to
        $invoice = Invoice::where('customer_id', $customerId)
            ->where('park_id', $park->id)
            ->whereIn('status', ['draft'])
            ->orderByDesc('created_at')
            ->first();

        if ($invoice === null) {
            // Generate invoice number
            $code = strtoupper(preg_replace('/[^A-Za-z]/', '', $park->name));
            $code = substr($code, 0, 4) ?: 'PARK';
            $year = now()->year;
            $prefix = $code . '-' . $year . '-';
            $last = Invoice::where('invoice_number', 'like', $prefix . '%')
                ->orderByDesc('invoice_number')
                ->value('invoice_number');
            $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;
            $invoiceNumber = $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);

            $invoice = Invoice::create([
                'customer_id'    => $customerId,
                'park_id'        => $park->id,
                'contract_id'    => $contractId,
                'invoice_number' => $invoiceNumber,
                'issue_date'     => now()->format('Y-m-d'),
                'due_date'       => now()->addDays(14)->format('Y-m-d'),
                'subtotal'       => 0,
                'tax_rate'       => 0,
                'tax_amount'     => 0,
                'total_amount'   => 0,
                'status'         => 'draft',
            ]);
        }

        $sortOrder = $invoice->items()->count();
        $description = sprintf(
            'Electricity charge: %.4f kWh × %.6f EUR/kWh (reading %s)',
            (float) $reading->consumption,
            (float) $pricing->price_per_kwh,
            $reading->reading_date->format('Y-m-d')
        );

        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'description' => $description,
            'quantity'    => (float) $reading->consumption,
            'unit_price'  => (float) $pricing->price_per_kwh,
            'total'       => $charge,
            'item_type'   => 'electricity',
            'sort_order'  => $sortOrder,
        ]);

        // Recalculate invoice totals
        $subtotal = $invoice->items()->sum('total');
        $taxAmount = round($subtotal * $invoice->tax_rate / 100, 2);
        $invoice->update([
            'subtotal'     => $subtotal,
            'tax_amount'   => $taxAmount,
            'total_amount' => round($subtotal + $taxAmount, 2),
        ]);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'electricity_billed',
            'model_type' => ElectricityReading::class,
            'model_id'   => $reading->id,
            'old_values' => null,
            'new_values' => ['invoice_id' => $invoice->id, 'charge' => $charge],
        ]);

        return response()->json([
            'invoice_id'  => $invoice->id,
            'item_charge' => $charge,
            'invoice'     => $invoice->fresh()->load('items'),
        ]);
    }

    // --- Pricing ---

    public function pricingIndex(int $parkId): JsonResponse
    {
        Park::findOrFail($parkId);
        $pricing = ElectricityPricing::where('park_id', $parkId)
            ->orderByDesc('valid_from')
            ->get();

        return response()->json($pricing);
    }

    public function pricingStore(Request $request, int $parkId): JsonResponse
    {
        $park = Park::findOrFail($parkId);

        $data = $request->validate([
            'price_per_kwh' => ['required', 'numeric', 'min:0'],
        ]);

        $today = now()->format('Y-m-d');

        // Close previous open period
        ElectricityPricing::where('park_id', $parkId)
            ->whereNull('valid_to')
            ->update(['valid_to' => $today]);

        $pricing = ElectricityPricing::create([
            'park_id'       => $parkId,
            'price_per_kwh' => $data['price_per_kwh'],
            'valid_from'    => $today,
            'valid_to'      => null,
        ]);

        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => 'electricity_pricing_created',
            'model_type' => ElectricityPricing::class,
            'model_id'   => $pricing->id,
            'old_values' => null,
            'new_values' => ['park_id' => $parkId, 'price_per_kwh' => $data['price_per_kwh'], 'valid_from' => $today],
        ]);

        return response()->json($pricing, 201);
    }
}
