<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaitingListNotification extends Model
{
    protected $fillable = [
        'waiting_list_id',
        'unit_id',
        'sent_at',
        'method',
        'response',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function waitingListEntry(): BelongsTo
    {
        return $this->belongsTo(WaitingList::class, 'waiting_list_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
