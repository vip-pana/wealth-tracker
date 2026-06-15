<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $kind
 * @property string|null $title
 * @property string $status
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AdvisorMessage> $messages
 */
class AdvisorSession extends Model
{
    public const KIND_REPORT = 'report';

    public const KIND_CHAT = 'chat';

    public const STATUS_PENDING = 'pending';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['kind', 'title', 'status', 'error'];

    /** @return HasMany<AdvisorMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AdvisorMessage::class, 'session_id')->orderBy('id');
    }
}
