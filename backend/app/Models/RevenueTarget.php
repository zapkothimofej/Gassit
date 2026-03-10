<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueTarget extends Model
{
    protected $fillable = ['park_id', 'year', 'month', 'target_amount'];

    protected $casts = ['target_amount' => 'decimal:2'];

    public function park(): BelongsTo { return $this->belongsTo(Park::class); }
}
