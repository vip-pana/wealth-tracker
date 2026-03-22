<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $date
 * @property float $total_value
 * @property-read Collection<int, SnapshotCategoryValue> $categoryValues
 */
class MonthlySnapshot extends Model
{
    protected $fillable = ['date', 'total_value'];

    protected $casts = [
        'total_value' => 'float',
        'date' => 'date:Y-m-d',
    ];

    /** @return HasMany<SnapshotCategoryValue, $this> */
    public function categoryValues(): HasMany
    {
        return $this->hasMany(SnapshotCategoryValue::class, 'snapshot_id');
    }
}
