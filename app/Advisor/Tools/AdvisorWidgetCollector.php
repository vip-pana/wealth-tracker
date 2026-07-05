<?php

declare(strict_types=1);

namespace App\Advisor\Tools;

use App\Models\AdvisorMessage;

/**
 * Collects the generative-UI widgets an advisor reply should render. A tool that
 * has an interactive counterpart (e.g. simulate_pac -> a slider chart) calls
 * add() alongside returning its text: the text is what the model reasons over,
 * the widget is a structured payload of app-computed data the frontend mounts as
 * a React component. The model never sees the widgets, so it cannot break them.
 *
 * A request-scoped singleton, mirroring AdvisorToolActivityReporter: ContinueChat
 * arms it with the pending assistant message, tools append as they run, and the
 * accumulated widgets are persisted onto that message's `widgets` column when the
 * reply is done. When not armed (report generation, unit tests) add() is a no-op.
 */
class AdvisorWidgetCollector
{
    private ?AdvisorMessage $target = null;

    /** @var list<array{type: string, data: array<string, mixed>}> */
    private array $widgets = [];

    /**
     * Whether the profile-proposal tool is allowed to emit its widget this turn.
     * The advisor must not propose a profile change on its own initiative — only
     * after the user explicitly agrees. ContinueChat sets this from the user's
     * last message; when false, propose_profile_update emits nothing and tells
     * the model to ask for consent first. Deterministic guard: the prompt alone
     * did not stop the model from proposing.
     */
    private bool $profileProposalAllowed = false;

    public function for(AdvisorMessage $message): void
    {
        $this->target = $message;
        $this->widgets = [];
    }

    public function allowProfileProposal(bool $allowed): void
    {
        $this->profileProposalAllowed = $allowed;
    }

    public function isProfileProposalAllowed(): bool
    {
        return $this->profileProposalAllowed;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function add(string $type, array $data): void
    {
        if (! $this->target instanceof AdvisorMessage) {
            return;
        }

        $this->widgets[] = ['type' => $type, 'data' => $data];
    }

    /**
     * The collected widgets. profile_proposal is de-duplicated keeping only the
     * LAST one: a confused model can call propose_profile_update more than once
     * within a single reply's tool loop, and only the final proposal should
     * reach the UI. Other widget types are all kept (a reply can legitimately
     * carry several — e.g. two different position cards).
     *
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public function widgets(): array
    {
        $lastProposalIndex = null;
        foreach ($this->widgets as $i => $widget) {
            if ($widget['type'] === 'profile_proposal') {
                $lastProposalIndex = $i;
            }
        }

        if ($lastProposalIndex === null) {
            return $this->widgets;
        }

        $out = [];
        foreach ($this->widgets as $i => $widget) {
            if ($widget['type'] === 'profile_proposal' && $i !== $lastProposalIndex) {
                continue;
            }
            $out[] = $widget;
        }

        return $out;
    }

    public function clear(): void
    {
        $this->target = null;
        $this->widgets = [];
        $this->profileProposalAllowed = false;
    }
}
