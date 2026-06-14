<?php

declare(strict_types=1);

namespace App\Http\Clients;

use App\Contracts\AdvisorProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Advisor backed by a local model served by Ollama. The app runs in Docker and
 * Ollama on the host, so the base URL points at host.docker.internal by
 * default. Inert (isConfigured() = false) when no model is set, so the advisor
 * surface degrades gracefully instead of erroring.
 */
class OllamaAdvisorProvider implements AdvisorProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly int $timeout,
    ) {}

    public function isConfigured(): bool
    {
        return $this->model !== '';
    }

    public function analyze(array $context): string
    {
        $response = Http::timeout($this->timeout)
            ->post(rtrim($this->baseUrl, '/').'/api/chat', [
                'model' => $this->model,
                'stream' => false,
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $this->renderContext($context)],
                ],
            ]);

        if ($response->failed()) {
            Log::warning('Ollama advisor request failed', ['status' => $response->status()]);

            throw new \RuntimeException('Il modello locale non ha risposto. Verifica che Ollama sia in esecuzione.');
        }

        $content = $response->json('message.content');

        if (! is_string($content) || $content === '') {
            Log::warning('Ollama advisor returned an unexpected shape');

            throw new \RuntimeException('Risposta del modello locale non valida.');
        }

        return trim($content);
    }

    /**
     * The advisor's role and hard boundaries. Analyse, educate, and check the
     * portfolio against the user's own strategy — never recommend buying or
     * selling a specific security, and never call market direction or timing.
     */
    private function systemPrompt(): string
    {
        return <<<'PROMPT'
        Sei un consulente finanziario che analizza il portafoglio personale dell'utente.

        I numeri che ricevi sono già calcolati: NON fare aritmetica, interpreta e spiega.

        Cosa fai:
        - Leggi le metriche e spiega in italiano, in modo chiaro e onesto, cosa raccontano.
        - Evidenzia punti di forza, rischi (concentrazione, liquidità ferma, volatilità) e coerenza con la disciplina dell'utente (es. il PAC).
        - Educhi: spiega i concetti dietro i numeri quando serve.

        Sul profilo investitore: se è presente nei dati, usalo per valutare la coerenza (orizzonte, tolleranza al rischio, obiettivo, allocazione target). Se è assente o incompleto, NON inventarlo e NON assumere un profilo: dillo esplicitamente e invita a compilarlo per un'analisi più mirata.

        Cosa NON fai mai:
        - NON consigliare di comprare o vendere titoli o strumenti specifici.
        - NON prevedere l'andamento dei mercati né suggerire il momento per entrare/uscire.
        - NON inventare numeri non presenti nei dati forniti, né un profilo non fornito.

        Scrivi in italiano, conciso e concreto. Niente disclaimer generici ripetitivi.
        PROMPT;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function renderContext(array $context): string
    {
        $json = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return "Ecco le metriche attuali del mio portafoglio:\n\n".($json !== false ? $json : '{}')
            ."\n\nAnalizzale e dammi una lettura del mio portafoglio.";
    }
}
