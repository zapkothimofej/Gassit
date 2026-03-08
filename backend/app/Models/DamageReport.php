<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DamageReport extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'unit_id',
        'contract_id',
        'reported_by',
        'description',
        'estimated_cost',
        'actual_cost',
        'status',
        'assigned_vendor_id',
        'resolved_at',
    ];

    protected $casts = [
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
        'resolved_at' => 'datetime',
    ];

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignedVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'assigned_vendor_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(DamagePhoto::class);
    }

    public function vendorInvoices(): HasMany
    {
        return $this->hasMany(VendorInvoice::class);
    }
}
