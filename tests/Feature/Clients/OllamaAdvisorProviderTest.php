<?php

declare(strict_types=1);

namespace Tests\Feature\Clients;

use App\Http\Clients\OllamaAdvisorProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaAdvisorProviderTest extends TestCase
{
    private function provider(string $model = 'qwen2.5:14b'): OllamaAdvisorProvider
    {
        return new OllamaAdvisorProvider('http://host.docker.internal:11434', $model, 120);
    }

    public function test_is_not_configured_without_a_model(): void
    {
        $this->assertFalse($this->provider('')->isConfigured());
        $this->assertTrue($this->provider()->isConfigured());
    }

    public function test_returns_the_model_message_content(): void
    {
        Http::fake([
            '*/api/chat' => Http::response([
                'message' => ['role' => 'assistant', 'content' => '  Il tuo portafoglio è ben distribuito.  '],
                'done' => true,
            ]),
        ]);

        $text = $this->provider()->analyze(['totalNetWorth' => 32666.39]);

        $this->assertSame('Il tuo portafoglio è ben distribuito.', $text); // trimmed
    }

    public function test_sends_system_prompt_and_context_to_ollama(): void
    {
        Http::fake([
            '*/api/chat' => Http::response(['message' => ['content' => 'ok']]),
        ]);

        $this->provider()->analyze(['concentration' => ['top_category' => 'Azioni']]);

        Http::assertSent(function ($request): bool {
            $body = $request->data();
            $messages = $body['messages'] ?? [];

            return $body['model'] === 'qwen2.5:14b'
                && $body['stream'] === false
                && $messages[0]['role'] === 'system'
                && str_contains($messages[0]['content'], 'NON consigliare di comprare o vendere')
                && str_contains($messages[1]['content'], 'Azioni');
        });
    }

    public function test_throws_on_a_failed_request(): void
    {
        Http::fake(['*/api/chat' => Http::response('', 500)]);

        $this->expectException(\RuntimeException::class);
        $this->provider()->analyze([]);
    }

    public function test_throws_on_an_empty_response(): void
    {
        Http::fake(['*/api/chat' => Http::response(['message' => ['content' => '']])]);

        $this->expectException(\RuntimeException::class);
        $this->provider()->analyze([]);
    }
}
