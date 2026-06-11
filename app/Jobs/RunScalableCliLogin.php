<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Scalable\RunScalableCliLogin as RunScalableCliLoginAction;
use App\Services\Scalable\ScalableLoginState;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs the blocking `sc login` device-code flow in the queue worker so the web
 * request (and the single-threaded dev server) never stall on it. One attempt
 * only — re-running would spawn a second login with a different code.
 */
class RunScalableCliLogin implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function handle(RunScalableCliLoginAction $action): void
    {
        $action->run();
    }

    public function failed(\Throwable $exception): void
    {
        app(ScalableLoginState::class)->markFailed('Login interrotto. Riprova.');
    }
}
