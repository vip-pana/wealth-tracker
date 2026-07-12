<?php

declare(strict_types=1);

namespace Tests\Unit\Advisor;

use App\Actions\Advisor\ContinueChat;
use App\Models\AdvisorMessage;
use App\Models\AdvisorSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class InterviewSlotsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array{target: bool, date: bool, income: bool, tolerance: bool}  $slots
     */
    private function briefing(array $slots): string
    {
        $method = new ReflectionMethod(ContinueChat::class, 'slotFillingBriefing');
        $method->setAccessible(true);

        return (string) $method->invoke(app(ContinueChat::class), $slots);
    }

    private function slots(AdvisorSession $session, string $userMessage): array
    {
        $method = new ReflectionMethod(ContinueChat::class, 'interviewSlots');
        $method->setAccessible(true);

        /** @var array{target: bool, date: bool, income: bool, tolerance: bool} $slots */
        $slots = $method->invoke(app(ContinueChat::class), $session, $userMessage, null);

        return $slots;
    }

    private function userTurn(AdvisorSession $session, string $content): void
    {
        AdvisorMessage::create([
            'session_id' => $session->id,
            'role' => AdvisorMessage::ROLE_USER,
            'content' => $content,
            'status' => AdvisorMessage::STATUS_DONE,
        ]);
    }

    public function test_no_answers_leaves_every_slot_empty(): void
    {
        $session = AdvisorSession::create([
            'kind' => AdvisorSession::KIND_CHAT,
            'title' => 'x',
            'status' => AdvisorSession::STATUS_DONE,
        ]);

        $slots = $this->slots($session, 'Il mio portafoglio è ben diversificato?');

        $this->assertNotContains(true, $slots);
    }

    /**
     * @return AdvisorSession::KIND_GOAL_INTERVIEW|AdvisorSession::KIND_PROFILE_INTERVIEW|null
     */
    private function kind(AdvisorSession $session, string $userMessage): ?string
    {
        $method = new ReflectionMethod(ContinueChat::class, 'interviewKind');
        $method->setAccessible(true);

        /** @var AdvisorSession::KIND_GOAL_INTERVIEW|AdvisorSession::KIND_PROFILE_INTERVIEW|null $kind */
        $kind = $method->invoke(app(ContinueChat::class), $session, $userMessage, null);

        return $kind;
    }

    public function test_plain_chat_mentioning_figures_is_not_an_interview(): void
    {
        // The regression: a question that merely cites a percentage and liquidity
        // must NOT count as a goal interview, so the proposal button stays hidden.
        $session = AdvisorSession::create([
            'kind' => AdvisorSession::KIND_CHAT,
            'title' => 'x',
            'status' => AdvisorSession::STATUS_DONE,
        ]);
        $this->userTurn($session, 'Il mio ETF ACWI ha reso il 20%, la liquidità è troppa?');

        $this->assertNull($this->kind($session, 'e nel 2024 come è andato?'));
    }

    public function test_explicit_intent_promotes_a_plain_chat_to_interview(): void
    {
        $session = AdvisorSession::create([
            'kind' => AdvisorSession::KIND_CHAT,
            'title' => 'x',
            'status' => AdvisorSession::STATUS_DONE,
        ]);

        $this->assertSame(
            AdvisorSession::KIND_GOAL_INTERVIEW,
            $this->kind($session, 'voglio ridefinire il mio obiettivo e le milestone'),
        );
    }

    public function test_interview_kind_honours_the_session_kind(): void
    {
        $session = AdvisorSession::create([
            'kind' => AdvisorSession::KIND_PROFILE_INTERVIEW,
            'title' => 'x',
            'status' => AdvisorSession::STATUS_DONE,
        ]);

        $this->assertSame(
            AdvisorSession::KIND_PROFILE_INTERVIEW,
            $this->kind($session, 'ok'),
        );
    }

    public function test_reproduces_the_completed_goal_interview(): void
    {
        // The real session 104 turns that made the model loop: by the last user
        // message every theme is answered, so all four slots must read true.
        $session = AdvisorSession::create([
            'kind' => AdvisorSession::KIND_CHAT,
            'title' => 'Aiutami a ridefinire il mio obiettivo',
            'status' => AdvisorSession::STATUS_DONE,
        ]);

        $this->userTurn($session, 'Voglio la libertà finanziaria, un milione di euro.');
        $this->userTurn($session, "L'età target è entro il 2051, quando raggiungo i 50 anni.");
        $this->userTurn($session, 'Guadagno circa 1900 euro al mese netti, senza un fondo di emergenza separato.');
        $this->userTurn($session, 'Non ho paura dei cali, fino al -30% non venderei e continuerei il PAC.');

        $slots = $this->slots($session, 'mostrami entrambe le opzioni');

        $this->assertSame(
            ['target' => true, 'date' => true, 'income' => true, 'tolerance' => true],
            $slots,
        );
    }

    public function test_briefing_orders_the_button_when_all_covered(): void
    {
        $text = $this->briefing(['target' => true, 'date' => true, 'income' => true, 'tolerance' => true]);

        $this->assertStringContainsString('offer_goal_proposal', $text);
        $this->assertStringNotContainsString('Ancora da chiarire', $text);
    }

    public function test_briefing_points_at_the_missing_theme(): void
    {
        $text = $this->briefing(['target' => true, 'date' => true, 'income' => false, 'tolerance' => false]);

        $this->assertStringContainsString('Già raccolto', $text);
        $this->assertStringContainsString('Ancora da chiarire', $text);
        $this->assertStringNotContainsString('offer_goal_proposal', $text);
    }
}
