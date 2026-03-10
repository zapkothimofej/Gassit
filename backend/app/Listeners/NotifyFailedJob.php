<?php

namespace App\Listeners;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Log;

class NotifyFailedJob
{
    public function handle(JobFailed $event): void
    {
        Log::error('Queue job failed', [
            'job'        => get_class($event->job),
            'connection' => $event->connectionName,
            'exception'  => $event->exception->getMessage(),
            'trace'      => $event->exception->getTraceAsString(),
        ]);
    }
}
