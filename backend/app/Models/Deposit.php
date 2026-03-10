<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deposit extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id', 'customer_id', 'park_id', 'amount', 'status',
        'received_at', 'returned_at', 'return_amount', 'deduction_amount',
        'deduction_reason', 'return_method', 'mollie_payment_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'return_amount' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'received_at' => 'datetime',
        'returned_at' => 'datetime',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function park(): BelongsTo
    {
        return $this->belongsTo(Park::class);
    }
}
