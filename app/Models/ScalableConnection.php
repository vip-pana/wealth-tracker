<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Global state of the Scalable broker sync (a single row). The connection
 * health — host proxy reachable, 8h session valid — is not per-asset, so the
 * outcome of the last sync lives here and surfaces in Settings.
 *
 * @property int $id
 * @property string|null $last_sync_status
 * @property string|null $last_sync_error
 * @property Carbon|null $last_sync_at
 */
class ScalableConnection extends Model
{
    public const SYNC_OK = 'ok';

    public const SYNC_FAILED = 'failed';

    protected $fillable = ['last_sync_status', 'last_sync_error', 'last_sync_at'];

    protected $casts = [
        'last_sync_at' => 'datetime',
    ];

    /** The singleton state row, created on first access. */
    public static function current(): self
    {
        return self::firstOrCreate([]);
    }

    public function recordSyncSuccess(): void
    {
        $this->update([
            'last_sync_status' => self::SYNC_OK,
            'last_sync_at' => Carbon::now(),
            'last_sync_error' => null,
        ]);
    }

    public function recordSyncFailure(string $error): void
    {
        $this->update([
            'last_sync_status' => self::SYNC_FAILED,
            'last_sync_at' => Carbon::now(),
            'last_sync_error' => $error,
        ]);
    }
}
