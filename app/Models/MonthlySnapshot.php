<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MonthlySnapshot extends Model
{
    protected $fillable = ['date', 'total_value'];

    protected $casts = [
        'total_value' => 'float',
        'date' => 'date:Y-m-d',
    ];

    public function categoryValues(): HasMany
    {
        return $this->hasMany(SnapshotCategoryValue::class, 'snapshot_id');
    }
}
