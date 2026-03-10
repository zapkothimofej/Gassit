<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmAccessCode extends Model
{
    protected $fillable = ['park_id', 'code', 'description', 'valid_from', 'valid_to', 'active'];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'active' => 'boolean',
    ];

    public function park(): BelongsTo { return $this->belongsTo(Park::class); }
}
