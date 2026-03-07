<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnitType extends Model
{
    protected $fillable = [
        'park_id',
        'name',
        'description',
        'base_rent',
        'deposit_amount',
        'size_m2',
        'floor_plan_path',
    ];

    protected function casts(): array
    {
        return [
            'base_rent' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'size_m2' => 'decimal:2',
        ];
    }

    public function park(): BelongsTo
    {
        return $this->belongsTo(Park::class);
    }

    public function features(): HasMany
    {
        return $this->hasMany(UnitFeature::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }
}
