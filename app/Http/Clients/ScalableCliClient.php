<?php

declare(strict_types=1);

namespace App\Http\Clients;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Client for the official Scalable Capital CLI (`sc`).
 *
 * The CLI runs headless in the container with a file-backed session (config.toml
 * `[auth] session_backend = "file"`). This client only ever invokes the commands
 * in self::COMMANDS — read queries plus logout — there is no code path that
 * builds a `trade` command. Read methods return null on any failure (disabled,
 * no session, non-zero exit, unexpected shape) so the caller leaves stored
 * values untouched.
 */
class ScalableCliClient implements ScalableSource
{
    /**
     * The only commands this client may run. Callers pass a key, never raw argv,
     * so a trade command is structurally unreachable.
     *
     * @var array<string, list<string>>
     */
    private const COMMANDS = [
        'overview' => ['broker', 'overview', '--json'],
        'holdings' => ['broker', 'holdings', '--json'],
        'whoami' => ['whoami', '--json'],
        'logout' => ['logout', '--json'],
    ];

    public function __construct(
        private readonly bool $enabled,
        private readonly string $binary,
        private readonly int $timeout,
    ) {}

    /**
     * Current positions from `broker holdings --json`, each with its EUR
     * valuation. Returns null on any failure.
     *
     * @return list<array{isin: string, name: string, value: float}>|null
     */
    public function positions(): ?array
    {
        $data = $this->runJson('holdings');

        if ($data === null) {
            return null;
        }

        $result = $data['result'] ?? null;
        $items = is_array($result) ? ($result['items'] ?? null) : null;

        if (! is_array($items)) {
            Log::warning('Scalable CLI unexpected holdings shape');

            return null;
        }

        $positions = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $isin = $item['isin'] ?? null;
            $name = $item['name'] ?? null;
            $value = $item['valuation'] ?? null;

            if (! is_string($isin) || ! is_string($name) || ! is_numeric($value)) {
                Log::warning('Scalable CLI unexpected position shape');

                continue;
            }

            $positions[] = [
                'isin' => $isin,
                'name' => $name,
                'value' => (float) $value,
            ];
        }

        return $positions;
    }

    /**
     * The uninvested cash balance in EUR, derived from `broker overview --json`
     * as total minus securities minus crypto (cash is not a line item). Returns
     * null on any failure.
     */
    public function cashBalance(): ?float
    {
        $data = $this->runJson('overview');

        if ($data === null) {
            return null;
        }

        $result = $data['result'] ?? null;
        $valuation = is_array($result) ? ($result['valuation'] ?? null) : null;
        $total = is_array($valuation) ? ($valuation['total'] ?? null) : null;
        $securities = is_array($valuation) ? ($valuation['securities'] ?? null) : null;
        $crypto = is_array($valuation) ? ($valuation['crypto'] ?? null) : null;

        if (! is_numeric($total) || ! is_numeric($securities) || ! is_numeric($crypto)) {
            Log::warning('Scalable CLI unexpected overview shape');

            return null;
        }

        return (float) $total - (float) $securities - (float) $crypto;
    }

    /**
     * Whether the CLI has a live session, probed cheaply via `whoami --json`.
     */
    public function isLoggedIn(): bool
    {
        return $this->runJson('whoami') !== null;
    }

    /**
     * Remove the saved session via `logout --json`. Returns true if the CLI
     * cleared it. Forgets the cached isLoggedIn() result so the UI reflects the
     * change immediately rather than after the cache window.
     */
    public function logout(): bool
    {
        $cleared = $this->runJson('logout') !== null;
        Cache::forget('scalable.cli.logged_in');

        return $cleared;
    }

    /**
     * Run an allowlisted command and return its `data` payload, or null on any
     * failure. The `sc` binary may be absent on a fresh machine — a real runtime
     * scenario, like the proxy's unreachable host — so the Process call is
     * guarded; an expired session surfaces as `ok:false` with `no_session`.
     *
     * @return array<mixed>|null
     */
    private function runJson(string $command): ?array
    {
        if (! $this->enabled) {
            return null;
        }

        try {
            $result = Process::timeout($this->timeout)->run([$this->binary, ...self::COMMANDS[$command]]);
        } catch (\Throwable $e) {
            Log::warning('Scalable CLI invocation failed', ['command' => $command, 'message' => $e->getMessage()]);

            return null;
        }

        if ($result->failed()) {
            Log::warning('Scalable CLI command failed', ['command' => $command, 'exit' => $result->exitCode()]);

            return null;
        }

        $json = json_decode($result->output(), true);

        if (! is_array($json)) {
            Log::warning('Scalable CLI unexpected output', ['command' => $command]);

            return null;
        }

        if (($json['ok'] ?? null) !== true) {
            $error = $json['error'] ?? null;
            $code = is_array($error) ? ($error['code'] ?? null) : null;
            Log::warning('Scalable CLI returned an error', ['command' => $command, 'code' => $code]);

            return null;
        }

        $data = $json['data'] ?? null;

        return is_array($data) ? $data : null;
    }
}
