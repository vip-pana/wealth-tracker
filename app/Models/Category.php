<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MacroCategory;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $color
 * @property int $sort_order
 * @property MacroCategory|null $macro_category
 * @property bool $investable
 * @property int|null $assets_count
 * @property Carbon|null $deleted_at
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['name', 'color', 'sort_order', 'macro_category', 'investable'];

    protected $casts = [
        'sort_order' => 'integer',
        'macro_category' => MacroCategory::class,
        'investable' => 'boolean',
    ];

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /** @return HasMany<SnapshotCategoryValue, $this> */
    public function snapshotValues(): HasMany
    {
        return $this->hasMany(SnapshotCategoryValue::class);
    }
}
