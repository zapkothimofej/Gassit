<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitFeature extends Model
{
    protected $fillable = [
        'unit_type_id',
        'feature',
    ];

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }
}
