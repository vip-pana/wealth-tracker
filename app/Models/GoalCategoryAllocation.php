<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $goal_id
 * @property int|null $milestone_id
 * @property int|null $category_id
 * @property string|null $macro_category
 * @property float $percentage
 * @property float|null $cap_amount
 * @property Carbon|null $deleted_at
 */
class GoalCategoryAllocation extends Model
{
    use SoftDeletes;

    protected $fillable = ['goal_id', 'milestone_id', 'category_id', 'macro_category', 'percentage', 'cap_amount'];

    protected $casts = [
        'percentage' => 'float',
        'cap_amount' => 'float',
    ];

    /** @return BelongsTo<Goal, $this> */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
