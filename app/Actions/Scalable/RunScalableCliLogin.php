<?php

declare(strict_types=1);

namespace App\Actions\Scalable;

use App\Actions\Action;
use App\Http\Clients\ScalableCliClient;
use App\Models\ScalableConnection;
use App\Services\Scalable\ScalableLoginState;
use Illuminate\Contracts\Process\InvokedProcess;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Drive the official CLI's device-code login start to finish.
 *
 * `sc login` is interactive (human_only, no JSON) and blocks while polling for
 * the user's browser confirmation, so this runs in a queued job, not a request.
 * It captures the activation URL and user code from the CLI's plaintext stdout
 * as soon as they appear, surfaces them via ScalableLoginState for the UI to
 * poll, then waits for the process to exit and confirms the session with the
 * JSON-supported `whoami` — the login text itself is never trusted for success.
 */
class RunScalableCliLogin extends Action
{
    /** Matches "https://secure.scalable.capital/activate?user_code=XXXX-XXXX". */
    private const string URL_PATTERN = '#(https://\S*activate\S*user_code=([A-Z0-9-]+))#';

    public function __construct(
        private readonly ScalableLoginState $state,
        private readonly ScalableCliClient $cli,
    ) {}

    public function run(): void
    {
        $binary = Config::string('services.scalable.cli.binary', 'sc');
        $timeout = Config::integer('services.scalable.cli.login_timeout', 900);

        try {
            $process = Process::timeout($timeout)->start([$binary, 'login']);

            // Always wait for the child even if capture throws, so a stray
            // `sc login` is reaped rather than left polling until its timeout.
            try {
                $this->captureUrl($process);
            } finally {
                $result = $process->wait();
            }
        } catch (\Throwable $e) {
            Log::warning('Scalable CLI login failed', ['message' => $e->getMessage()]);
            $this->fail('Login non riuscito o scaduto. Riprova.');

            return;
        }

        // The login printed exit 0, but only a live whoami proves the session is
        // usable — the plaintext "logged in" line is not authoritative.
        if ($result->successful() && $this->cli->isLoggedIn()) {
            $this->state->markComplete();
            ScalableConnection::current()->recordSyncSuccess();

            return;
        }

        $this->fail('Login non confermato. Riprova ad aprire il link e inserire il codice.');
    }

    /**
     * Poll the process's stdout until the activation URL appears, then publish
     * it for the UI. Checks once more after the process exits so a fast emitter
     * isn't missed. A drift in the CLI's wording would leave the URL uncaptured,
     * so the raw output is logged in that case.
     */
    private function captureUrl(InvokedProcess $process): void
    {
        do {
            if (preg_match(self::URL_PATTERN, $process->latestOutput(), $matches) === 1) {
                $this->state->markUrlIssued($matches[1], $matches[2]);

                return;
            }

            if (! $process->running()) {
                break;
            }

            usleep(300_000);
        } while (true);

        Log::warning('Scalable CLI login produced no activation URL', ['output' => $process->latestOutput()]);
    }

    private function fail(string $message): void
    {
        $this->state->markFailed($message);
        ScalableConnection::current()->recordSyncFailure($message);
    }
}
