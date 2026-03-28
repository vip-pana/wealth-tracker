<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $goal_id
 * @property string|null $notes
 * @property float $target_value
 * @property Carbon $target_date
 */
class GoalMilestone extends Model
{
    protected $fillable = ['goal_id', 'notes', 'target_value', 'target_date'];

    protected $casts = [
        'target_value' => 'float',
        'target_date' => 'date:Y-m-d',
    ];

    /** @return BelongsTo<Goal, $this> */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }
}
