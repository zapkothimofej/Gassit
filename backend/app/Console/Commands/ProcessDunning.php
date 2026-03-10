<?php

namespace App\Console\Commands;

use App\Services\DunningService;
use Illuminate\Console\Command;

class ProcessDunning extends Command
{
    protected $signature = 'dunning:process';
    protected $description = 'Find overdue invoices and escalate dunning levels';

    public function handle(DunningService $service): int
    {
        $service->processDunning();

        return Command::SUCCESS;
    }
}
