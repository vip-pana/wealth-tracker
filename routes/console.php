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
