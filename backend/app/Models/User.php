<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'active',
        'login_attempts',
        'two_factor_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'totp_secret',
    ];

    public function parks(): BelongsToMany
    {
        return $this->belongsToMany(Park::class);
    }

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'active'             => 'boolean',
            'two_factor_enabled' => 'boolean',
            'totp_secret'        => 'encrypted',
        ];
    }
}
