<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMonthlyInvoices;
use Illuminate\Console\Command;

class GenerateMonthlyInvoicesCommand extends Command
{
    protected $signature = 'invoices:generate-monthly {--month= : Billing month in Y-m format (defaults to current month)}';
    protected $description = 'Dispatch monthly invoice generation job for all active contracts';

    public function handle(): int
    {
        $month = $this->option('month') ?: now()->format('Y-m');

        $this->info("Dispatching invoice generation for month: {$month}");

        GenerateMonthlyInvoices::dispatch($month);

        $this->info('GenerateMonthlyInvoices job dispatched successfully.');

        return self::SUCCESS;
    }
}
