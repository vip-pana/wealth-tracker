<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Advisor\GenerateAdvisorReport;
use App\Models\AdvisorReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Generates the advisor report in the background so the web request returns
 * immediately and the user can navigate away (or close the tab) while a local
 * model takes its time. The result is persisted on the report row — the UI
 * polls its status. One attempt: a stale half-analysis isn't worth retrying.
 */
class GenerateAdvisorReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        private readonly int $reportId,
    ) {}

    public function handle(GenerateAdvisorReport $generate): void
    {
        $report = AdvisorReport::find($this->reportId);

        if ($report === null) {
            return;
        }

        $content = $generate->run();

        if ($content === null) {
            $report->update(['status' => AdvisorReport::STATUS_FAILED, 'error' => 'Consulente AI non configurato.']);

            return;
        }

        $report->update(['status' => AdvisorReport::STATUS_DONE, 'content' => $content]);
    }

    public function failed(\Throwable $exception): void
    {
        AdvisorReport::find($this->reportId)?->update([
            'status' => AdvisorReport::STATUS_FAILED,
            'error' => 'Generazione non riuscita. Verifica che il modello locale sia in esecuzione.',
        ]);
    }
}
