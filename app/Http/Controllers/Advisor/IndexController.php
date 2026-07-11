<?php

declare(strict_types=1);

namespace App\Http\Controllers\Advisor;

use App\Actions\Advisor\ComputeAdvisorFunFacts;
use App\Contracts\AdvisorProvider;
use App\Http\Controllers\Controller;
use App\Models\AdvisorMessage;
use App\Models\AdvisorSession;
use App\Models\Goal;
use App\Models\InvestorProfile;
use App\Models\Notification;
use Inertia\Inertia;
use Inertia\Response;

class IndexController extends Controller
{
    public function __construct(
        private readonly AdvisorProvider $provider,
        private readonly ComputeAdvisorFunFacts $funFacts,
    ) {}

    public function __invoke(?AdvisorSession $session = null): Response
    {
        $profile = InvestorProfile::query()->first();
        $goal = Goal::query()->with(['milestones', 'categoryAllocations'])->first();

        // Opening a session marks it read, so its unread dot clears. A session
        // whose reply is still generating is left unread until it finishes and
        // the user next opens it. Reaching the session directly (not via the
        // bell) also dismisses any notification pointing at it — the user has
        // arrived, so the bell must not keep nagging about it.
        if ($session instanceof AdvisorSession && ! $session->isGenerating()) {
            $session->update(['last_read_at' => now()]);

            Notification::query()
                ->unread()
                ->where('action_url', '/advisor/'.$session->id)
                ->each(fn (Notification $n) => $n->markRead());
        }

        return Inertia::render('Advisor', [
            'configured' => $this->provider->isConfigured(),
            'profile' => $profile ? [
                'horizon' => $profile->horizon,
                'risk_tolerance' => $profile->risk_tolerance,
                'objective' => $profile->objective,
                'target_allocation' => $profile->target_allocation,
                'notes' => $profile->notes,
            ] : null,
            'goalObjective' => $goal?->name,
            'goal' => $goal instanceof Goal ? [
                'name' => $goal->name,
                'description' => $goal->description,
                'target_value' => $goal->target_value,
                'target_date' => $goal->target_date?->format('Y-m-d'),
                'milestones' => $goal->milestones
                    ->map(fn ($m): array => [
                        'notes' => $m->notes,
                        'target_value' => (float) $m->target_value,
                        'target_date' => $m->target_date->format('Y-m-d'),
                    ])
                    ->all(),
                'macro_allocations' => $goal->categoryAllocations
                    ->filter(fn ($a): bool => $a->macro_category !== null)
                    ->map(fn ($a): array => [
                        'macro_category' => $a->macro_category,
                        'percentage' => (float) $a->percentage,
                    ])
                    ->values()
                    ->all(),
            ] : null,
            'sessions' => $this->sessionList(),
            'activeSession' => $session instanceof AdvisorSession ? $this->serializeSession($session) : null,
            'funFacts' => $this->provider->isConfigured() ? $this->funFacts->run() : [],
        ]);
    }

    /**
     * The session history for the sidebar list (no message bodies — light).
     *
     * @return list<array{id: int, kind: string, title: string|null, status: string, generating: bool, unread: bool, created_at: string|null}>
     */
    private function sessionList(): array
    {
        $rows = AdvisorSession::query()
            ->with('messages')
            ->latest('id')
            ->get()
            ->map(fn (AdvisorSession $s): array => [
                'id' => $s->id,
                'kind' => $s->kind,
                'title' => $s->title,
                'status' => $s->status,
                'generating' => $s->isGenerating(),
                'unread' => $s->hasUnread(),
                'created_at' => $s->created_at?->toISOString(),
            ])
            ->all();

        return array_values($rows);
    }

    /**
     * A full session with its messages, for the conversation view.
     *
     * @return array{id: int, kind: string, title: string|null, status: string, error: string|null, messages: list<array{id: int, role: string, content: string, status: string, error: string|null, widgets: list<array{type: string, data: array<string, mixed>}>|null, created_at: string|null}>}
     */
    private function serializeSession(AdvisorSession $session): array
    {
        $messages = $session->messages()
            ->get()
            ->map(fn (AdvisorMessage $m): array => [
                'id' => $m->id,
                'role' => $m->role,
                'content' => $m->content,
                'status' => $m->status,
                'error' => $m->error,
                'widgets' => $m->widgets,
                'created_at' => $m->created_at?->toISOString(),
            ])
            ->all();

        return [
            'id' => $session->id,
            'kind' => $session->kind,
            'title' => $session->title,
            'status' => $session->status,
            'error' => $session->error,
            'messages' => array_values($messages),
        ];
    }
}
