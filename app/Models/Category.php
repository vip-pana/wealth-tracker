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
 * @property string|null $icon
 * @property int $sort_order
 * @property MacroCategory|null $macro_category
 * @property int|null $assets_count
 * @property Carbon|null $deleted_at
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['name', 'color', 'icon', 'sort_order', 'macro_category'];

    protected $casts = [
        'sort_order' => 'integer',
        'macro_category' => MacroCategory::class,
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
