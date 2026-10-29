<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Actions\Advisor\BuildAdvisorContext;
use App\Actions\Advisor\ContinueChat;
use App\Actions\Notifications\PushNotification;
use App\Advisor\AdvisorBudget;
use App\Advisor\AdvisorBudgetExceeded;
use App\Advisor\AdvisorOutputFilter;
use App\Advisor\Tools\AdvisorToolActivityReporter;
use App\Advisor\Tools\AdvisorToolFactory;
use App\Advisor\Tools\AdvisorWidgetCollector;
use App\Contracts\AdvisorProvider;
use App\Http\Clients\PrismAdvisorProvider;
use App\Jobs\ContinueChatJob;
use App\Models\AdvisorMessage;
use App\Models\AdvisorSession;
use App\Models\AdvisorTokenUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Prism\Prism\Enums\Provider;
use Tests\TestCase;

/**
 * The spend cap on the AI advisor: what it counts, when it refuses, and that
 * refusing produces something the user can read rather than a 500 or a silent
 * failure blamed on the model.
 */
class AdvisorBudgetTest extends TestCase
{
    use RefreshDatabase;

    private function budget(): AdvisorBudget
    {
        return $this->app->make(AdvisorBudget::class);
    }

    /** Book $tokens as already spent, optionally against a session. */
    private function spend(int $tokens, ?AdvisorSession $session = null, ?string $day = null): void
    {
        AdvisorTokenUsage::create([
            'session_id' => $session?->id,
            'day' => $day ?? Carbon::now()->toDateString(),
            'prompt_tokens' => $tokens,
            'completion_tokens' => 0,
            'total_tokens' => $tokens,
        ]);
    }

    private function newSession(): AdvisorSession
    {
        return AdvisorSession::create(['kind' => AdvisorSession::KIND_CHAT, 'status' => AdvisorSession::STATUS_DONE]);
    }

    /**
     * Bind the real Prism provider over a faked Ollama, sharing the container's
     * AdvisorBudget. A stub provider would skip the very check being tested —
     * enforcement lives in the provider, so the fake has to be the transport,
     * not the provider.
     */
    private function bindProvider(int $promptTokens = 10, int $completionTokens = 5): void
    {
        config()->set('prism.providers.ollama.url', 'http://ollama.test');

        Http::fake([
            '*/api/chat' => Http::response([
                'message' => ['role' => 'assistant', 'content' => 'Tutto bene.'],
                'done' => true,
                'prompt_eval_count' => $promptTokens,
                'eval_count' => $completionTokens,
            ]),
        ]);

        $this->app->instance(AdvisorProvider::class, $this->realProvider());
    }

    private function realProvider(): PrismAdvisorProvider
    {
        return new PrismAdvisorProvider(
            Provider::Ollama,
            'qwen3:8b',
            120,
            new AdvisorToolFactory($this->app->make(BuildAdvisorContext::class), new AdvisorToolActivityReporter, new AdvisorWidgetCollector, new AdvisorOutputFilter),
            $this->budget(),
        );
    }

    // ─── Accounting ──────────────────────────────────────────────────────────

    public function test_record_books_what_a_call_cost(): void
    {
        $this->budget()->record(1000, 250);

        $this->assertDatabaseHas('advisor_token_usage', [
            'prompt_tokens' => 1000,
            'completion_tokens' => 250,
            'total_tokens' => 1250,
        ]);
        $this->assertSame(1250, $this->budget()->dailyUsage());
    }

    public function test_yesterdays_spend_does_not_count_against_today(): void
    {
        $this->spend(900_000, day: Carbon::yesterday()->toDateString());

        $this->assertSame(0, $this->budget()->dailyUsage());
        $this->assertNull($this->budget()->exceeded());
    }

    // ─── The caps ────────────────────────────────────────────────────────────

    public function test_the_daily_cap_refuses_once_it_is_spent(): void
    {
        config()->set('services.advisor.budget.daily_tokens', 10_000);
        $this->spend(10_000);

        $reason = $this->budget()->exceeded();

        $this->assertNotNull($reason);
        $this->assertStringContainsString('giornaliero', $reason);

        $this->expectException(AdvisorBudgetExceeded::class);
        $this->budget()->ensureWithinBudget();
    }

    public function test_the_session_cap_refuses_only_the_conversation_that_spent_it(): void
    {
        config()->set('services.advisor.budget.session_tokens', 5_000);
        config()->set('services.advisor.budget.daily_tokens', 1_000_000);

        $hungry = $this->newSession();
        $this->spend(5_000, $hungry);

        $budget = $this->budget();

        // Unarmed — the opening report — sees only the daily cap, which has room.
        $this->assertNull($budget->exceeded());

        $budget->for($hungry);
        $this->assertStringContainsString('conversazione', (string) $budget->exceeded());

        // A fresh conversation is unaffected: the point of the per-session cap
        // is that one runaway thread does not take the others down with it.
        $budget->for($this->newSession());
        $this->assertNull($budget->exceeded());
    }

    public function test_the_whole_cap_can_be_switched_off(): void
    {
        config()->set('services.advisor.budget.enabled', false);
        config()->set('services.advisor.budget.daily_tokens', 1);
        $this->spend(1_000_000);

        $this->assertNull($this->budget()->exceeded());
        $this->assertNull($this->budget()->maxOutputTokens());

        // Disabled means not even bookkeeping — nothing to grow unbounded on a
        // local model that costs nothing.
        $this->budget()->record(500, 500);
        $this->assertDatabaseCount('advisor_token_usage', 1);
    }

