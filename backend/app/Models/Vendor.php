<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'park_id',
        'name',
        'contact_name',
        'phone',
        'email',
        'specialty',
        'hourly_rate',
        'active',
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function park(): BelongsTo
    {
        return $this->belongsTo(Park::class);
    }

    public function damageReports(): HasMany
    {
        return $this->hasMany(DamageReport::class, 'assigned_vendor_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(VendorInvoice::class);
    }
}
