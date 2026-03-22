<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ticker
 * @property float $price
 * @property string $currency
 * @property Carbon $fetched_at
 */
class AssetPrice extends Model
{
    protected $fillable = ['ticker', 'price', 'currency', 'fetched_at'];

    protected $casts = [
        'price' => 'float',
        'fetched_at' => 'datetime',
    ];

    public static function latestFor(string $ticker): ?self
    {
        return self::where('ticker', $ticker)->first();
    }
}
