<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $category_id
 * @property string $name
 * @property float $value
 * @property string|null $ticker
 * @property string|null $wallet_address
 * @property float|null $quantity
 * @property Carbon $date
 * @property string|null $notes
 * @property Carbon|null $deleted_at
 * @property-read Category $category
 */
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = ['category_id', 'name', 'value', 'ticker', 'wallet_address', 'quantity', 'date', 'notes'];

    protected $casts = [
        'value' => 'float',
        'quantity' => 'float',
        'date' => 'date:Y-m-d',
    ];

    public function currentValue(?float $price = null): float
    {
        if ($this->ticker !== null && $this->quantity !== null && $price !== null) {
            return $this->quantity * $price;
        }

        return $this->value;
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
