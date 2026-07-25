<?php

declare(strict_types=1);

namespace App\Actions\Advisor;

use App\Actions\Action;
use App\Contracts\AdvisorProvider;

class GenerateAdvisorReport extends Action
{
    public function __construct(
        private readonly BuildAdvisorContext $buildAdvisorContext,
        private readonly RenderAdvisorContext $renderAdvisorContext,
        private readonly AdvisorProvider $provider,
    ) {}

    /**
     * Generate the written portfolio analysis. Returns null when the advisor
     * isn't configured (no model set) so the caller can show an unavailable
     * state instead of erroring. The structured metrics are rendered into an
     * annotated briefing before reaching the provider.
     */
    public function run(): ?string
    {
        if (! $this->provider->isConfigured()) {
            return null;
        }

        $briefing = $this->renderAdvisorContext->run($this->buildAdvisorContext->run());

        return $this->provider->analyze($briefing);
    }
}
