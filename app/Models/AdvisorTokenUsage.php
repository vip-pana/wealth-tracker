<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per completed model call, written by AdvisorBudget::record(). The
 * token counts come from the provider's own usage report, so this is what was
 * actually spent rather than an estimate.
 *
 * Rows outlive the conversation they belong to (session_id goes null when a
 * session is deleted) — see the migration for why.
 *
 * `day` is deliberately NOT cast to a date. Laravel's `date` cast serialises
 * through the model's datetime format, so it writes `2026-10-20 00:00:00` into
 * the column — which then never matches the `Y-m-d` equality the cap query
 * uses, and silently reports a usage of zero. It is an accounting bucket, not a
 * moment in time, so it stays the plain `Y-m-d` string that AdvisorBudget
 * writes and compares.
 *
 * @property int $id
 * @property int|null $session_id
 * @property string $day
 * @property int $prompt_tokens
 * @property int $completion_tokens
 * @property int $total_tokens
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AdvisorSession|null $session
 */
class AdvisorTokenUsage extends Model
{
    protected $table = 'advisor_token_usage';

    protected $fillable = ['session_id', 'day', 'prompt_tokens', 'completion_tokens', 'total_tokens'];

    /** @return BelongsTo<AdvisorSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AdvisorSession::class, 'session_id');
    }
}
