<?php

declare(strict_types=1);

namespace App\Services\Scalable;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Ephemeral state of an in-app Scalable CLI login, shared between the queue
 * worker that runs `sc login` and the web requests that poll for its progress.
 * Lives in the cache (not scalable_connections, which tracks sync health) with a
 * TTL so a crashed worker's "pending" self-expires instead of spinning forever.
 */
class ScalableLoginState
{
    private const string KEY = 'scalable.login';

    /** A device code expires in minutes; bound the whole flow well past that. */
    private const int TTL_MINUTES = 20;

    public const PENDING = 'pending';

    public const URL_ISSUED = 'url_issued';

    public const COMPLETE = 'complete';

    public const FAILED = 'failed';

    public function markPending(): void
    {
        $this->put(['status' => self::PENDING, 'started_at' => Carbon::now()->toISOString()]);
    }

    public function markUrlIssued(string $url, string $userCode): void
    {
        $snapshot = $this->snapshot();
        $snapshot['status'] = self::URL_ISSUED;
        $snapshot['url'] = $url;
        $snapshot['user_code'] = $userCode;
        $this->put($snapshot);
    }

    public function markComplete(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['status'] = self::COMPLETE;
        $snapshot['error'] = null;
        $this->put($snapshot);
    }

    public function markFailed(string $error): void
    {
        $snapshot = $this->snapshot();
        $snapshot['status'] = self::FAILED;
        $snapshot['error'] = $error;
        $this->put($snapshot);
    }

    /**
     * @return array{status: string, url: string|null, user_code: string|null, error: string|null, started_at: string|null}
     */
    public function snapshot(): array
    {
        /** @var array{status?: string, url?: string|null, user_code?: string|null, error?: string|null, started_at?: string|null} $stored */
        $stored = Cache::get(self::KEY, []);

        return [
            'status' => $stored['status'] ?? 'idle',
            'url' => $stored['url'] ?? null,
            'user_code' => $stored['user_code'] ?? null,
            'error' => $stored['error'] ?? null,
            'started_at' => $stored['started_at'] ?? null,
        ];
    }

    /**
     * Whether a login is live: pending or awaiting confirmation, and started
     * recently enough that it isn't an orphan from a crashed worker.
     */
    public function isInProgress(): bool
    {
        $snapshot = $this->snapshot();

        if (! in_array($snapshot['status'], [self::PENDING, self::URL_ISSUED], true)) {
            return false;
        }

        $startedAt = $snapshot['started_at'];

        return is_string($startedAt) && Carbon::parse($startedAt)->isAfter(Carbon::now()->subMinutes(self::TTL_MINUTES));
    }

    public function clear(): void
    {
        Cache::forget(self::KEY);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function put(array $snapshot): void
    {
        Cache::put(self::KEY, $snapshot, Carbon::now()->addMinutes(self::TTL_MINUTES));
    }
}
