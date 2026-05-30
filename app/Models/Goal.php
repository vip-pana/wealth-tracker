<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property float $target_value
 * @property Carbon|null $target_date
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, GoalCategoryAllocation> $categoryAllocations
 * @property-read Collection<int, GoalMilestone> $milestones
 */
class Goal extends Model
{
    use SoftDeletes;

    protected $table = 'goal';

    protected $fillable = ['name', 'description', 'target_value', 'target_date'];

    protected $casts = [
        'target_value' => 'float',
        'target_date' => 'date:Y-m-d',
    ];

    /** @return HasMany<GoalCategoryAllocation, $this> */
    public function categoryAllocations(): HasMany
    {
        return $this->hasMany(GoalCategoryAllocation::class);
    }

    /** @return HasMany<GoalMilestone, $this> */
    public function milestones(): HasMany
    {
        return $this->hasMany(GoalMilestone::class);
    }
}
