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

        $text = $this->provider()->analyze('Rendimento reale: +22%.');

        $this->assertSame('Il tuo portafoglio è ben distribuito.', $text); // trimmed
    }

    public function test_strips_the_internal_guillemets_from_the_reply(): void
    {
        Http::fake([
            '*/api/chat' => Http::response([
                'message' => ['content' => 'Il tuo «iShares MSCI ACWI» rende bene.'],
            ]),
        ]);

        // The «» data-markers must not leak into the visible reply.
        $text = $this->provider()->analyze('briefing');

        $this->assertStringNotContainsString('«', $text);
        $this->assertStringNotContainsString('»', $text);
        $this->assertStringContainsString('iShares MSCI ACWI', $text);
    }

    public function test_sends_system_prompt_and_context_to_ollama(): void
    {
        Http::fake([
            '*/api/chat' => Http::response(['message' => ['content' => 'ok']]),
        ]);

        $this->provider()->analyze('ALLOCAZIONE: Azioni 47%.');

        Http::assertSent(function ($request): bool {
            $body = $request->data();
            $messages = $body['messages'] ?? [];

            return $body['model'] === 'qwen2.5:14b'
                && $body['stream'] === false
                && $messages[0]['role'] === 'system'
                && str_contains((string) $messages[0]['content'], 'NON raccomandare strumenti')
                && str_contains((string) $messages[1]['content'], 'Azioni');
        });
    }

    public function test_chat_sends_the_full_message_list_verbatim(): void
    {
        Http::fake([
            '*/api/chat' => Http::response(['message' => ['content' => 'risposta']]),
        ]);

        $messages = [
            ['role' => 'system', 'content' => 'sei un consulente'],
            ['role' => 'user', 'content' => 'ho troppa liquidità?'],
        ];

        $reply = $this->provider()->chat($messages);

        $this->assertSame('risposta', $reply);
        Http::assertSent(function ($request) use ($messages): bool {
            $body = $request->data();

            return $body['stream'] === false && $body['messages'] === $messages;
        });
    }

    public function test_chat_stream_forwards_each_ndjson_delta_and_returns_the_full_reply(): void
    {
        // Ollama streams one JSON object per line.
        $ndjson = implode("\n", [
            json_encode(['message' => ['content' => 'Il tuo ']]),
            json_encode(['message' => ['content' => '«ETF» ']]),
            json_encode(['message' => ['content' => 'va bene.'], 'done' => true]),
        ])."\n";

        Http::fake(['*/api/chat' => Http::response($ndjson)]);

        $chunks = [];
        $full = $this->provider()->chatStream(
            [['role' => 'user', 'content' => 'come va?']],
            function (string $c) use (&$chunks): void {
                $chunks[] = $c;
            },
        );

        // Each delta was forwarded, and the «» were normalised on the way out.
        $this->assertSame(['Il tuo ', '“ETF” ', 'va bene.'], $chunks);
        $this->assertSame('Il tuo “ETF” va bene.', $full);
    }

    public function test_chat_stream_throws_on_an_empty_stream(): void
    {
        Http::fake(['*/api/chat' => Http::response('')]);

        $this->expectException(\RuntimeException::class);
        $this->provider()->chatStream([['role' => 'user', 'content' => 'x']], function (): void {});
    }

    public function test_throws_on_a_failed_request(): void
    {
        Http::fake(['*/api/chat' => Http::response('', 500)]);

        $this->expectException(\RuntimeException::class);
        $this->provider()->analyze('briefing');
    }

    public function test_throws_on_an_empty_response(): void
    {
        Http::fake(['*/api/chat' => Http::response(['message' => ['content' => '']])]);

        $this->expectException(\RuntimeException::class);
        $this->provider()->analyze('briefing');
    }

    public function test_retries_once_when_the_first_reply_is_empty(): void
    {
        // A local model intermittently returns an empty body; one retry recovers.
        Http::fakeSequence('*/api/chat')
            ->push(['message' => ['content' => '   ']])
            ->push(['message' => ['content' => 'Risposta valida.']]);

        $this->assertSame('Risposta valida.', $this->provider()->analyze('briefing'));
    }
}
