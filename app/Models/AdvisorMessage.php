<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $session_id
 * @property string $role
 * @property string $content
 * @property string $status
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AdvisorSession $session
 */
class AdvisorMessage extends Model
{
    public const ROLE_ASSISTANT = 'assistant';

    public const ROLE_USER = 'user';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['session_id', 'role', 'content', 'status', 'error'];

    /** @return BelongsTo<AdvisorSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AdvisorSession::class, 'session_id');
    }
}
