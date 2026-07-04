<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Actions\Advisor\BuildAdvisorContext;
use App\Advisor\Tools\AdvisorToolActivityReporter;
use App\Advisor\Tools\AdvisorToolFactory;
use App\Http\Clients\PrismAdvisorProvider;
use Illuminate\Support\Facades\Http;
use Mockery;
use Prism\Prism\Enums\Provider;
use Tests\TestCase;

class PrismAdvisorProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Prism talks to Ollama's native endpoint; point it at a known base.
        config()->set('prism.providers.ollama.url', 'http://ollama.test');
    }

    private function provider(string $model = 'qwen3:8b'): PrismAdvisorProvider
    {
        $build = Mockery::mock(BuildAdvisorContext::class);
        $build->shouldReceive('run')->andReturn(['portfolio' => ['hasData' => false], 'positionReturns' => null]);

        return new PrismAdvisorProvider(Provider::Ollama, $model, 120, new AdvisorToolFactory($build, new AdvisorToolActivityReporter));
    }

    public function test_is_not_configured_without_a_model(): void
    {
        $this->assertFalse($this->provider('')->isConfigured());
        $this->assertTrue($this->provider()->isConfigured());
    }

    public function test_returns_the_model_message_content_trimmed(): void
    {
        Http::fake([
            '*/api/chat' => Http::response([
                'message' => ['role' => 'assistant', 'content' => '  Il tuo portafoglio è ben distribuito.  '],
                'done' => true,
            ]),
        ]);

        $text = $this->provider()->chat([
            ['role' => 'system', 'content' => 'sei un consulente'],
            ['role' => 'user', 'content' => 'come va?'],
        ]);

        $this->assertSame('Il tuo portafoglio è ben distribuito.', $text);
    }

    public function test_strips_the_internal_guillemets_from_the_reply(): void
    {
        Http::fake([
            '*/api/chat' => Http::response([
                'message' => ['content' => 'Il tuo «iShares MSCI ACWI» rende bene.'],
                'done' => true,
            ]),
        ]);

        $text = $this->provider()->chat([['role' => 'user', 'content' => 'x']]);

        $this->assertStringNotContainsString('«', $text);
        $this->assertStringNotContainsString('»', $text);
        $this->assertStringContainsString('iShares MSCI ACWI', $text);
    }

    public function test_sends_the_system_prompt_and_conversation_to_the_model(): void
    {
        Http::fake(['*/api/chat' => Http::response(['message' => ['content' => 'ok'], 'done' => true])]);

        $this->provider()->chat([
            ['role' => 'system', 'content' => 'NON raccomandare strumenti'],
            ['role' => 'user', 'content' => 'ALLOCAZIONE: Azioni 47%.'],
        ]);

        Http::assertSent(function ($request): bool {
            $body = $request->data();
            $roles = array_column($body['messages'], 'role');
            $contents = implode(' ', array_column($body['messages'], 'content'));

            return $body['model'] === 'qwen3:8b'
                && $body['stream'] === false
                && in_array('system', $roles, true)
                && str_contains($contents, 'NON raccomandare strumenti')
                && str_contains($contents, 'Azioni');
        });
    }

    public function test_exposes_the_advisor_tools_to_the_model(): void
    {
        Http::fake(['*/api/chat' => Http::response(['message' => ['content' => 'ok'], 'done' => true])]);

        $this->provider()->chat([['role' => 'user', 'content' => 'x']]);

        Http::assertSent(function ($request): bool {
            $names = array_column(array_column($request->data()['tools'] ?? [], 'function'), 'name');

            return in_array('get_position', $names, true)
                && in_array('get_portfolio_summary', $names, true)
                && in_array('simulate_pac', $names, true)
                && in_array('net_worth_between', $names, true);
        });
    }

    public function test_throws_on_a_failed_request(): void
    {
        Http::fake(['*/api/chat' => Http::response('', 500)]);

        $this->expectException(\RuntimeException::class);
        $this->provider()->chat([['role' => 'user', 'content' => 'x']]);
    }

    public function test_retries_once_when_the_first_reply_is_empty(): void
    {
        // A local model intermittently returns an empty body; one retry recovers.
        Http::fakeSequence('*/api/chat')
            ->push(['message' => ['content' => '   '], 'done' => true])
            ->push(['message' => ['content' => 'Risposta valida.'], 'done' => true]);

        $this->assertSame('Risposta valida.', $this->provider()->chat([['role' => 'user', 'content' => 'x']]));
    }

    public function test_throws_when_empty_twice(): void
    {
        Http::fake(['*/api/chat' => Http::response(['message' => ['content' => ''], 'done' => true])]);

        $this->expectException(\RuntimeException::class);
        $this->provider()->chat([['role' => 'user', 'content' => 'x']]);
    }
}
