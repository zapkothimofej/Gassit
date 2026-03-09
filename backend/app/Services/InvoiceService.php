<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Park;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function generateInvoiceNumber(Park $park): string
    {
        return DB::transaction(function () use ($park) {
            $code = strtoupper(preg_replace('/[^A-Za-z]/', '', $park->name));
            $code = substr($code, 0, 4) ?: 'PARK';
            $year = now()->year;

            $prefix = $code . '-' . $year . '-';

            $last = Invoice::where('invoice_number', 'like', $prefix . '%')
                ->lockForUpdate()
                ->orderByDesc('invoice_number')
                ->value('invoice_number');

            $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;

            return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
        });
    }
}
