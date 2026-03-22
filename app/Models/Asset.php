<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asset extends Model
{
    protected $fillable = ['category_id', 'name', 'value', 'date', 'notes'];

    protected $casts = [
        'value' => 'float',
        'date'  => 'date:Y-m-d',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
