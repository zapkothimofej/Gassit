<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceOption extends Model
{
    protected $fillable = [
        'park_id', 'unit_type_id', 'name', 'provider',
        'monthly_premium', 'coverage_amount', 'active',
    ];

    protected $casts = [
        'monthly_premium' => 'decimal:2',
        'coverage_amount' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function park(): BelongsTo { return $this->belongsTo(Park::class); }
    public function unitType(): BelongsTo { return $this->belongsTo(UnitType::class); }
}
