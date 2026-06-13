<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $asset_id
 * @property string $type
 * @property float $shares
 * @property float $price_per_share
 * @property float|null $fees
 * @property Carbon $date
 * @property string|null $external_id
 * @property string|null $notes
 * @property Carbon|null $deleted_at
 * @property-read Asset $asset
 */
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    use SoftDeletes;

    public const TYPE_BUY = 'buy';

    public const TYPE_SELL = 'sell';

    protected $fillable = ['asset_id', 'type', 'shares', 'price_per_share', 'fees', 'date', 'external_id', 'notes'];

    protected $casts = [
        'shares' => 'float',
        'price_per_share' => 'float',
        'fees' => 'float',
        'date' => 'date:Y-m-d',
    ];

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
