<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'park_id',
        'unit_type_id',
        'unit_number',
        'floor',
        'building',
        'size_m2',
        'rent_override',
        'deposit_override',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'size_m2' => 'decimal:2',
            'rent_override' => 'decimal:2',
            'deposit_override' => 'decimal:2',
        ];
    }

    public function park(): BelongsTo
    {
        return $this->belongsTo(Park::class);
    }

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(UnitPhoto::class);
    }
}
