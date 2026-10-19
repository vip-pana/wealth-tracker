<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Advisor\AdvisorBudget;
use App\Models\AdvisorSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses an advisor request whose budget is already spent, before it is
 * accepted.
 *
 * The authoritative check lives in the provider (AdvisorBudget), but that one
 * runs inside a queued job: on its own it would leave the user staring at a
 * "pending" bubble that eventually flips to an error. Checking here turns the
 * same condition into an immediate, readable 422 on the surface that asked.
 *
 * Applied to the advisor routes that reach the model — the ones grouped under
 * the `advisor-generate` limiter. Reads, renames and deletions are deliberately
 * left alone: they cost nothing, and locking the user out of their own history
 * because the day's tokens ran out would be the wrong failure.
 */
class EnsureAdvisorBudget
{
    public function __construct(
        private readonly AdvisorBudget $budget,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Arm with the session when the route carries one, so a conversation
        // that is over its own cap is refused even while the day has room left.
        $session = $request->route('session');

        if ($session instanceof AdvisorSession) {
            $this->budget->for($session);
        }

        $reason = $this->budget->exceeded();

        // Leave the singleton as we found it: the provider arms it per
        // generation, and a stale session id here would account a later report
        // to this conversation.
        $this->budget->clear();

        if ($reason !== null) {
            // Every advisor route under this middleware is called by axios and
            // already handles a 422 body with `error`; the surface shows the
            // message as-is. 429 would be the throttle's code, and this is not a
            // "slow down" — waiting a minute changes nothing.
            return response()->json(['error' => $reason], 422);
        }

        return $next($request);
    }
}
