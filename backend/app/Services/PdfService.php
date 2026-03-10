<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Deposit;
use App\Models\DocumentTemplate;
use App\Models\DunningRecord;
use App\Models\Invoice;
use App\Models\Unit;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;

class PdfService
{
    private function renderPdf(string $html): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Arial');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function renderTemplate(string $templateHtml, array $variables): string
    {
        $html = $templateHtml;
        foreach ($variables as $key => $value) {
            $escaped = $key === 'invoice_items'
                ? (string) ($value ?? '')
                : htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
            $html = str_replace('{' . $key . '}', $escaped, $html);
        }
        return $html;
    }

    private function findTemplate(string $documentType, ?int $parkId): ?DocumentTemplate
    {
        return DocumentTemplate::where('document_type', $documentType)
            ->where('active', true)
            ->where(function ($q) use ($parkId) {
                $q->where('park_id', $parkId)->orWhereNull('park_id');
            })
            ->orderByRaw('park_id IS NULL ASC')
            ->first();
    }

    private function storeToS3(string $pdfContent, string $path): string
    {
        Storage::disk('s3')->put($path, $pdfContent);
        return $path;
    }

    public function generateContract(Contract $contract): string
    {
        $contract->load(['customer', 'unit.park', 'unit.unitType']);

        $customer = $contract->customer;
        $unit     = $contract->unit;
        $park     = $unit?->park;

        $variables = [
            'customer_name'  => trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')),
            'customer_email' => $customer->email ?? '',
            'customer_phone' => $customer->phone ?? '',
            'customer_address' => ($customer->address ?? '') . ', ' . ($customer->zip ?? '') . ' ' . ($customer->city ?? ''),
            'unit_number'    => $unit?->unit_number ?? '',
            'unit_size_m2'   => $unit?->size_m2 ?? '',
            'rent_amount'    => number_format((float) $contract->rent_amount, 2, '.', ',') . ' EUR',
            'deposit_amount' => number_format((float) $contract->deposit_amount, 2, '.', ',') . ' EUR',
            'start_date'     => $contract->start_date?->format('d.m.Y') ?? '',
            'end_date'       => $contract->end_date?->format('d.m.Y') ?? '',
            'park_name'      => $park?->name ?? '',
            'park_address'   => ($park?->address ?? '') . ', ' . ($park?->zip ?? '') . ' ' . ($park?->city ?? ''),
            'park_logo_url'  => $park?->logo_path ? Storage::disk('s3')->url($park->logo_path) : '',
            'notice_period_days' => $contract->notice_period_days ?? 30,
            'contract_id'    => $contract->id,
        ];

        $template = $this->findTemplate('rental_contract', $park?->id);
        $html = $template
            ? $this->renderTemplate($template->template_html, $variables)
            : $this->buildContractHtml($variables);

        $pdfContent = $this->renderPdf($html);
        $path = 'contracts/contract-' . $contract->id . '-' . time() . '.pdf';
        $this->storeToS3($pdfContent, $path);

        $contract->update(['signed_pdf_path' => $path]);

        return $path;
    }

    public function generateInvoice(Invoice $invoice): string
    {
        $invoice->load(['customer', 'park', 'items', 'contract.unit']);

        $customer = $invoice->customer;
        $park     = $invoice->park;
        $items    = $invoice->items;

        $itemRows = '';
        foreach ($items as $item) {
            $itemRows .= '<tr>'
                . '<td>' . htmlspecialchars($item->description) . '</td>'
                . '<td>' . $item->item_type . '</td>'
                . '<td style="text-align:right">' . number_format((float) $item->quantity, 2) . '</td>'
                . '<td style="text-align:right">' . number_format((float) $item->unit_price, 2) . '</td>'
                . '<td style="text-align:right">' . number_format((float) $item->total, 2) . ' EUR</td>'
                . '</tr>';
        }

        $variables = [
            'customer_name'   => trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')),
            'customer_email'  => $customer->email ?? '',
            'customer_address' => ($customer->address ?? '') . ', ' . ($customer->zip ?? '') . ' ' . ($customer->city ?? ''),
            'invoice_number'  => $invoice->invoice_number ?? '',
            'issue_date'      => $invoice->issue_date?->format('d.m.Y') ?? '',
            'due_date'        => $invoice->due_date?->format('d.m.Y') ?? '',
            'subtotal'        => number_format((float) $invoice->subtotal, 2, '.', ',') . ' EUR',
            'tax_rate'        => number_format((float) $invoice->tax_rate, 2) . '%',
            'tax_amount'      => number_format((float) $invoice->tax_amount, 2, '.', ',') . ' EUR',
            'total_amount'    => number_format((float) $invoice->total_amount, 2, '.', ',') . ' EUR',
            'park_name'       => $park?->name ?? '',
            'park_address'    => ($park?->address ?? '') . ', ' . ($park?->zip ?? '') . ' ' . ($park?->city ?? ''),
            'park_logo_url'   => $park?->logo_path ? Storage::disk('s3')->url($park->logo_path) : '',
            'park_iban'       => $park?->bank_iban ?? '',
            'park_bic'        => $park?->bank_bic ?? '',
            'invoice_items'   => $itemRows,
        ];

        $template = $this->findTemplate('invoice', $park?->id);
        $html = $template
            ? $this->renderTemplate($template->template_html, $variables)
            : $this->buildInvoiceHtml($variables);

        $pdfContent = $this->renderPdf($html);
        $path = 'invoices/invoice-' . $invoice->id . '-' . time() . '.pdf';
        $this->storeToS3($pdfContent, $path);

        $invoice->update(['pdf_path' => $path]);

        return $path;
    }

