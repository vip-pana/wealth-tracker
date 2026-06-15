<?php

declare(strict_types=1);

namespace App\Contracts;

interface AdvisorProvider
{
    /**
     * Produce a written analysis from an already-rendered context briefing.
     *
     * The caller renders the pre-computed metrics into an annotated text
     * briefing (see RenderAdvisorContext) — the provider only sends it to its
     * model with the system prompt and returns the reply. Implementations may
     * be backed by a local model (Ollama) or a cloud one (Claude); the caller
     * doesn't care which.
     *
     * @param  string  $briefing  the rendered, annotated context
     * @return string the analysis text
     */
    public function analyze(string $briefing): string;

    /**
     * Hold a multi-turn conversation. The caller passes the full message list
     * to send (a leading system message, then alternating user/assistant
     * turns); the provider sends them to its model and returns the assistant's
     * reply. Used by the chat surface, where the report is just the first
     * assistant turn and the user keeps asking follow-ups.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @return string the assistant reply
     */
    public function chat(array $messages): string;

    /**
     * Whether the provider is configured and usable. False makes the advisor
     * surface report itself as unavailable rather than erroring.
     */
    public function isConfigured(): bool;
}
