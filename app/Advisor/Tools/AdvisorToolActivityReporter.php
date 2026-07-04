<?php

declare(strict_types=1);

namespace App\Advisor\Tools;

use App\Models\AdvisorMessage;

/**
 * Lets the advisor tools report, in real time, which one is running so the
 * polling chat UI can show "Sto controllando la tua posizione Bitcoin…" instead
 * of a blank wait. A request-scoped singleton: ContinueChat arms it with the
 * pending assistant message before asking the model, each tool calls report()
 * as it starts, and the label is written straight to the DB row the UI polls.
 *
 * When not armed (the tools run outside a tracked chat turn — e.g. the opening
 * report, or a unit test) report() is a no-op, so nothing is persisted.
 */
class AdvisorToolActivityReporter
{
    private ?AdvisorMessage $target = null;

    public function for(AdvisorMessage $message): void
    {
        $this->target = $message;
    }

    public function report(string $label): void
    {
        $this->target?->update(['tool_activity' => $label]);
    }

    public function clear(): void
    {
        $this->target = null;
    }
}
