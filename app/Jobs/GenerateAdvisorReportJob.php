<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Advisor\GenerateAdvisorReport;
use App\Actions\Notifications\PushNotification;
use App\Advisor\AdvisorBudget;
use App\Advisor\AdvisorBudgetExceeded;
use App\Models\AdvisorMessage;
use App\Models\AdvisorSession;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Generates a report session's opening analysis in the background, so the web
 * request returns immediately and the user can navigate away (or close the
 * tab) while a local model takes its time. The analysis is stored as the
 * session's first assistant message and the session status flips to done; the
 * UI polls it. One attempt: a stale half-analysis isn't worth retrying.
 */
class GenerateAdvisorReportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        private readonly int $sessionId,
    ) {}

    public function handle(GenerateAdvisorReport $generate, PushNotification $notify, AdvisorBudget $budget): void
    {
        $session = AdvisorSession::find($this->sessionId);

        if ($session === null) {
            return;
        }

        // Account the analysis to its own session, so the ledger attributes the
        // spend. One call, so the per-session cap is not the binding one here —
        // the daily cap is.
        $budget->for($session);

        try {
            $content = $generate->run();
        } catch (AdvisorBudgetExceeded $e) {
            // The generic failed() handler below blames the local model. Here
            // the model is fine and was never asked, so say what actually
            // happened — otherwise the owner goes debugging a working model.
            $session->update(['status' => AdvisorSession::STATUS_FAILED, 'error' => $e->getMessage()]);
            $notify->run(
                type: Notification::TYPE_ADVISOR_REPORT_FAILED,
                level: Notification::LEVEL_WARNING,
                title: 'Analisi non generata',
                body: $e->getMessage(),
                actionUrl: '/advisor',
            );

            return;
        } finally {
            $budget->clear();
        }

        if ($content === null) {
            $session->update(['status' => AdvisorSession::STATUS_FAILED, 'error' => 'Consulente AI non configurato.']);
            $notify->run(
                type: Notification::TYPE_ADVISOR_REPORT_FAILED,
                level: Notification::LEVEL_WARNING,
                title: 'Analisi non generata',
                body: 'Il consulente AI non è configurato.',
                actionUrl: '/advisor',
            );

            return;
        }

        AdvisorMessage::create([
            'session_id' => $session->id,
            'role' => AdvisorMessage::ROLE_ASSISTANT,
            'content' => $content,
        ]);
        $session->update(['status' => AdvisorSession::STATUS_DONE]);

        $notify->run(
            type: Notification::TYPE_ADVISOR_REPORT_READY,
            level: Notification::LEVEL_SUCCESS,
            title: 'Analisi completata',
            body: 'Il consulente AI ha generato una nuova lettura del tuo portafoglio.',
            actionUrl: '/advisor/'.$session->id,
        );
    }

    public function failed(\Throwable $exception): void
    {
        AdvisorSession::find($this->sessionId)?->update([
            'status' => AdvisorSession::STATUS_FAILED,
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
