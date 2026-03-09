<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateMonthlyInvoices;
use App\Models\AuditLog;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MailTemplate;
use App\Models\Park;
use App\Models\SentEmail;
use App\Services\InvoiceService;
use App\Services\PdfService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Invoice::with(['customer', 'park', 'contract', 'items']);

        if ($request->filled('park_id')) {
            $query->where('park_id', $request->query('park_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->query('customer_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('issue_date', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('issue_date', '<=', $request->query('to'));
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function show(int $id): JsonResponse
    {
        $invoice = Invoice::with(['customer', 'park', 'contract', 'items'])->findOrFail($id);
        return response()->json($invoice);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'park_id'     => ['required', 'integer', 'exists:parks,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'due_date'    => ['required', 'date'],
            'tax_rate'    => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items'       => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string'],
            'items.*.quantity'    => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
            'items.*.item_type'   => ['nullable', 'string'],
        ]);

        $park = Park::findOrFail($data['park_id']);
        $taxRate = $data['tax_rate'] ?? 0;

        $subtotal = 0;
        foreach ($data['items'] as $item) {
            $subtotal += round($item['quantity'] * $item['unit_price'], 2);
        }
        $taxAmount   = round($subtotal * $taxRate / 100, 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        $invoiceService = new InvoiceService();
        $invoiceNumber = $invoiceService->generateInvoiceNumber($park);

        $invoice = Invoice::create([
            'customer_id'    => $data['customer_id'],
            'park_id'        => $data['park_id'],
            'contract_id'    => $data['contract_id'] ?? null,
            'invoice_number' => $invoiceNumber,
            'issue_date'     => now()->format('Y-m-d'),
            'due_date'       => $data['due_date'],
            'subtotal'       => $subtotal,
            'tax_rate'       => $taxRate,
            'tax_amount'     => $taxAmount,
            'total_amount'   => $totalAmount,
            'status'         => 'draft',
        ]);

        foreach ($data['items'] as $index => $item) {
            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => $item['description'],
                'quantity'    => $item['quantity'],
                'unit_price'  => $item['unit_price'],
                'total'       => round($item['quantity'] * $item['unit_price'], 2),
                'item_type'   => $item['item_type'] ?? 'rent',
                'sort_order'  => $index,
            ]);
        }

        $this->writeAuditLog($request, 'invoice_created', $invoice, [], ['invoice_number' => $invoiceNumber, 'total_amount' => $totalAmount]);

        return response()->json($invoice->load('items'), 201);
    }

    public function generateMonthly(Request $request): JsonResponse
    {
        if ($request->user()?->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $month = $request->input('month', now()->format('Y-m'));

        GenerateMonthlyInvoices::dispatch($month);

        return response()->json(['message' => 'Monthly invoice generation queued.', 'month' => $month]);
    }

    public function pdf(int $id): Response
    {
        $invoice = Invoice::with(['customer', 'park', 'items'])->findOrFail($id);

        if ($invoice->pdf_path && Storage::disk('s3')->exists($invoice->pdf_path)) {
            $content = Storage::disk('s3')->get($invoice->pdf_path);
        } else {
            $pdfService = new PdfService();
            $path       = $pdfService->generateInvoice($invoice);
            $content    = Storage::disk('s3')->get($path);
        }

        return response($content, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="invoice-' . $invoice->invoice_number . '.pdf"',
        ]);
    }

    public function send(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::with(['customer', 'park', 'items'])->findOrFail($id);

        if (in_array($invoice->status, ['cancelled'])) {
            return response()->json(['message' => 'Cannot send a cancelled invoice.'], 422);
        }

        // Generate PDF if needed
        if (!$invoice->pdf_path) {
            $pdfService = new PdfService();
            $pdfService->generateInvoice($invoice);
            $invoice->refresh();
        }

        $template = MailTemplate::where('template_type', 'invoice')
            ->where(function ($q) use ($invoice) {
                $q->where('park_id', $invoice->park_id)->orWhereNull('park_id');
            })
            ->where('active', true)
            ->orderByRaw('park_id IS NULL ASC')
            ->first();

        SentEmail::create([
            'customer_id'     => $invoice->customer_id,
            'template_id'     => $template?->id,
            'sent_by'         => $request->user()->id,
            'subject'         => $template?->subject ?? 'Invoice ' . $invoice->invoice_number,
            'body_html'       => $template?->body_html ?? '<p>Please find your invoice attached.</p>',
            'recipient_email' => $invoice->customer->email ?? '',
            'status'          => 'queued',
        ]);

        $old = $invoice->toArray();
        $invoice->update(['status' => 'sent', 'sent_at' => now()]);

        $this->writeAuditLog($request, 'invoice_sent', $invoice, ['status' => $old['status']], ['status' => 'sent']);

        return response()->json($invoice->fresh());
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $invoice = Invoice::with('items')->findOrFail($id);

        if ($invoice->status === 'cancelled') {
            return response()->json(['message' => 'Invoice is already cancelled.'], 422);
        }

        $old = $invoice->toArray();

        DB::transaction(function () use ($invoice, $request, $old) {
            $invoice->update([
                'status'       => 'cancelled',
                'cancelled_at' => now(),
            ]);

            // Create credit note line
            InvoiceItem::create([
                'invoice_id'  => $invoice->id,
                'description' => 'Credit note - cancellation of invoice ' . $invoice->invoice_number,
                'quantity'    => 1,
                'unit_price'  => -$invoice->total_amount,
                'total'       => -$invoice->total_amount,
                'item_type'   => 'credit_note',
                'sort_order'  => $invoice->items()->count(),
            ]);

            AuditLog::create([
                'user_id'    => $request->user()->id,
                'action'     => 'invoice_cancelled',
                'model_type' => Invoice::class,
                'model_id'   => $invoice->id,
                'old_values' => json_encode(['status' => $old['status']]),
                'new_values' => json_encode(['status' => 'cancelled']),
            ]);
        });

        return response()->json($invoice->fresh()->load('items'));
    }

    public function datevExport(Request $request): Response
    {
        $data = $request->validate([
            'park_id' => ['nullable', 'integer', 'exists:parks,id'],
            'from'    => ['required', 'date'],
            'to'      => ['required', 'date'],
        ]);

        $query = Invoice::with(['customer', 'park'])
            ->whereDate('issue_date', '>=', $data['from'])
            ->whereDate('issue_date', '<=', $data['to'])
            ->whereNotIn('status', ['cancelled']);

        if (!empty($data['park_id'])) {
            $query->where('park_id', $data['park_id']);
        }

        $invoices = $query->get();

        // EXTF CSV format for DATEV
        $lines = [];

        // Header row 1: EXTF format identifier
        $lines[] = '"EXTF";700;21;"Buchungsstapel";13;' . now()->format('YmdHis') . '000;;;"";"";"";1;;;;"EUR";;;;';

        // Header row 2: column headers
        $lines[] = '"Umsatz (ohne Soll/Haben-Kz)";"Soll/Haben-Kennzeichen";"WKZ Umsatz";"Kurs";"Basis-Umsatz";"WKZ Basis-Umsatz";"Konto";"Gegenkonto (ohne BU-Schlüssel)";"BU-Schlüssel";"Belegdatum";"Belegfeld 1";"Belegfeld 2";"Skonto";"Buchungstext"';

        foreach ($invoices as $invoice) {
            $amount    = number_format((float) $invoice->total_amount, 2, ',', '');
            $date      = \Carbon\Carbon::parse($invoice->issue_date)->format('dm');
            $debtor    = '10' . str_pad($invoice->customer_id, 4, '0', STR_PAD_LEFT);
            $revenue   = '8400'; // Standard revenue account
            $lines[]   = '"' . $amount . '";"S";"EUR";;;"";' . $debtor . ';' . $revenue . ';;' . $date . ';"' . $invoice->invoice_number . '";;;"' . addslashes($invoice->customer->last_name ?? 'Customer') . '"';
        }

        $csv = implode("\r\n", $lines);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="datev-export-' . $data['from'] . '-' . $data['to'] . '.csv"',
        ]);
    }

    private function writeAuditLog(Request $request, string $action, $model, array $old, array $new): void
    {
        AuditLog::create([
            'user_id'    => $request->user()->id,
            'action'     => $action,
            'model_type' => get_class($model),
            'model_id'   => $model->id,
            'old_values' => json_encode($old),
            'new_values' => json_encode($new),
        ]);
    }
}
