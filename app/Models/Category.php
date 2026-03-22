<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $color
 * @property string|null $icon
 * @property int $sort_order
 * @property int|null $assets_count
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $fillable = ['name', 'color', 'icon', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
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
