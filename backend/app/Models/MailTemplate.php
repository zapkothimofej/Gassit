<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailTemplate extends Model
{
    use HasFactory;
    protected $fillable = [
        'park_id',
        'name',
        'subject',
        'body_html',
        'template_type',
        'variables_json',
        'active',
    ];

    protected $casts = [
        'variables_json' => 'array',
        'active' => 'boolean',
    ];

    public function park(): BelongsTo
    {
        return $this->belongsTo(Park::class);
    }

    public function sentEmails(): HasMany
    {
        return $this->hasMany(SentEmail::class, 'template_id');
    }
}
