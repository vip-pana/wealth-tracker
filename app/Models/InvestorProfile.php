<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $horizon
 * @property string|null $risk_tolerance
 * @property float|null $income_monthly
 * @property string|null $emergency_fund
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class InvestorProfile extends Model
{
    protected $table = 'investor_profile';

    protected $fillable = ['horizon', 'risk_tolerance', 'income_monthly', 'emergency_fund', 'notes'];

    /** @var array<string, string> */
    protected $casts = [
        'income_monthly' => 'float',
    ];
}
