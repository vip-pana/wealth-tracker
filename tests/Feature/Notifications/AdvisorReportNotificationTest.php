<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Actions\Advisor\GenerateAdvisorReport;
use App\Actions\Notifications\PushNotification;
use App\Contracts\AdvisorProvider;
use App\Jobs\GenerateAdvisorReportJob;
use App\Models\AdvisorSession;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvisorReportNotificationTest extends TestCase
{
    use RefreshDatabase;

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

            /**
             * @param  list<array{role: string, content: string}>  $messages
             * @param  callable(string): void  $onChunk
             */
            public function chatStream(array $messages, callable $onChunk): string
            {
                $onChunk($this->reply);

                return $this->reply;
            }
        });
    }

    public function test_a_done_report_produces_a_success_notification(): void
    {
        $this->bindProvider(configured: true, reply: 'Portafoglio solido.');
        $session = AdvisorSession::create(['kind' => 'report', 'status' => 'pending']);

        (new GenerateAdvisorReportJob($session->id))->handle(
            app(GenerateAdvisorReport::class),
            app(PushNotification::class),
        );

        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_ADVISOR_REPORT_READY,
            'level' => Notification::LEVEL_SUCCESS,
        ]);
    }

    public function test_an_unconfigured_provider_produces_a_warning_notification(): void
    {
        $this->bindProvider(configured: false);
        $session = AdvisorSession::create(['kind' => 'report', 'status' => 'pending']);

        (new GenerateAdvisorReportJob($session->id))->handle(
            app(GenerateAdvisorReport::class),
            app(PushNotification::class),
        );

        $this->assertDatabaseHas('notifications', [
            'type' => Notification::TYPE_ADVISOR_REPORT_FAILED,
            'level' => Notification::LEVEL_WARNING,
        ]);
    }
}
