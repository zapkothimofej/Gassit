<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WaitingList extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'waiting_list';

    protected $fillable = [
        'park_id',
        'customer_id',
        'unit_type_id',
        'priority_score',
        'notes',
        'notified_at',
        'converted_application_id',
    ];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
        ];
    }

    public function park(): BelongsTo
    {
        return $this->belongsTo(Park::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function unitType(): BelongsTo
    {
        return $this->belongsTo(UnitType::class);
    }

    public function convertedApplication(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'converted_application_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(WaitingListNotification::class);
    }
}
