<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Advisor\GenerateAdvisorReport;
use App\Actions\Notifications\PushNotification;
use App\Models\AdvisorReport;
use App\Models\Notification;
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

    public function handle(GenerateAdvisorReport $generate, PushNotification $notify): void
    {
        $report = AdvisorReport::find($this->reportId);

        if ($report === null) {
            return;
        }

        $content = $generate->run();

        if ($content === null) {
            $report->update(['status' => AdvisorReport::STATUS_FAILED, 'error' => 'Consulente AI non configurato.']);
            $notify->run(
                type: Notification::TYPE_ADVISOR_REPORT_FAILED,
                level: Notification::LEVEL_WARNING,
                title: 'Analisi non generata',
                body: 'Il consulente AI non è configurato.',
                actionUrl: '/advisor',
            );

            return;
        }

        $report->update(['status' => AdvisorReport::STATUS_DONE, 'content' => $content]);
        $notify->run(
            type: Notification::TYPE_ADVISOR_REPORT_READY,
            level: Notification::LEVEL_SUCCESS,
            title: 'Analisi completata',
            body: 'Il consulente AI ha generato una nuova lettura del tuo portafoglio.',
            actionUrl: '/advisor',
        );
    }

    public function failed(\Throwable $exception): void
    {
        AdvisorReport::find($this->reportId)?->update([
            'status' => AdvisorReport::STATUS_FAILED,
            'error' => 'Generazione non riuscita. Verifica che il modello locale sia in esecuzione.',
        ]);

        app(PushNotification::class)->run(
            type: Notification::TYPE_ADVISOR_REPORT_FAILED,
            level: Notification::LEVEL_WARNING,
            title: 'Analisi non riuscita',
            body: 'Generazione fallita. Verifica che il modello locale sia in esecuzione.',
            actionUrl: '/advisor',
        );
    }
}
