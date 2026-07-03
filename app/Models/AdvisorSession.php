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
 * @property Carbon|null $last_read_at
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

    protected $fillable = ['kind', 'title', 'status', 'error', 'last_read_at'];

    /** @var array<string, string> */
    protected $casts = ['last_read_at' => 'datetime'];

    /** @return HasMany<AdvisorMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AdvisorMessage::class, 'session_id')->orderBy('id');
    }

    /**
     * A reply is still being produced when the newest message is a pending
     * assistant turn (chat) or the opening report is still generating.
     */
    public function isGenerating(): bool
    {
        if ($this->status === self::STATUS_PENDING) {
            return true;
        }

        $last = $this->messages->last();

        return $last?->role === AdvisorMessage::ROLE_ASSISTANT && $last->status === AdvisorMessage::STATUS_PENDING;
    }

    /**
     * The session has a finished assistant reply the user hasn't seen: the last
     * message is a done assistant turn newer than their last read (or never
     * read). The currently open session is marked read, so it never shows here.
     */
    public function hasUnread(): bool
    {
        $last = $this->messages->last();

        if ($last?->role !== AdvisorMessage::ROLE_ASSISTANT || $last->status !== AdvisorMessage::STATUS_DONE) {
            return false;
        }

        return $this->last_read_at === null || ($last->created_at !== null && $last->created_at->greaterThan($this->last_read_at));
    }
}
