<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectricityPricing extends Model
{
    protected $fillable = [
        'park_id',
        'price_per_kwh',
        'valid_from',
        'valid_to',
    ];

    protected $casts = [
        'price_per_kwh' => 'decimal:6',
        'valid_from' => 'date',
        'valid_to' => 'date',
    ];

    public function park(): BelongsTo
    {
        return $this->belongsTo(Park::class);
    }
}
