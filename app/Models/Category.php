<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $color
 * @property string|null $icon
 * @property int $sort_order
 */
class Category extends Model
{
    protected $fillable = ['name', 'color', 'icon', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function snapshotValues(): HasMany
    {
        return $this->hasMany(SnapshotCategoryValue::class);
    }
}
