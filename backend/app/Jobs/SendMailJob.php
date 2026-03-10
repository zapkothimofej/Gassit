<?php

namespace App\Jobs;

use App\Models\SentEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendMailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $sentEmailId) {}

    public function handle(): void
    {
        $sentEmail = SentEmail::find($this->sentEmailId);

        if (!$sentEmail || $sentEmail->status === 'sent') {
            return;
        }

        // In production: use Laravel Mail to send $sentEmail->body_html to $sentEmail->recipient_email
        // For now, mark as sent (stub implementation)
        $sentEmail->update([
            'status'  => 'sent',
            'sent_at' => now(),
        ]);
    }
}
