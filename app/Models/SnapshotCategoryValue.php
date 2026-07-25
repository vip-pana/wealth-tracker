<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $snapshot_id
 * @property int $category_id
 * @property float $value
 * @property Carbon|null $deleted_at
 * @property-read Snapshot $snapshot
 * @property-read Category $category
 */
class SnapshotCategoryValue extends Model
{
    use SoftDeletes;

    public $timestamps = false;

    protected $fillable = ['snapshot_id', 'category_id', 'value'];

    protected $casts = [
        'value' => 'float',
    ];

    /** @return BelongsTo<Snapshot, $this> */
    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(Snapshot::class, 'snapshot_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
