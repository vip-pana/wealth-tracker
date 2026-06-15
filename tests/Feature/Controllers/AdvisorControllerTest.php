<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Actions\Advisor\GenerateAdvisorReport;
use App\Actions\Notifications\PushNotification;
use App\Contracts\AdvisorProvider;
use App\Jobs\GenerateAdvisorReportJob;
use App\Models\AdvisorReport;
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

    private function bindProvider(bool $configured, string $reply = 'analisi', bool $throws = false): void
    {
        $this->app->instance(AdvisorProvider::class, new class($configured, $reply, $throws) implements AdvisorProvider
        {
            public function __construct(private bool $configured, private string $reply, private bool $throws) {}

            public function isConfigured(): bool
            {
                return $this->configured;
            }

            public function analyze(string $briefing): string
            {
                if ($this->throws) {
                    throw new \RuntimeException('Il modello locale non ha risposto.');
                }

                return $this->reply;
            }
        });
    }

    public function test_page_reports_configured_state(): void
    {
        $this->bindProvider(configured: true);

        $this->get('/advisor')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Advisor')->where('configured', true));
    }

    public function test_generate_queues_a_job_and_returns_pending(): void
    {
        $this->bindProvider(configured: true);
        Queue::fake();

        $this->postJson('/advisor/generate')
            ->assertOk()
            ->assertJson(['status' => 'pending']);

        Queue::assertPushed(GenerateAdvisorReportJob::class);
        $this->assertDatabaseHas('advisor_reports', ['status' => 'pending']);
    }

    public function test_generate_422_when_not_configured(): void
    {
        $this->bindProvider(configured: false);
        Queue::fake();

        $this->postJson('/advisor/generate')->assertStatus(422);
        Queue::assertNothingPushed();
    }

    public function test_generate_replaces_the_previous_report(): void
    {
        $this->bindProvider(configured: true);
        AdvisorReport::create(['status' => 'done', 'content' => 'vecchia']);
        Queue::fake();

        $this->postJson('/advisor/generate')->assertOk();

        // Single-row: the old report is gone, replaced by a fresh pending one.
        $this->assertDatabaseCount('advisor_reports', 1);
        $this->assertDatabaseMissing('advisor_reports', ['content' => 'vecchia']);
    }

    public function test_generate_does_not_queue_a_second_job_while_pending(): void
    {
        $this->bindProvider(configured: true);
        AdvisorReport::create(['status' => 'pending']);
        Queue::fake();

        $this->postJson('/advisor/generate')->assertOk()->assertJson(['status' => 'pending']);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('advisor_reports', 1);
    }

    public function test_status_is_idle_with_no_report(): void
    {
        $this->getJson('/advisor/status')->assertOk()->assertJson(['status' => 'idle']);
    }

    public function test_status_returns_the_done_report(): void
    {
        AdvisorReport::create(['status' => 'done', 'content' => 'Analisi pronta.']);

        $this->getJson('/advisor/status')
            ->assertOk()
            ->assertJson(['status' => 'done', 'content' => 'Analisi pronta.']);
    }

    public function test_job_generates_and_marks_the_report_done(): void
    {
        $this->bindProvider(configured: true, reply: 'Il tuo portafoglio è solido.');
        $report = AdvisorReport::create(['status' => 'pending']);

        (new GenerateAdvisorReportJob($report->id))->handle(app(GenerateAdvisorReport::class), app(PushNotification::class));

        $report->refresh();
        $this->assertSame('done', $report->status);
        $this->assertSame('Il tuo portafoglio è solido.', $report->content);
    }

    public function test_job_marks_failed_when_not_configured(): void
    {
        $this->bindProvider(configured: false);
        $report = AdvisorReport::create(['status' => 'pending']);

        (new GenerateAdvisorReportJob($report->id))->handle(app(GenerateAdvisorReport::class), app(PushNotification::class));

        $this->assertSame('failed', $report->fresh()->status);
    }
}
