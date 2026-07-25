<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Advisor\GenerateAdvisorReport;
use App\Contracts\AdvisorProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateAdvisorReportTest extends TestCase
{
    use RefreshDatabase;

    private function fakeProvider(bool $configured, string $reply = 'analisi'): AdvisorProvider
    {
        return new readonly class($configured, $reply) implements AdvisorProvider
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
        };
    }

    public function test_returns_null_when_provider_not_configured(): void
    {
        $this->app->instance(AdvisorProvider::class, $this->fakeProvider(configured: false));

        $this->assertNull(app(GenerateAdvisorReport::class)->run());
    }

    public function test_returns_the_provider_analysis_when_configured(): void
    {
        $this->app->instance(AdvisorProvider::class, $this->fakeProvider(configured: true, reply: 'Il tuo portafoglio è solido.'));

        $this->assertSame('Il tuo portafoglio è solido.', app(GenerateAdvisorReport::class)->run());
    }
}
