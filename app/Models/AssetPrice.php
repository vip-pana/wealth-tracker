<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $ticker
 * @property float|null $price
 * @property string $currency
 * @property Carbon|null $fetched_at
 * @property string|null $last_status
 * @property Carbon|null $last_attempt_at
 * @property string|null $last_error
 */
class AssetPrice extends Model
{
    public const STATUS_OK = 'ok';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['ticker', 'price', 'currency', 'fetched_at', 'last_status', 'last_attempt_at', 'last_error'];

    protected $casts = [
        'price' => 'float',
        'fetched_at' => 'datetime',
        'last_attempt_at' => 'datetime',
    ];

    public static function latestFor(string $ticker): ?self
    {
        return self::where('ticker', $ticker)->first();
    }

    public static function recordSuccess(string $ticker, float $price, string $currency): void
    {
        self::updateOrCreate(
            ['ticker' => $ticker],
            [
                'price' => $price,
                'currency' => $currency,
                'fetched_at' => Carbon::now(),
                'last_status' => self::STATUS_OK,
                'last_attempt_at' => Carbon::now(),
                'last_error' => null,
            ],
        );
    }

    public static function recordFailure(string $ticker, string $error): void
    {
        // Leaves any previously-fetched price untouched; only updates the
        // attempt status, creating the row if this ticker never succeeded.
        self::updateOrCreate(
            ['ticker' => $ticker],
            [
                'last_status' => self::STATUS_FAILED,
                'last_attempt_at' => Carbon::now(),
                'last_error' => $error,
            ],
        );
    }
}
