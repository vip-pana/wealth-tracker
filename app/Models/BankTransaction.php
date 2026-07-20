<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $bank_account_id
 * @property string $external_id
 * @property float $amount
 * @property string $currency
 * @property Carbon $booking_date
 * @property Carbon|null $value_date
 * @property string|null $description
 * @property string|null $counterparty
 * @property string|null $flow_type
 * @property bool $excluded
 * @property bool $is_manual
 * @property string|null $merchant_category_code
 * @property array<string, mixed>|null $raw
 * @property Carbon|null $deleted_at
 * @property-read BankAccount $account
 */
class BankTransaction extends Model
{
    use SoftDeletes;

    public const FLOW_INCOME = 'income';

    public const FLOW_EXPENSE = 'expense';

    public const FLOW_TRANSFER = 'transfer';

    protected $fillable = ['bank_account_id', 'external_id', 'amount', 'currency', 'booking_date', 'value_date', 'description', 'counterparty', 'flow_type', 'excluded', 'is_manual', 'merchant_category_code', 'raw'];

    protected $casts = [
        'amount' => 'float',
        'booking_date' => 'date:Y-m-d',
        'value_date' => 'date:Y-m-d',
        'excluded' => 'boolean',
        'is_manual' => 'boolean',
        'raw' => 'array',
    ];

    /** @return BelongsTo<BankAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }
}
