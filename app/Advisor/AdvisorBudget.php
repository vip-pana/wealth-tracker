<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Advisor;

use App\Models\AdvisorSession;
use App\Models\AdvisorTokenUsage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * The spend cap on the AI advisor.
 *
 * Pointing the app at a cloud model and putting it on the internet makes every
 * advisor request a line on somebody's bill, and nothing in the app used to
 * stop that. This caps it in tokens — the unit every provider bills in and the
 * only one the app can measure without knowing a price list.
 *
 * Two caps, both configurable (`services.advisor.budget`):
 *
 * - **per day**, the one that bounds the bill;
 * - **per session**, so a single runaway conversation cannot eat the day in one
 *   sitting and leave the other surfaces dead.
 *
 * Enforced in two places on purpose. EnsureAdvisorBudget checks before the
 * request is accepted, which is what gives the user a readable answer instead
 * of a reply that silently never arrives. The authoritative check is here,
 * called by the provider immediately before each model call: generation happens
 * in a queued job, so by the time it runs the request-time check is minutes
 * old, and the tool-calling loop can make several calls per reply.
 *
 * A request-scoped singleton, armed with the session being generated (the same
 * idiom as AdvisorToolActivityReporter). Unarmed, only the daily cap applies —
 * correct for the opening report, which has no conversation to bound.
 */
class AdvisorBudget
{
    private ?int $sessionId = null;

    /**
     * Account the next model calls to this session, so the per-session cap
     * applies and the usage rows say which conversation spent them.
     */
    public function for(AdvisorSession $session): void
    {
        $this->sessionId = $session->id;
    }

    public function clear(): void
    {
        $this->sessionId = null;
    }

    public function enabled(): bool
    {
        return Config::boolean('services.advisor.budget.enabled', true);
    }

    /**
     * The cap on a single reply's output, handed to the model as max_tokens. The
     * daily and session caps stop a slow leak; this stops one request running
     * away on its own, which is the failure the other two notice too late.
     * Null when budgeting is off, which is what Prism wants for "no cap".
     */
    public function maxOutputTokens(): ?int
    {
        if (! $this->enabled()) {
            return null;
        }

        $max = Config::integer('services.advisor.budget.max_output_tokens', 0);

        return $max > 0 ? $max : null;
    }

    /**
     * Refuse the call when either cap is already spent.
     *
     * @throws AdvisorBudgetExceeded
     */
    public function ensureWithinBudget(): void
    {
        $reason = $this->exceeded();

        if ($reason !== null) {
            throw new AdvisorBudgetExceeded($reason);
        }
    }

    /**
     * The user-facing reason the advisor is refusing, or null when it is free to
     * run. Separate from ensureWithinBudget() so the middleware can answer with
     * a 422 instead of catching an exception it caused itself.
     */
    public function exceeded(): ?string
    {
        if (! $this->enabled()) {
            return null;
        }

        $sessionCap = Config::integer('services.advisor.budget.session_tokens', 0);

        if ($sessionCap > 0 && $this->sessionId !== null && $this->sessionUsage() >= $sessionCap) {
            return 'Questa conversazione ha raggiunto il limite di '.number_format($sessionCap, 0, ',', '.')
                .' token. Aprine una nuova per continuare.';
        }

        $dailyCap = Config::integer('services.advisor.budget.daily_tokens', 0);

        if ($dailyCap > 0 && $this->dailyUsage() >= $dailyCap) {
            return 'Hai raggiunto il limite giornaliero di '.number_format($dailyCap, 0, ',', '.')
                .' token del consulente AI. Riprende domani.';
        }

        return null;
    }

    /**
     * Book what a completed model call actually cost. Called by the provider
     * with the usage the provider itself reported, so the ledger reflects the
     * whole tool-calling loop rather than the final turn alone.
     */
    public function record(int $promptTokens, int $completionTokens): void
    {
        if (! $this->enabled()) {
            return;
        }

        AdvisorTokenUsage::create([
            'session_id' => $this->sessionId,
            'day' => Carbon::now()->toDateString(),
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $promptTokens + $completionTokens,
        ]);
    }

    /** Tokens spent today, across every session. */
    public function dailyUsage(): int
    {
        // A plain equality, not whereDate(): `day` is already a date column, and
        // whereDate() wraps it in strftime(), which throws away the index this
        // table was given one for.
        return (int) AdvisorTokenUsage::query()
            ->where('day', Carbon::now()->toDateString())
            ->sum('total_tokens');
    }

    /** Tokens spent by the armed session, over its whole life. */
    public function sessionUsage(): int
    {
        if ($this->sessionId === null) {
            return 0;
        }

        return (int) AdvisorTokenUsage::query()
            ->where('session_id', $this->sessionId)
            ->sum('total_tokens');
    }
}
