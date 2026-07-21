<?php

declare(strict_types=1);

namespace App\Http\Clients;

use App\Models\Transaction;
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
class ScalableCliClient
{
    /**
     * The only commands this client may run. Callers pass a key, never raw argv,
     * so a trade command is structurally unreachable.
     *
     * @var array<string, list<string>>
     */
    private const array COMMANDS = [
        'overview' => ['broker', 'overview', '--json'],
        'holdings' => ['broker', 'holdings', '--json'],
        'transactions' => ['broker', 'transactions', '--json'],
        'whoami' => ['whoami', '--json'],
        'logout' => ['logout', '--json'],
    ];

    /** Maximum page size the CLI accepts for `broker transactions`. */
    private const int TRANSACTIONS_PAGE_SIZE = 100;

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
     * One page of settled buy/sell transactions from `broker transactions`,
     * newest first, each normalised for import. Only BUY, SELL and SAVINGS_PLAN
     * (the PAC) types are requested; the per-share price is derived as
     * |amount| / quantity since the CLI gives a EUR amount, not a unit price.
     * Returns null on any failure. `cursor` (null = first page) and the returned
     * `next_cursor` (null = no more pages) drive pagination by the caller.
     *
     * @return array{items: list<array{external_id: string, isin: string, name: string, type: string, source: string, shares: float, price_per_share: float, date: string}>, next_cursor: string|null}|null
     */
    public function transactions(?string $cursor = null): ?array
    {
        $args = [
            '--type-filter', 'BUY',
            '--type-filter', 'SELL',
            '--type-filter', 'SAVINGS_PLAN',
            '--status', 'SETTLED',
            '--page-size', (string) self::TRANSACTIONS_PAGE_SIZE,
        ];

        if ($cursor !== null) {
            $args[] = '--cursor';
            $args[] = $cursor;
        }

        $data = $this->runJson('transactions', $args);

        if ($data === null) {
            return null;
        }

        $result = $data['result'] ?? null;
        $items = is_array($result) ? ($result['items'] ?? null) : null;

        if (! is_array($items)) {
            Log::warning('Scalable CLI unexpected transactions shape');

            return null;
        }

        $nextCursor = $result['cursor'] ?? null;

        $normalised = [];

        foreach ($items as $item) {
            $transaction = $this->normaliseTransaction($item);

            if ($transaction !== null) {
                $normalised[] = $transaction;
            }
        }

        return [
            'items' => $normalised,
            'next_cursor' => is_string($nextCursor) ? $nextCursor : null,
        ];
    }

    /**
     * Map one raw transaction item to our shape, or null if it can't be used
     * (missing fields, zero quantity, unknown side). A negative `amount` is a
     * buy outflow; price per share is the absolute amount over the quantity.
     *
     * @param  mixed  $item
     * @return array{external_id: string, isin: string, name: string, type: string, source: string, shares: float, price_per_share: float, date: string}|null
     */
    private function normaliseTransaction($item): ?array
    {
        if (! is_array($item)) {
            return null;
        }

        $id = $item['id'] ?? null;
        $isin = $item['isin'] ?? null;
        $name = $item['description'] ?? null;
        $quantity = $item['quantity'] ?? null;
        $amount = $item['amount'] ?? null;
        $side = $item['side'] ?? null;
        $datetime = $item['last_event_datetime'] ?? null;

        if (! is_string($id) || ! is_string($isin) || ! is_string($name)
            || ! is_numeric($quantity) || ! is_numeric($amount) || ! is_string($datetime)) {
            Log::warning('Scalable CLI unexpected transaction shape');

            return null;
        }

        $shares = (float) $quantity;

        if ($shares <= 0.0) {
            return null;
        }

        $type = match ($side) {
            'BUY' => Transaction::TYPE_BUY,
            'SELL' => Transaction::TYPE_SELL,
            default => null,
        };

        if ($type === null) {
            Log::warning('Scalable CLI unknown transaction side', ['side' => $side]);

            return null;
        }

        // SAVINGS_PLAN is the recurring PAC order; everything else (SINGLE, …)
        // is a one-off buy/sell. Anything unrecognised falls back to single.
        $source = $item['security_transaction_type'] === 'SAVINGS_PLAN'
            ? Transaction::SOURCE_SAVINGS_PLAN
            : Transaction::SOURCE_SINGLE;

        return [
            'external_id' => $id,
            'isin' => $isin,
            'name' => $name,
            'type' => $type,
            'source' => $source,
            'shares' => $shares,
            'price_per_share' => abs((float) $amount) / $shares,
            'date' => substr($datetime, 0, 10),
        ];
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
     * @param  list<string>  $extraArgs  filters/pagination this client builds itself;
     *                                   callers pass a command key, never raw argv,
     *                                   so a `trade` command stays unreachable
     * @return array<mixed>|null
     */
    private function runJson(string $command, array $extraArgs = []): ?array
    {
        if (! $this->enabled) {
            return null;
        }

        try {
            $result = Process::timeout($this->timeout)->run([$this->binary, ...self::COMMANDS[$command], ...$extraArgs]);
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
