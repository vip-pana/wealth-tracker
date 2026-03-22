<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SnapshotCategoryValue extends Model
{
    public $timestamps = false;

    protected $fillable = ['snapshot_id', 'category_id', 'value'];

    protected $casts = [
        'value' => 'float',
    ];

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(MonthlySnapshot::class, 'snapshot_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
