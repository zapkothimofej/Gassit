<?php

namespace App\Models;

use App\Casts\EncryptedString;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class Customer extends Model
{
    use HasFactory, SoftDeletes, Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'full_name' => trim("{$this->first_name} {$this->last_name}"),
            'email' => $this->email,
            'status' => $this->status,
        ];
    }

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
        'email_hash',
        'phone',
        'id_number',
        'tax_id',
        'status',
        'gdpr_consent_at',
        'gdpr_deleted_at',
        'notes',
        'dunning_paused_until',
    ];

    protected function casts(): array
    {
        return [
            'first_name'           => EncryptedString::class,
            'last_name'            => EncryptedString::class,
            'email'                => EncryptedString::class,
            'phone'                => EncryptedString::class,
            'dob'                  => EncryptedString::class,
            'address'              => EncryptedString::class,
            'id_number'            => EncryptedString::class,
            'dunning_paused_until' => 'datetime',
            'gdpr_consent_at'      => 'datetime',
            'gdpr_deleted_at'      => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Customer $customer) {
            if ($customer->isDirty('email') && $customer->getAttributes()['email'] !== null && $customer->gdpr_deleted_at === null) {
                $rawEmail = $customer->email;
                $customer->email_hash = hash_hmac('sha256', strtolower($rawEmail), config('app.key'));
            }
        });
    }

    public function blacklistEntries(): HasMany
    {
        return $this->hasMany(Blacklist::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
