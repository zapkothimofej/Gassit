<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferenceItem extends Model
{
    protected $fillable = ['category', 'value', 'label', 'sort_order', 'active'];

    protected $casts = ['active' => 'boolean'];
}
