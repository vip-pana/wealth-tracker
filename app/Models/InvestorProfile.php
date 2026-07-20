<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $name
 * @property Carbon|null $birth_date
 * @property string|null $horizon
 * @property string|null $risk_tolerance
 * @property int $emergency_fund_months
 * @property string|null $notes
 * @property string|null $memory
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class InvestorProfile extends Model
{
    protected $table = 'investor_profile';

    protected $fillable = ['name', 'birth_date', 'horizon', 'risk_tolerance', 'emergency_fund_months', 'notes', 'memory'];

    /** @var array<string, string> */
    protected $casts = [
        'birth_date' => 'date',
        'emergency_fund_months' => 'integer',
    ];
}
