<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

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

    /**
     * The target allocation that applies RIGHT NOW: the allocation of the next
     * milestone not yet reached (the first, by amount, whose target exceeds the
     * current net worth), which is the glide-path step the user is currently
     * aiming for. Falls back to the last milestone's allocation once every
     * milestone is reached, and to the goal's global allocation (milestone_id
     * null) when no milestone carries one — so pre-glide-path goals still work.
     *
     * Expects `milestones.categoryAllocations` and `categoryAllocations` loaded.
     *
     * @return SupportCollection<int, GoalCategoryAllocation>
     */
    public function currentTargetAllocation(float $currentNetWorth): SupportCollection
    {
        $ordered = $this->milestones->sortBy('target_value')->values();

        $next = $ordered->first(fn (GoalMilestone $m): bool => $m->target_value > $currentNetWorth)
            ?? $ordered->last();

        if ($next instanceof GoalMilestone && $next->categoryAllocations->isNotEmpty()) {
            return $next->categoryAllocations->values();
        }

        return $this->categoryAllocations->whereNull('milestone_id')->values();
    }
}
