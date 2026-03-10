<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Scout\Searchable;

class Contract extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'park_id' => $this->unit?->park_id,
        ];
    }

    protected $fillable = [
        'application_id', 'customer_id', 'unit_id', 'start_date', 'end_date',
        'notice_period_days', 'rent_amount', 'deposit_amount', 'insurance_amount',
        'status', 'signed_pdf_path', 'signed_at', 'terminated_at',
        'termination_reason_id', 'termination_notice_date', 'final_invoice_waived',
    ];

    protected $casts = [
        'start_date'              => 'date',
        'end_date'                => 'date',
        'signed_at'               => 'datetime',
        'terminated_at'           => 'datetime',
        'termination_notice_date' => 'date',
        'rent_amount'             => 'decimal:2',
        'deposit_amount'          => 'decimal:2',
        'insurance_amount'        => 'decimal:2',
        'final_invoice_waived'    => 'boolean',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(ContractSignature::class);
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(ContractRenewal::class);
    }
}
