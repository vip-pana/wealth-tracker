<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $snapshot_id
 * @property int $category_id
 * @property float $value
 * @property-read MonthlySnapshot $snapshot
 * @property-read Category $category
 */
class SnapshotCategoryValue extends Model
{
    public $timestamps = false;

    protected $fillable = ['snapshot_id', 'category_id', 'value'];

    protected $casts = [
        'value' => 'float',
    ];

    /** @return BelongsTo<MonthlySnapshot, $this> */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(MonthlySnapshot::class, 'snapshot_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