    public function generateDunningLetter(DunningRecord $dunningRecord): string
    {
        $dunningRecord->load(['customer', 'invoice.park']);

        $customer = $dunningRecord->customer;
        $invoice  = $dunningRecord->invoice;
        $park     = $invoice?->park;
        $level    = $dunningRecord->level;

        $documentType = match ($level) {
            1 => 'dunning_letter',
            2 => 'dunning_letter',
            3 => 'dunning_letter',
            default => 'dunning_letter',
        };

        $variables = [
            'customer_name'   => trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')),
            'customer_email'  => $customer->email ?? '',
            'customer_address' => ($customer->address ?? '') . ', ' . ($customer->zip ?? '') . ' ' . ($customer->city ?? ''),
            'invoice_number'  => $invoice?->invoice_number ?? '',
            'invoice_total'   => number_format((float) ($invoice?->total_amount ?? 0), 2, '.', ',') . ' EUR',
            'due_date'        => $invoice?->due_date?->format('d.m.Y') ?? '',
            'dunning_level'   => $level,
            'fee_amount'      => number_format((float) $dunningRecord->fee_amount, 2, '.', ',') . ' EUR',
            'sent_at'         => $dunningRecord->sent_at?->format('d.m.Y') ?? now()->format('d.m.Y'),
            'park_name'       => $park?->name ?? '',
            'park_logo_url'   => $park?->logo_path ? Storage::disk('s3')->url($park->logo_path) : '',
        ];

        $template = $this->findTemplate($documentType, $park?->id);
        $html = $template
            ? $this->renderTemplate($template->template_html, $variables)
            : $this->buildDunningHtml($variables);

        $pdfContent = $this->renderPdf($html);
        $path = 'dunning/dunning-' . $dunningRecord->id . '-level' . $level . '-' . time() . '.pdf';
        $this->storeToS3($pdfContent, $path);

        $dunningRecord->update(['template_used' => $path]);

        return $path;
    }

    public function generateDepositReturn(Deposit $deposit): string
    {
        $deposit->load(['customer', 'park', 'contract.unit']);

        $customer = $deposit->customer;
        $park     = $deposit->park;
        $contract = $deposit->contract;

        $variables = [
            'customer_name'     => trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')),
            'customer_email'    => $customer->email ?? '',
            'customer_address'  => ($customer->address ?? '') . ', ' . ($customer->zip ?? '') . ' ' . ($customer->city ?? ''),
            'unit_number'       => $contract?->unit?->unit_number ?? '',
            'deposit_amount'    => number_format((float) $deposit->amount, 2, '.', ',') . ' EUR',
            'deduction_amount'  => number_format((float) ($deposit->deduction_amount ?? 0), 2, '.', ',') . ' EUR',
            'deduction_reason'  => $deposit->deduction_reason ?? '',
            'return_amount'     => number_format((float) ($deposit->return_amount ?? $deposit->amount), 2, '.', ',') . ' EUR',
            'return_method'     => $deposit->return_method ?? '',
            'returned_at'       => $deposit->returned_at?->format('d.m.Y') ?? now()->format('d.m.Y'),
            'park_name'         => $park?->name ?? '',
            'park_logo_url'     => $park?->logo_path ? Storage::disk('s3')->url($park->logo_path) : '',
            'park_iban'         => $park?->bank_iban ?? '',
        ];

        $template = $this->findTemplate('deposit_return', $park?->id);
        $html = $template
            ? $this->renderTemplate($template->template_html, $variables)
            : $this->buildDepositReturnHtml($variables);

        $pdfContent = $this->renderPdf($html);
        $path = 'deposits/deposit-return-' . $deposit->id . '-' . time() . '.pdf';
        $this->storeToS3($pdfContent, $path);

        return $path;
    }

