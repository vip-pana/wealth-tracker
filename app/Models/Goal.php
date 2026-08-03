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

    /**
     * The investment horizon, DERIVED from the target date rather than stored:
     * the goal's date is the single source of truth for "how far away am I
     * aiming". A hand-set profile field beside it could contradict it (horizon
     * "breve" next to a 2040 target), which put two conflicting statements in
     * the same advisor prompt. Null when the goal carries no target date.
     *
     * Buckets match the labels the UI and the advisor use: < 3 years short,
     * 3-10 medium, 10+ long.
     */
    public function horizon(): ?string
    {
        if ($this->target_date === null) {
            return null;
        }

        $years = Carbon::today()->diffInYears($this->target_date, absolute: false);

        return match (true) {
            $years < 3 => 'short',
            $years < 10 => 'medium',
            default => 'long',
        };
    }

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
            // Percentages are resolved against the milestone's OWN target value
            // (the level the allocation describes), so a cap of "50k" clamps the
            // share at that level, not at the user's current net worth.
            return $this->applyAllocationCaps($next->categoryAllocations->values(), $next->target_value);
        }

        return $this->categoryAllocations->whereNull('milestone_id')->values();
    }

    /**
     * Apply each row's absolute `cap_amount` to the allocation, adjusting the
     * in-memory `percentage` of the returned instances (never persisted — every
     * caller only reads them). When a row's `pct × targetValue` exceeds its cap,
     * the row is clamped to `cap/targetValue` and the freed percentage is spread
     * over the UNCAPPED rows in proportion to their weights. Several caps can
     * bind at once; if every row is capped, the excess is left unallocated (the
     * percentages sum to under 100) rather than silently forced back to 100.
     *
     * Mirrors applyAllocationCaps() in resources/js/lib/goalMath.ts — keep in step.
     *
     * @param  SupportCollection<int, GoalCategoryAllocation>  $rows
     * @return SupportCollection<int, GoalCategoryAllocation>
     */
    private function applyAllocationCaps(SupportCollection $rows, float $targetValue): SupportCollection
    {
        if ($targetValue <= 0.0) {
            return $rows;
        }

        $binds = static function (GoalCategoryAllocation $a) use ($targetValue): ?float {
            if ($a->cap_amount === null || $a->cap_amount < 0.0) {
                return null;
            }
            $boundPct = ($a->cap_amount / $targetValue) * 100.0;

            return $boundPct < $a->percentage ? $boundPct : null;
        };

        $freed = 0.0;
        $uncappedTotal = 0.0;
        foreach ($rows as $a) {
            $boundPct = $binds($a);
            if ($boundPct !== null) {
                $freed += $a->percentage - $boundPct;
            } else {
                $uncappedTotal += $a->percentage;
            }
        }

        if ($freed <= 0.0) {
            return $rows;
        }

        foreach ($rows as $a) {
            $boundPct = $binds($a);
            if ($boundPct !== null) {
                $a->percentage = $boundPct;
            } elseif ($uncappedTotal > 0.0) {
                $a->percentage += ($a->percentage / $uncappedTotal) * $freed;
            }
        }

        return $rows;
    }
}
