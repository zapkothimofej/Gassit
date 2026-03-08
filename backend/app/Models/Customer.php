<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type',
        'first_name',
        'last_name',
        'company_name',
        'dob',
        'address',
        'city',
        'zip',
        'country',
        'email',
        'phone',
        'id_number',
        'tax_id',
        'status',
        'gdpr_consent_at',
        'gdpr_deleted_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
            'gdpr_consent_at' => 'datetime',
            'gdpr_deleted_at' => 'datetime',
        ];
    }

    public function blacklistEntries(): HasMany
    {
        return $this->hasMany(Blacklist::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class);
    }
}
