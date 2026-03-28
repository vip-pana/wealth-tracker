<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $goal_id
 * @property int|null $category_id
 * @property string|null $macro_category
 * @property float $percentage
 */
class GoalCategoryAllocation extends Model
{
    protected $fillable = ['goal_id', 'category_id', 'macro_category', 'percentage'];

    protected $casts = [
        'percentage' => 'float',
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
