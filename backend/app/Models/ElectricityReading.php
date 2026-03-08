<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ElectricityReading extends Model
{
    use HasFactory;
    protected $fillable = [
        'meter_id',
        'reading_date',
        'reading_value',
        'photo_path',
        'recorded_by',
        'consumption',
    ];

    protected $casts = [
        'reading_date' => 'date',
        'reading_value' => 'decimal:4',
        'consumption' => 'decimal:4',
    ];

    public function meter(): BelongsTo
    {
        return $this->belongsTo(ElectricityMeter::class, 'meter_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
