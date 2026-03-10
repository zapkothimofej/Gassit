<?php

use App\Console\Commands\GenerateMonthlyInvoicesCommand;
use App\Console\Commands\ProcessDunning;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(ProcessDunning::class)->daily();
Schedule::command(GenerateMonthlyInvoicesCommand::class)->monthlyOn(1, '06:00');
