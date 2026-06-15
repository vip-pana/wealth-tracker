<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Actions\Advisor\ContinueChat;
use App\Contracts\AdvisorProvider;
use App\Models\AdvisorMessage;
use App\Models\AdvisorSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            public function __construct(private bool $configured, private array &$captured) {}

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
}