    /**
     * The cap is only worth something if it cannot be reset from the UI.
     * Deleting a conversation is a one-click route, so cascading the usage rows
     * would have made the daily budget trivially bypassable.
     */
    public function test_deleting_a_conversation_does_not_refund_what_it_spent(): void
    {
        config()->set('services.advisor.budget.daily_tokens', 10_000);
        $session = $this->newSession();
        $this->spend(10_000, $session);

        $this->delete('/advisor/'.$session->id);

        $this->assertDatabaseMissing('advisor_sessions', ['id' => $session->id]);
        $this->assertSame(10_000, $this->budget()->dailyUsage());
        $this->assertNotNull($this->budget()->exceeded());
    }

    // ─── The user-facing refusal ─────────────────────────────────────────────

    public function test_an_over_budget_request_is_refused_with_a_readable_message(): void
    {
        config()->set('services.advisor.budget.daily_tokens', 10_000);
        $this->spend(10_000);

        $response = $this->postJson('/advisor/chat', ['message' => 'come va il portafoglio?']);

        $response->assertStatus(422)->assertJsonStructure(['error']);
        $this->assertStringContainsString('token', (string) $response->json('error'));

        // Refused before anything was created, so no half-started conversation
        // is left behind for the user to find.
        $this->assertDatabaseCount('advisor_sessions', 0);
    }

    public function test_a_conversation_over_its_own_cap_is_refused_on_its_own_route(): void
    {
        config()->set('services.advisor.budget.session_tokens', 5_000);
        config()->set('services.advisor.budget.daily_tokens', 1_000_000);
        $this->bindProvider();

        $session = $this->newSession();
        $this->spend(5_000, $session);

        $this->postJson('/advisor/'.$session->id.'/message', ['message' => 'ancora'])
            ->assertStatus(422)
            ->assertJsonStructure(['error']);

        // A different conversation still works — the day has room.
        $this->postJson('/advisor/'.$this->newSession()->id.'/message', ['message' => 'ciao'])
            ->assertOk();
    }

    public function test_reading_the_history_still_works_when_the_budget_is_spent(): void
    {
        config()->set('services.advisor.budget.daily_tokens', 10_000);
        $session = $this->newSession();
        $this->spend(10_000, $session);

        // Running out of tokens must not lock the owner out of their own data.
        $this->get('/advisor/'.$session->id)->assertOk();
        $this->get('/advisor/'.$session->id.'/status')->assertOk();
    }

    /**
     * Generation happens in a queued job, so the request-time check cannot be
     * the only one. When the cap is reached after the request was accepted, the
     * pending reply must fail with the real reason — the generic handler blames
     * the model, which would send the owner debugging something that works.
     */
    public function test_the_queued_reply_fails_with_the_budget_reason_not_a_model_error(): void
    {
        config()->set('services.advisor.budget.daily_tokens', 10_000);
        $this->bindProvider();

        $session = $this->newSession();
        $user = AdvisorMessage::create([
            'session_id' => $session->id,
            'role' => AdvisorMessage::ROLE_USER,
            'content' => 'come va?',
            'status' => AdvisorMessage::STATUS_DONE,
        ]);
        $assistant = AdvisorMessage::create([
            'session_id' => $session->id,
            'role' => AdvisorMessage::ROLE_ASSISTANT,
            'content' => '',
            'status' => AdvisorMessage::STATUS_PENDING,
        ]);

        // The budget runs out between accepting the request and running the job.
        $this->spend(10_000);

        new ContinueChatJob($user->id, $assistant->id)
            ->handle($this->app->make(ContinueChat::class), $this->app->make(PushNotification::class));

        $assistant->refresh();

        $this->assertSame(AdvisorMessage::STATUS_FAILED, $assistant->status);
        $this->assertStringContainsString('token', (string) $assistant->error);
        $this->assertStringNotContainsString('non ha risposto', (string) $assistant->error);
    }

    // ─── The provider is the authoritative meter ─────────────────────────────

    public function test_the_provider_books_the_usage_the_model_reported(): void
    {
        $this->bindProvider(promptTokens: 1200, completionTokens: 300);

        $this->realProvider()->chat([['role' => 'user', 'content' => 'come va?']]);

        $this->assertSame(1500, $this->budget()->dailyUsage());
    }

    public function test_the_provider_refuses_to_call_the_model_when_the_cap_is_spent(): void
    {
        config()->set('services.advisor.budget.daily_tokens', 10_000);
        $this->bindProvider();
        $this->spend(10_000);

        try {
            $this->realProvider()->chat([['role' => 'user', 'content' => 'come va?']]);
            $this->fail('Expected the budget to refuse the call.');
        } catch (AdvisorBudgetExceeded) {
            // The point: the model was never reached, so nothing was billed.
            Http::assertNothingSent();
        }
    }

    public function test_a_per_request_output_cap_is_sent_to_the_model(): void
    {
        config()->set('services.advisor.budget.max_output_tokens', 1234);

        $this->assertSame(1234, $this->budget()->maxOutputTokens());
    }
}
