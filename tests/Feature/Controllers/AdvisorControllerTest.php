<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Actions\Advisor\GenerateAdvisorReport;
use App\Actions\Notifications\PushNotification;
use App\Contracts\AdvisorProvider;
use App\Jobs\GenerateAdvisorReportJob;
use App\Models\AdvisorMessage;
use App\Models\AdvisorSession;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdvisorControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function bindProvider(bool $configured, string $reply = 'analisi'): void
    {
        $this->app->instance(AdvisorProvider::class, new class($configured, $reply) implements AdvisorProvider
        {
            public function __construct(private bool $configured, private string $reply) {}

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function analyze(string $briefing): string
            {
                return $this->reply;
            }

            /** @param  list<array{role: string, content: string}>  $messages */
            public function chat(array $messages): string
            {
                return $this->reply;
            }
        });
    }

    public function test_page_reports_configured_state(): void
    {
        $this->bindProvider(configured: true);

        $this->get('/advisor')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Advisor')->where('configured', true)->has('sessions'));
    }

    public function test_generate_opens_a_report_session_and_queues_the_job(): void
    {
        $this->bindProvider(configured: true);
        Queue::fake();

        $this->postJson('/advisor/generate')
            ->assertOk()
            ->assertJson(['status' => 'pending']);

        Queue::assertPushed(GenerateAdvisorReportJob::class);
        $this->assertDatabaseHas('advisor_sessions', ['kind' => 'report', 'status' => 'pending']);
    }

    public function test_generate_422_when_not_configured(): void
    {
        $this->bindProvider(configured: false);
        Queue::fake();

        $this->postJson('/advisor/generate')->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function test_generate_does_not_open_a_second_report_while_one_is_pending(): void
    {
        $this->bindProvider(configured: true);
        $existing = AdvisorSession::create(['kind' => 'report', 'status' => 'pending', 'title' => 'in corso']);
        Queue::fake();

        $this->postJson('/advisor/generate')
            ->assertOk()
            ->assertJson(['session_id' => $existing->id, 'status' => 'pending']);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('advisor_sessions', 1);
    }

    public function test_generate_keeps_old_sessions_in_history(): void
    {
        $this->bindProvider(configured: true);
        AdvisorSession::create(['kind' => 'report', 'status' => 'done', 'title' => 'vecchia']);
        Queue::fake();

        $this->postJson('/advisor/generate')->assertOk();

        // The old session survives — generating opens a NEW one.
        $this->assertDatabaseCount('advisor_sessions', 2);
        $this->assertDatabaseHas('advisor_sessions', ['title' => 'vecchia']);
    }

    public function test_status_returns_a_pending_sessions_state(): void
    {
        $session = AdvisorSession::create(['kind' => 'report', 'status' => 'pending']);

        $this->getJson("/advisor/{$session->id}/status")
            ->assertOk()
            ->assertJson(['status' => 'pending', 'messages' => []]);
    }

    public function test_status_returns_the_done_report_message(): void
    {
        $session = AdvisorSession::create(['kind' => 'report', 'status' => 'done']);
        AdvisorMessage::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => 'Analisi pronta.']);

        $this->getJson("/advisor/{$session->id}/status")
            ->assertOk()
            ->assertJson(['status' => 'done'])
            ->assertJsonPath('messages.0.content', 'Analisi pronta.');
    }

    public function test_job_generates_the_opening_message_and_marks_the_session_done(): void
    {
        $this->bindProvider(configured: true, reply: 'Il tuo portafoglio è solido.');
        $session = AdvisorSession::create(['kind' => 'report', 'status' => 'pending']);

        (new GenerateAdvisorReportJob($session->id))->handle(app(GenerateAdvisorReport::class), app(PushNotification::class));

        $session->refresh();
        $this->assertSame('done', $session->status);
        $this->assertDatabaseHas('advisor_messages', [
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => 'Il tuo portafoglio è solido.',
        ]);
    }

    public function test_job_marks_failed_when_not_configured(): void
    {
        $this->bindProvider(configured: false);
        $session = AdvisorSession::create(['kind' => 'report', 'status' => 'pending']);

        (new GenerateAdvisorReportJob($session->id))->handle(app(GenerateAdvisorReport::class), app(PushNotification::class));

        $this->assertSame('failed', $session->fresh()->status);
        $this->assertDatabaseCount('advisor_messages', 0);
    }

    public function test_chat_creates_a_session_and_returns_the_reply(): void
    {
        $this->bindProvider(configured: true, reply: 'Ecco la mia risposta.');

        $this->postJson('/advisor/chat', ['message' => 'Ho troppa liquidità?'])
            ->assertOk()
            ->assertJsonPath('message.role', 'assistant')
            ->assertJsonPath('message.content', 'Ecco la mia risposta.');

        $this->assertDatabaseHas('advisor_sessions', ['kind' => 'chat']);
        // The user question + the assistant reply are both persisted.
        $this->assertDatabaseHas('advisor_messages', ['role' => 'user', 'content' => 'Ho troppa liquidità?']);
        $this->assertDatabaseHas('advisor_messages', ['role' => 'assistant', 'content' => 'Ecco la mia risposta.']);
    }

    public function test_message_appends_to_an_existing_session(): void
    {
        $this->bindProvider(configured: true, reply: 'Risposta di follow-up.');
        $session = AdvisorSession::create(['kind' => 'chat', 'status' => 'done']);
        AdvisorMessage::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => 'apertura']);

        $this->postJson("/advisor/{$session->id}/message", ['message' => 'E i costi?'])
            ->assertOk()
            ->assertJsonPath('message.content', 'Risposta di follow-up.');

        $this->assertSame(3, $session->messages()->count());
    }

    public function test_destroy_removes_the_session_and_its_messages(): void
    {
        $session = AdvisorSession::create(['kind' => 'chat', 'status' => 'done']);
        AdvisorMessage::create(['session_id' => $session->id, 'role' => 'user', 'content' => 'ciao']);

        $this->delete("/advisor/{$session->id}")->assertRedirect();

        $this->assertDatabaseMissing('advisor_sessions', ['id' => $session->id]);
        $this->assertDatabaseCount('advisor_messages', 0);
    }
}
