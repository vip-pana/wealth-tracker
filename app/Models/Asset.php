<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property float $value
 * @property Carbon $date
 * @property string|null $notes
 * @property-read Category $category
 */
class Asset extends Model
{
    protected $fillable = ['category_id', 'name', 'value', 'date', 'notes'];

    protected $casts = [
        'value' => 'float',
        'date' => 'date:Y-m-d',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
