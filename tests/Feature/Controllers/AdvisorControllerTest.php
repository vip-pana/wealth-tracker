<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Contracts\AdvisorProvider;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_generate_returns_the_report(): void
    {
        $this->bindProvider(configured: true, reply: 'Il tuo portafoglio è solido.');

        $this->postJson('/advisor/generate')
            ->assertOk()
            ->assertJson(['report' => 'Il tuo portafoglio è solido.']);
    }

    public function test_generate_422_when_not_configured(): void
    {
        $this->bindProvider(configured: false);

        $this->postJson('/advisor/generate')->assertStatus(422);
    }

    public function test_generate_502_on_provider_error(): void
    {
        $this->bindProvider(configured: true, throws: true);

        $this->postJson('/advisor/generate')->assertStatus(502);
    }
}
