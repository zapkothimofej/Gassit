<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DunningRecord extends Model
{
    protected $fillable = [
        'invoice_id',
        'customer_id',
        'level',
        'sent_at',
        'fee_amount',
        'template_used',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'fee_amount' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
