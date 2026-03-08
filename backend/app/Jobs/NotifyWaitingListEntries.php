<?php

namespace App\Jobs;

use App\Models\MailTemplate;
use App\Models\SentEmail;
use App\Models\Unit;
use App\Models\WaitingList;
use App\Models\WaitingListNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyWaitingListEntries implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $unitId
    ) {}

    public function handle(): void
    {
        $unit = Unit::find($this->unitId);
        if (!$unit || $unit->status !== 'free') {
            return;
        }

        $entries = WaitingList::with('customer')
            ->where('park_id', $unit->park_id)
            ->where('unit_type_id', $unit->unit_type_id)
            ->whereNull('converted_application_id')
            ->whereNull('deleted_at')
            ->orderByDesc('priority_score')
            ->orderBy('created_at')
            ->get();

        foreach ($entries as $entry) {
            WaitingListNotification::create([
                'waiting_list_id' => $entry->id,
                'unit_id'         => $unit->id,
                'sent_at'         => now(),
                'method'          => 'email',
                'response'        => 'no_response',
            ]);

            $entry->update(['notified_at' => now()]);

            $template = MailTemplate::where('template_type', 'unit_available')
                ->where(function ($q) use ($unit) {
                    $q->where('park_id', $unit->park_id)->orWhereNull('park_id');
                })
                ->where('active', true)
                ->first();

            SentEmail::create([
                'customer_id'     => $entry->customer_id,
                'template_id'     => $template?->id,
                'sent_by'         => null,
                'subject'         => $template?->subject ?? 'Unit Available',
                'body_html'       => $template?->body_html ?? '<p>A unit matching your preferences is now available.</p>',
                'recipient_email' => $entry->customer->email ?? '',
                'status'          => 'queued',
            ]);
        }
    }
}
