<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $aspsp_name
 * @property string $aspsp_country
 * @property string $state
 * @property string|null $session_id
 * @property Carbon|null $valid_until
 * @property string $status
 * @property-read Collection<int, BankAccount> $accounts
 */
class BankConnection extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = ['aspsp_name', 'aspsp_country', 'state', 'session_id', 'valid_until', 'status'];

    protected $casts = [
        'valid_until' => 'datetime',
    ];

    /** @return HasMany<BankAccount, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && ($this->valid_until === null || $this->valid_until->isFuture());
    }
}
