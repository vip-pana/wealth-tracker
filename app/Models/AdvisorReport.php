<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $status
 * @property string|null $content
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AdvisorReport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['status', 'content', 'error'];

    /**
     * The single current report (latest row), or null if none generated yet.
     */
    public static function current(): ?self
    {
        return self::query()->latest('id')->first();
    }
}
