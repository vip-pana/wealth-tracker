<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $goal_id
 * @property string|null $notes
 * @property string|null $action
 * @property string|null $rationale
 * @property float $target_value
 * @property Carbon $target_date
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, GoalCategoryAllocation> $categoryAllocations
 */
class GoalMilestone extends Model
{
    use SoftDeletes;

    protected $fillable = ['goal_id', 'notes', 'action', 'rationale', 'target_value', 'target_date'];

    protected $casts = [
        'target_value' => 'float',
        'target_date' => 'date:Y-m-d',
    ];

    /** @return BelongsTo<Goal, $this> */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /** @return HasMany<GoalCategoryAllocation, $this> */
    public function categoryAllocations(): HasMany
    {
        return $this->hasMany(GoalCategoryAllocation::class, 'milestone_id');
    }
}
