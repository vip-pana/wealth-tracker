<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Actions\Action;
use App\Models\Notification;

class PushNotification extends Action
{
    /**
     * Create a notification. When a dedupe_key is given, a recurring state
     * condition reuses its single unread row instead of stacking duplicates:
     * if an unread notification with that key already exists, this is a no-op
     * (the nightly sync can fail every day and the bell shows one item, not
     * seven). One-shot events pass dedupe_key = null and always create a row.
     */
    public function run(
        string $type,
        string $level,
        string $title,
        ?string $body = null,
        ?string $actionUrl = null,
        ?string $dedupeKey = null,
    ): Notification {
        if ($dedupeKey !== null) {
            $existing = Notification::query()
                ->unread()
                ->where('dedupe_key', $dedupeKey)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        return Notification::create([
            'type' => $type,
            'level' => $level,
            'title' => $title,
            'body' => $body,
            'action_url' => $actionUrl,
            'dedupe_key' => $dedupeKey,
        ]);
    }

    /**
     * Auto-dismiss the notification(s) for a resolved condition: mark every
     * unread row with this dedupe_key as read. Called when the underlying
     * problem clears (e.g. the broker is reconnected), so the bell stops
     * showing a stale warning without the user clearing it by hand.
     */
    public function resolve(string $dedupeKey): void
    {
        Notification::query()
            ->unread()
            ->where('dedupe_key', $dedupeKey)
            ->each(fn (Notification $n) => $n->markRead());
    }
}
