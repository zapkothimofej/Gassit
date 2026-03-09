<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class Application extends Model
{
    use HasFactory, Searchable;

    public function toSearchableArray(): array
    {
        $customer = $this->customer;
        return [
            'id' => $this->id,
            'status' => $this->status,
            'park_id' => $this->park_id,
            'customer_name' => $customer
                ? trim("{$customer->first_name} {$customer->last_name}")
                : null,
        ];
    }
    protected $fillable = [
        'park_id',
        'customer_id',
        'unit_type_id',
        'unit_id',
        'desired_start_date',
        'status',
        'assigned_to',
        'credit_check_path',
        'notes',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'desired_start_date' => 'date',
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

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
