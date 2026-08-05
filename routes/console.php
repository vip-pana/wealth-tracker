<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('prices:fetch')->dailyAt('06:00');
// Import bank transactions daily. The API only returns a ~90-day window; the
// idempotent import (keyed on the EB transaction id) lets the DB accumulate
// history beyond it while overlapping re-fetches never duplicate.
Schedule::command('bank:import-transactions')->dailyAt('06:05');
// Take a daily snapshot at 06:15, after the price fetch, but only if every
// source refreshed cleanly (the command skips + notifies otherwise).
Schedule::command('snapshots:daily')->dailyAt('06:15');
// Keep the Scalable CLI session alive: its refresh token rotates on use, so a
// ping every 6h (well inside the refresh window) prevents the session lapsing
// between the once-a-day sync/snapshot.
Schedule::command('scalable:keep-alive')->everySixHours();
// Nightly off-site backup. Replaces the host-side launchd job + scripts/backup.sh,
// which only worked on the machine that had Docker and the cloud-sync client
// installed; running it in-app keeps backups working wherever the app is hosted.
Schedule::command('backup:run')->dailyAt('03:00');
// A failed backup notifies itself; this catches the quieter failure where the
// backup simply never runs, which no error would ever report.
Schedule::command('backup:health')->dailyAt('09:00');
