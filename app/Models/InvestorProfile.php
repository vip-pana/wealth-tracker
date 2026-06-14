<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $horizon
 * @property string|null $risk_tolerance
 * @property string|null $objective
 * @property string|null $target_allocation
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class InvestorProfile extends Model
{
    protected $table = 'investor_profile';

    protected $fillable = ['horizon', 'risk_tolerance', 'objective', 'target_allocation'];
}
