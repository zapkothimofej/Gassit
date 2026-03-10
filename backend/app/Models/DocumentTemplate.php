<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplate extends Model
{
    protected $fillable = [
        'park_id',
        'name',
        'document_type',
        'template_html',
        'variables_json',
        'version',
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
}
