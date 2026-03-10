<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractRenewal extends Model
{
    protected $fillable = [
        'contract_id', 'new_contract_id', 'renewed_at', 'new_rent_amount',
    ];

    protected $casts = [
        'renewed_at' => 'datetime',
        'new_rent_amount' => 'decimal:2',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function newContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'new_contract_id');
    }
}
