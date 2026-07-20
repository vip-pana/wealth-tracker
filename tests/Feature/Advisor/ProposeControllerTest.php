<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Contracts\AdvisorProvider;
use App\Jobs\ProposeNowJob;
use App\Models\AdvisorSession;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProposeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app->instance(AdvisorProvider::class, $this->configuredProvider());
    }

    private function configuredProvider(): AdvisorProvider
    {
        return new class implements AdvisorProvider
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
                return 'n/a';
            }

            /**
             * @param  list<array{role: string, content: string}>  $messages
             * @param  callable(string): void  $onChunk
             */
            public function chatStream(array $messages, callable $onChunk): string
            {
                return 'n/a';
            }
        };
    }

    public function test_creates_a_pending_assistant_turn_and_dispatches_the_job(): void
    {
        Queue::fake();
        $session = AdvisorSession::create(['kind' => 'chat', 'title' => 't', 'status' => 'done']);

        $this->postJson("/advisor/{$session->id}/propose/profile")
            ->assertOk()
            ->assertJsonPath('assistant.status', 'pending')
            ->assertJsonPath('assistant.role', 'assistant');

        $this->assertDatabaseHas('advisor_messages', [
            'session_id' => $session->id,
            'role' => 'assistant',
            'status' => 'pending',
        ]);
        Queue::assertPushed(ProposeNowJob::class);
    }

    public function test_rejects_an_unknown_kind(): void
    {
        Queue::fake();
        $session = AdvisorSession::create(['kind' => 'chat', 'title' => 't', 'status' => 'done']);

        $this->postJson("/advisor/{$session->id}/propose/banana")->assertNotFound();

        Queue::assertNothingPushed();
    }
}
