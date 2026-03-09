<?php

namespace App\Jobs;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyInvoices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $billingMonth = '', // Y-m format; defaults to current month
    ) {}

    public function handle(): void
    {
        $month = $this->billingMonth ?: now()->format('Y-m');

        $contracts = Contract::where('status', 'active')->pluck('id');

        Log::info("GenerateMonthlyInvoices: dispatching GenerateInvoiceJob for {$contracts->count()} contracts (month: {$month}).");

        foreach ($contracts as $contractId) {
            GenerateInvoiceJob::dispatch($contractId, $month);
        }
    }
}
