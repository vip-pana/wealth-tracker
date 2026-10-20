<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Models;

use App\Advisor\AdvisorOutputFilter;
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
 * @property string|null $tool_activity
 * @property list<array{type: string, data: array<string, mixed>}>|null $widgets
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

    protected $fillable = ['session_id', 'role', 'content', 'status', 'error', 'tool_activity', 'widgets'];

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['widgets' => 'array'];
    }

    /**
     * The content to send to the UI. Assistant replies pass through the
     * advisor firewall a second time here, on the way out: filtering only at
     * write time would leave anything already in the database — written before
     * the filter existed, or by a future path that forgets to call it — on
     * screen unchecked. The filter is idempotent, so a reply already cleared at
     * write time is returned untouched. User turns are the user's own words and
     * are never rewritten.
     */
    public function displayContent(): string
    {
        if ($this->role !== self::ROLE_ASSISTANT) {
            return $this->content;
        }

        return app(AdvisorOutputFilter::class)->filter($this->content);
    }

    /** @return BelongsTo<AdvisorSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AdvisorSession::class, 'session_id');
    }
}
