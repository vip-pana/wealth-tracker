<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Notifications\PushNotification;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reports when the deployed build is behind the repository. It deliberately
 * does not update anything: pulling and rebuilding unattended would, sooner or
 * later, deploy a change that needs a new .env key and leave the app down until
 * someone noticed. Deciding when to update stays with the person who can also
 * read the commit messages.
 */
class CheckForUpdates extends Command
{
    public const AVAILABLE_KEY = 'update_available';

    protected $signature = 'updates:check';

    protected $description = 'Notify when the deployed build is behind the repository';

    public function handle(PushNotification $notify): int
    {
        /** @var string|null $repository */
        $repository = config('updates.repository');
        /** @var string|null $current */
        $current = config('updates.commit');

        if ($repository === null || $repository === '') {
            $this->line('Update checking is disabled.');

            return Command::SUCCESS;
        }

        // An image built without the GIT_COMMIT arg cannot know what it is
        // running, and a wrong answer is worse than none.
        if (in_array($current, [null, '', 'unknown'], true)) {
            $this->line('This build carries no commit stamp; nothing to compare.');

            return Command::SUCCESS;
        }

        /** @var string $branch */
        $branch = config('updates.branch');

        try {
            $behind = $this->commitsBehind($repository, $branch, $current);
        } catch (Throwable $e) {
            // A network blip must not raise a notification: the user would have
            // no action to take, and a daily false alarm trains them to ignore
            // the bell.
            Log::info('Update check failed', ['reason' => $e->getMessage()]);
            $this->warn('Update check failed: '.$e->getMessage());

            return Command::SUCCESS;
        }

        if ($behind === null) {
            $this->warn('Could not compare against '.$repository.'.');

            return Command::SUCCESS;
        }

        if ($behind === 0) {
            $this->clearStandingNotice();
            $this->info('Up to date.');

            return Command::SUCCESS;
        }

        $notify->run(
            type: Notification::TYPE_UPDATE_AVAILABLE,
            level: Notification::LEVEL_INFO,
            title: $behind === 1 ? 'Un aggiornamento disponibile' : $behind.' aggiornamenti disponibili',
            body: 'Sul server gira una versione più vecchia di '.$behind.' commit. Aggiorna con: git pull && docker compose -f docker-compose.prod.yml up -d --build',
            // One standing notice per deployed build: re-running daily must not
            // stack up a new row every morning.
            dedupeKey: self::AVAILABLE_KEY.':'.substr($current, 0, 12),
        );

        $this->info($behind.' commit behind '.$repository.'.');

        return Command::SUCCESS;
    }

    /**
     * Dismiss the standing "update available" notice, whichever build raised
     * it. The dedupe key carries the deployed commit — so that updating to a
     * still-behind build replaces the notice rather than reusing a stale one —
     * which means clearing it cannot match on an exact key.
     */
    private function clearStandingNotice(): void
    {
        Notification::query()
            ->unread()
            ->where('type', Notification::TYPE_UPDATE_AVAILABLE)
            ->each(fn (Notification $n) => $n->markRead());
    }

    /**
     * How many commits the running build is behind the branch head, or null if
     * GitHub cannot tell us (an unknown commit, a rewritten history).
     */
    private function commitsBehind(string $repository, string $branch, string $current): ?int
    {
        $headers = ['Accept' => 'application/vnd.github+json'];

        /** @var string|null $token */
        $token = config('updates.github_token');
        if ($token !== null && $token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        $response = Http::withHeaders($headers)
            ->timeout(15)
            ->get("https://api.github.com/repos/{$repository}/compare/{$current}...{$branch}");

        if ($response->status() === 404) {
            // The deployed commit is not on the remote — a local-only build, or
            // history that was rewritten. Not an error worth reporting.
            return null;
        }

        if (! $response->successful()) {
            throw new \RuntimeException('GitHub returned '.$response->status());
        }

        $behind = $response->json('ahead_by');

        return is_int($behind) ? $behind : null;
    }
}
