<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property string|null $last_sync_status
 * @property string|null $last_sync_error
 * @property Carbon|null $last_sync_at
 * @property-read BankConnection $connection
 * @property-read Collection<int, BankTransaction> $transactions
 */
class BankAccount extends Model
{
    public const SYNC_OK = 'ok';

    public const SYNC_FAILED = 'failed';

    protected $fillable = ['bank_connection_id', 'uid', 'iban', 'name', 'currency', 'linked_name', 'linked_category_id', 'last_sync_status', 'last_sync_error', 'last_sync_at'];

    protected $casts = [
        'last_sync_at' => 'datetime',
    ];

    /** @return BelongsTo<BankConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(BankConnection::class, 'bank_connection_id');
    }

    /** @return HasMany<BankTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class);
    }

    public function isLinked(): bool
    {
        return $this->linked_name !== null && $this->linked_category_id !== null;
    }

    /**
     * Accounts that link a logical asset on a connection that is currently
     * active and not expired. This is the single source of truth for
     * "an asset is managed by the bank".
     *
     * @param  Builder<BankAccount>  $query
     * @return Builder<BankAccount>
     */
    public function scopeActiveLinks(Builder $query): Builder
    {
        return $query
            ->whereNotNull('linked_name')
            ->whereNotNull('linked_category_id')
            ->whereHas('connection', fn (Builder $q) => $q
                ->where('status', BankConnection::STATUS_ACTIVE)
                ->where(fn (Builder $q2) => $q2->whereNull('valid_until')->orWhere('valid_until', '>', Carbon::now()))
            );
    }

    /**
     * Keys "name|category_id" of every logical asset currently managed by an
     * active, non-expired bank link. Used to lock those assets' identity.
     *
     * @return list<string>
     */
    public static function activeLinkKeys(): array
    {
        $keys = [];

        foreach (self::query()->activeLinks()->get() as $account) {
            $keys[] = $account->linked_name.'|'.$account->linked_category_id;
        }

        return $keys;
    }

    /**
     * The asset row for the linked logical asset (name + category) in the
     * current month, creating it if it doesn't exist yet. Null if unlinked.
     * This is how a bank balance follows the asset across monthly rows.
     *
     * Resolves trashed rows too: if the user deleted this month's row, the
     * next sync restores it in place instead of spawning a duplicate beside
     * the soft-deleted one (which would later collide on restore).
     */
    public function currentMonthAsset(): ?Asset
    {
        if (! $this->isLinked()) {
            return null;
        }

        $asset = Asset::withTrashed()->firstOrNew([
            'name' => $this->linked_name,
            'category_id' => $this->linked_category_id,
            'date' => Carbon::now()->format('Y-m-01'),
        ]);

        if (! $asset->exists) {
            $asset->value = 0.0;
        }

        $asset->deleted_at = null;
        $asset->save();

        return $asset;
    }

    public function recordSyncSuccess(): void
    {
        $this->update([
            'last_sync_status' => self::SYNC_OK,
            'last_sync_at' => Carbon::now(),
            'last_sync_error' => null,
        ]);
    }

    public function recordSyncFailure(string $error): void
    {
        // Leaves the asset's last good value untouched; only records the attempt.
        $this->update([
            'last_sync_status' => self::SYNC_FAILED,
            'last_sync_at' => Carbon::now(),
            'last_sync_error' => $error,
        ]);
    }
}
