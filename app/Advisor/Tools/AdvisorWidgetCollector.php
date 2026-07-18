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

    /**
     * Whether the goal-proposal tools (propose_goal_core / _milestones /
     * _composition) may emit their widget this turn. Deliberately SEPARATE from
     * the profile flag: consenting to a profile update must not unlock a goal
     * write in the same turn, and vice-versa. One flag covers all three goal
     * tools — they are one capability ("define my goal"), gated together.
     */
    private bool $goalProposalAllowed = false;

    /**
     * Whether the offer_*_proposal tools may emit their "generate proposal"
     * BUTTON this turn. Distinct from the proposal flags above: the button is
     * offered during the interview (before consent), while the proposal cards
     * fire only after the user clicks it. Gated so the button appears ONLY in a
     * goal/profile interview — a plain chat that happens to mention figures must
     * never surface it (the model would otherwise call offer_* on its own).
     */
    private bool $goalOfferAllowed = false;

    private bool $profileOfferAllowed = false;

    /**
     * The proposal widget types that must be de-duplicated to the LAST one a
     * confused model emits within a single reply's tool loop. Any type not
     * listed here is kept as-is (a reply can legitimately carry several, e.g.
     * two position cards).
     */
    private const array PROPOSAL_TYPES = [
        'profile_proposal',
        'goal_core_proposal',
        'goal_milestones_proposal',
        'goal_composition_proposal',
    ];

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

    public function allowGoalProposal(bool $allowed): void
    {
        $this->goalProposalAllowed = $allowed;
    }

    public function isGoalProposalAllowed(): bool
    {
        return $this->goalProposalAllowed;
    }

    public function allowGoalOffer(bool $allowed): void
    {
        $this->goalOfferAllowed = $allowed;
    }

    public function isGoalOfferAllowed(): bool
    {
        return $this->goalOfferAllowed;
    }

    public function allowProfileOffer(bool $allowed): void
    {
        $this->profileOfferAllowed = $allowed;
    }

    public function isProfileOfferAllowed(): bool
    {
        return $this->profileOfferAllowed;
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
     * The collected widgets, de-duplicated. Each proposal type (see
     * PROPOSAL_TYPES) and each proposal_offer button is kept only ONCE — the
     * LAST one — because a confused model can call a proposal or offer tool more
     * than once within a single reply's tool loop, and only the final one of
     * each kind should reach the UI (a duplicate offer button rendered twice).
     * The offer button is keyed by type+kind so a goal offer and a profile offer
     * (never expected together) wouldn't cancel each other. Other widget types
     * are all kept (a reply can legitimately carry several — e.g. two different
     * position cards).
     *
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public function widgets(): array
    {
        $lastIndexByKey = [];
        foreach ($this->widgets as $i => $widget) {
            $key = $this->dedupKey($widget);
            if ($key !== null) {
                $lastIndexByKey[$key] = $i;
            }
        }

        if ($lastIndexByKey === []) {
            return $this->widgets;
        }

        $out = [];
        foreach ($this->widgets as $i => $widget) {
            $key = $this->dedupKey($widget);
            if ($key !== null && $lastIndexByKey[$key] !== $i) {
                continue;
            }
            $out[] = $widget;
        }

        return $out;
    }

    /**
     * The de-dup key for a widget, or null when the widget is not de-duplicated.
     * Proposal cards de-dup by type; the proposal_offer button by type+kind.
     *
     * @param  array{type: string, data: array<string, mixed>}  $widget
     */
    private function dedupKey(array $widget): ?string
    {
        if (in_array($widget['type'], self::PROPOSAL_TYPES, true)) {
            return $widget['type'];
        }

        if ($widget['type'] === 'proposal_offer') {
            $kind = is_string($widget['data']['kind'] ?? null) ? $widget['data']['kind'] : '';

            return 'proposal_offer:'.$kind;
        }

        return null;
    }

    public function clear(): void
    {
        $this->target = null;
        $this->widgets = [];
        $this->profileProposalAllowed = false;
        $this->goalProposalAllowed = false;
        $this->goalOfferAllowed = false;
        $this->profileOfferAllowed = false;
    }
}
