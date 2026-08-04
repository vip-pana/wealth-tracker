<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property string $level
 * @property string $title
 * @property string|null $body
 * @property string|null $action_url
 * @property string|null $dedupe_key
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Notification extends Model
{
    public const LEVEL_INFO = 'info';

    public const LEVEL_SUCCESS = 'success';

    public const LEVEL_WARNING = 'warning';

    // Machine types. One-shot events leave dedupe_key null; recurring state
    // conditions pass a stable dedupe_key so they never pile up.
    public const TYPE_ADVISOR_REPORT_READY = 'advisor_report_ready';

    public const TYPE_ADVISOR_REPORT_FAILED = 'advisor_report_failed';

    public const TYPE_ADVISOR_CHAT_READY = 'advisor_chat_ready';

    public const TYPE_SCALABLE_SYNC_FAILED = 'scalable_sync_failed';

    public const TYPE_BANK_CONSENT_EXPIRED = 'bank_consent_expired';

    public const TYPE_SNAPSHOT_SKIPPED = 'snapshot_skipped';

    public const TYPE_BACKUP_FAILED = 'backup_failed';

    public const TYPE_BACKUP_STALE = 'backup_stale';

    protected $fillable = ['type', 'level', 'title', 'body', 'action_url', 'dedupe_key', 'read_at'];

    #[\Override]
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    /** @param  Builder<Notification>  $query */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    public function markRead(): void
    {
        if ($this->read_at === null) {
            $this->update(['read_at' => Carbon::now()]);
        }
    }
}
