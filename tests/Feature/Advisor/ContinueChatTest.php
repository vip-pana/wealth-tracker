<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Actions\Advisor\ContinueChat;
use App\Contracts\AdvisorProvider;
use App\Models\AdvisorMessage;
use App\Models\AdvisorSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContinueChatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A provider that records the message list it was asked to send, so the
     * test can assert what context the chat action built.
     *
     * @param  list<array{role: string, content: string}>  $captured
     */
    private function recordingProvider(bool $configured, array &$captured): AdvisorProvider
    {
        return new class($configured, $captured) implements AdvisorProvider
        {
            /** @param  list<array{role: string, content: string}>  $captured */
            public function __construct(private readonly bool $configured, private array &$captured) {}

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function analyze(string $briefing): string
            {
                return 'n/a';
            }

            /** @param  list<array{role: string, content: string}>  $messages */
            public function chat(array $messages): string
            {
                $this->captured = $messages;

                return 'Risposta del consulente.';
            }

            /**
             * @param  list<array{role: string, content: string}>  $messages
             * @param  callable(string): void  $onChunk
             */
            public function chatStream(array $messages, callable $onChunk): string
            {
                $this->captured = $messages;
                $onChunk('Risposta del consulente.');

                return 'Risposta del consulente.';
            }
        };
    }

    public function test_returns_null_and_persists_nothing_when_unconfigured(): void
    {
        $captured = [];
        $this->app->instance(AdvisorProvider::class, $this->recordingProvider(false, $captured));
        $session = AdvisorSession::create(['kind' => 'chat', 'status' => 'done']);

        $reply = app(ContinueChat::class)->run($session, 'Ciao');

        $this->assertNull($reply);
        $this->assertDatabaseCount('advisor_messages', 0);
    }

    public function test_persists_user_then_assistant_and_sends_fresh_context(): void
    {
        $captured = [];
        $this->app->instance(AdvisorProvider::class, $this->recordingProvider(true, $captured));
        $session = AdvisorSession::create(['kind' => 'chat', 'status' => 'done']);

        $reply = app(ContinueChat::class)->run($session, 'Ho troppa liquidità?');

        $this->assertNotNull($reply);
        $this->assertSame('Risposta del consulente.', $reply->content);

        // Both turns persisted, user before assistant.
        $messages = $session->messages()->get();
        $this->assertSame('user', $messages[0]->role);
        $this->assertSame('Ho troppa liquidità?', $messages[0]->content);
        $this->assertSame('assistant', $messages[1]->role);

        // The model received: a system prompt, a fresh-context system message,
        // then the conversation (here just the new user turn).
        $this->assertSame('system', $captured[0]['role']);
        $this->assertSame('system', $captured[1]['role']);
        $this->assertStringContainsString('portafoglio', strtolower($captured[1]['content']));
        $this->assertSame('user', $captured[count($captured) - 1]['role']);
        $this->assertSame('Ho troppa liquidità?', $captured[count($captured) - 1]['content']);
    }

    public function test_collapses_consecutive_same_role_turns_so_the_model_sees_clean_alternation(): void
    {
        $captured = [];
        $this->app->instance(AdvisorProvider::class, $this->recordingProvider(true, $captured));
        $session = AdvisorSession::create(['kind' => 'chat', 'status' => 'done']);
        // A poisoned history (orphaned user turns from past failures).
        AdvisorMessage::create(['session_id' => $session->id, 'role' => 'user', 'content' => 'a']);
        AdvisorMessage::create(['session_id' => $session->id, 'role' => 'user', 'content' => 'b']);

        app(ContinueChat::class)->run($session, 'c');

        // Among the conversation turns (ignoring the leading system messages,
        // which may legitimately repeat), no two consecutive turns share a role.
        $convo = array_values(array_filter($captured, fn (array $m): bool => $m['role'] !== 'system'));
        $counter = count($convo);
        for ($i = 1; $i < $counter; $i++) {
            $this->assertNotSame($convo[$i - 1]['role'], $convo[$i]['role'], 'consecutive same-role turn leaked to the model');
        }
        // The three orphaned/new user turns collapsed into a single user turn.
        $this->assertCount(1, $convo);
        $this->assertStringContainsString('a', $convo[0]['content']);
        $this->assertStringContainsString('c', $convo[0]['content']);
    }

    public function test_a_failed_model_call_leaves_no_orphan_user_message(): void
    {
        $this->app->instance(AdvisorProvider::class, new class implements AdvisorProvider
        {
            public function isConfigured(): bool
            {
                return true;
            }

            public function analyze(string $briefing): string
            {
                return 'n/a';
            }

            /** @param  list<array{role: string, content: string}>  $messages */
            public function chat(array $messages): string
            {
                throw new \RuntimeException('modello giù');
            }

            /**
             * @param  list<array{role: string, content: string}>  $messages
             * @param  callable(string): void  $onChunk
             */
            public function chatStream(array $messages, callable $onChunk): string
            {
                throw new \RuntimeException('modello giù');
            }
        });
        $session = AdvisorSession::create(['kind' => 'chat', 'status' => 'done']);

        try {
            app(ContinueChat::class)->run($session, 'domanda');
        } catch (\RuntimeException) {
            // expected
        }

        // The user turn must NOT be persisted if the reply failed.
        $this->assertDatabaseCount('advisor_messages', 0);
    }

    public function test_includes_recent_history_in_the_sent_messages(): void
    {
        $captured = [];
        $this->app->instance(AdvisorProvider::class, $this->recordingProvider(true, $captured));
        $session = AdvisorSession::create(['kind' => 'chat', 'status' => 'done']);
        AdvisorMessage::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => 'apertura']);

        app(ContinueChat::class)->run($session, 'E adesso?');

        $roles = array_map(fn (array $m): string => $m['role'], $captured);
        // system, system(context), assistant(apertura), user(E adesso?)
        $this->assertContains('assistant', $roles);
        $contents = array_map(fn (array $m): string => $m['content'], $captured);
        $this->assertContains('apertura', $contents);
        $this->assertContains('E adesso?', $contents);
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function consentCases(): iterable
    {
        yield 'question is never consent' => ['Se avessi tolleranza alta cosa cambierebbe?', false];
        yield 'bare si' => ['Sì', true];
        yield 'ok update it' => ['Ok aggiorna il profilo', true];
        yield 'va bene' => ['va bene', true];
        yield 'plain answer is not consent' => ['Libertà finanziaria tra 20 anni', false];
        yield 'update-with-question is not consent' => ['Vuoi aggiornare il profilo?', false];
        yield 'empty is not consent' => ['', false];
    }

    #[DataProvider('consentCases')]
    public function test_detects_explicit_consent_to_update_the_profile(string $message, bool $expected): void
    {
        $action = app(ContinueChat::class);
        $method = new \ReflectionMethod($action, 'userConsentsToProfileUpdate');

        $this->assertSame($expected, $method->invoke($action, $message));
    }
}
