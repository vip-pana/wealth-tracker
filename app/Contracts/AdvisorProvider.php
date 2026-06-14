<?php

declare(strict_types=1);

namespace App\Contracts;

interface AdvisorProvider
{
    /**
     * Produce a written analysis of the portfolio from pre-computed metrics.
     *
     * The metrics are already calculated in PHP — the provider's job is to
     * interpret and narrate them, never to do arithmetic. Implementations may
     * be backed by a local model (Ollama) or a cloud one (Claude); the caller
     * doesn't care which.
     *
     * @param  array<string, mixed>  $context  the pre-computed advisor context
     * @return string the analysis text
     */
    public function analyze(array $context): string;

    /**
     * Whether the provider is configured and usable. False makes the advisor
     * surface report itself as unavailable rather than erroring.
     */
    public function isConfigured(): bool;
}
