<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountRule extends Model
{
    protected $fillable = [
        'park_id', 'unit_type_id', 'name', 'discount_type',
        'discount_value', 'applies_from_month', 'applies_to_month', 'active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function park(): BelongsTo { return $this->belongsTo(Park::class); }
    public function unitType(): BelongsTo { return $this->belongsTo(UnitType::class); }
}