    private function buildContractHtml(array $v): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html><head><meta charset="UTF-8"><style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 40px; }
        h1 { color: #333; } table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 8px; border: 1px solid #ddd; }
        </style></head><body>
        <h1>Mietvertrag</h1>
        <p><strong>Mieter:</strong> {$v['customer_name']}</p>
        <p><strong>Adresse:</strong> {$v['customer_address']}</p>
        <p><strong>Einheit:</strong> {$v['unit_number']} ({$v['unit_size_m2']} m²)</p>
        <p><strong>Miete:</strong> {$v['rent_amount']}</p>
        <p><strong>Kaution:</strong> {$v['deposit_amount']}</p>
        <p><strong>Mietbeginn:</strong> {$v['start_date']}</p>
        <p><strong>Kündigungsfrist:</strong> {$v['notice_period_days']} Tage</p>
        <p><strong>Vermieter:</strong> {$v['park_name']}, {$v['park_address']}</p>
        </body></html>
        HTML;
    }

    private function buildInvoiceHtml(array $v): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html><head><meta charset="UTF-8"><style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 40px; }
        h1 { color: #333; } table { width: 100%; border-collapse: collapse; }
        th { background: #eee; } th, td { padding: 6px 8px; border: 1px solid #ddd; }
        .total { font-weight: bold; }
        </style></head><body>
        <h1>Rechnung {$v['invoice_number']}</h1>
        <p><strong>An:</strong> {$v['customer_name']}, {$v['customer_address']}</p>
        <p><strong>Datum:</strong> {$v['issue_date']} | <strong>Fällig:</strong> {$v['due_date']}</p>
        <table>
        <tr><th>Beschreibung</th><th>Typ</th><th>Menge</th><th>Einzelpreis</th><th>Gesamt</th></tr>
        {$v['invoice_items']}
        </table>
        <p>Zwischensumme: {$v['subtotal']} | MwSt ({$v['tax_rate']}): {$v['tax_amount']}</p>
        <p class="total">Gesamtbetrag: {$v['total_amount']}</p>
        <p><strong>Bankverbindung:</strong> IBAN: {$v['park_iban']} | BIC: {$v['park_bic']}</p>
        <p>{$v['park_name']}, {$v['park_address']}</p>
        </body></html>
        HTML;
    }

    private function buildDunningHtml(array $v): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html><head><meta charset="UTF-8"><style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 40px; }
        h1 { color: #c00; }
        </style></head><body>
        <h1>Mahnung (Stufe {$v['dunning_level']})</h1>
        <p><strong>An:</strong> {$v['customer_name']}, {$v['customer_address']}</p>
        <p>Rechnung {$v['invoice_number']} über {$v['invoice_total']} war fällig am {$v['due_date']}.</p>
        <p>Mahngebühr: {$v['fee_amount']}</p>
        <p>Bitte begleichen Sie den Betrag umgehend.</p>
        <p>{$v['park_name']}</p>
        </body></html>
        HTML;
    }

    private function buildDepositReturnHtml(array $v): string
    {
        return <<<HTML
        <!DOCTYPE html>
        <html><head><meta charset="UTF-8"><style>
        body { font-family: Arial, sans-serif; font-size: 12px; margin: 40px; }
        h1 { color: #333; }
        </style></head><body>
        <h1>Kautionsrückgabe</h1>
        <p><strong>Mieter:</strong> {$v['customer_name']}</p>
        <p><strong>Einheit:</strong> {$v['unit_number']}</p>
        <p><strong>Kaution:</strong> {$v['deposit_amount']}</p>
        <p><strong>Abzug:</strong> {$v['deduction_amount']} – {$v['deduction_reason']}</p>
        <p><strong>Rückzahlungsbetrag:</strong> {$v['return_amount']}</p>
        <p><strong>Rückzahlungsmethode:</strong> {$v['return_method']}</p>
        <p><strong>Datum:</strong> {$v['returned_at']}</p>
        <p>{$v['park_name']}</p>
        </body></html>
        HTML;
    }
}
