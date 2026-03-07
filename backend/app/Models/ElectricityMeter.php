<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ElectricityMeter extends Model
{
    protected $fillable = [
        'unit_id',
        'meter_number',
        'meter_type',
        'active',
        'installed_at',
    ];

    protected $casts = [
        'active' => 'boolean',
        'installed_at' => 'date',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(ElectricityReading::class, 'meter_id');
    }
}
