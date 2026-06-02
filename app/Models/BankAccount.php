<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bank_connection_id
 * @property string $uid
 * @property string|null $iban
 * @property string|null $name
 * @property string|null $currency
 * @property string|null $linked_name
 * @property int|null $linked_category_id
 * @property-read BankConnection $connection
 */
class BankAccount extends Model
{
    protected $fillable = ['bank_connection_id', 'uid', 'iban', 'name', 'currency', 'linked_name', 'linked_category_id'];

    /** @return BelongsTo<BankConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class, 'bank_connection_id');
    }

    public function isLinked(): bool
    {
        return $this->linked_name !== null && $this->linked_category_id !== null;
    }

    /**
     * The asset row for the linked logical asset (name + category) in the
     * current month, creating it if it doesn't exist yet. Null if unlinked.
     * This is how a bank balance follows the asset across monthly rows.
     */
    public function currentMonthAsset(): ?Asset
    {
        if (! $this->isLinked()) {
            return null;
        }

        return Asset::firstOrCreate(
            [
                'name' => $this->linked_name,
                'category_id' => $this->linked_category_id,
                'date' => Carbon::now()->format('Y-m-01'),
            ],
            ['value' => 0.0],
        );
    }
}
