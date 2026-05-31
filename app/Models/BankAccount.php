<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $bank_connection_id
 * @property string $uid
 * @property string|null $iban
 * @property string|null $name
 * @property string|null $currency
 * @property int|null $asset_id
 * @property-read BankConnection $connection
 * @property-read Asset|null $asset
 */
class BankAccount extends Model
{
    protected $fillable = ['bank_connection_id', 'uid', 'iban', 'name', 'currency', 'asset_id'];

    /** @return BelongsTo<BankConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class, 'bank_connection_id');
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
