<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('prices:fetch')->dailyAt('06:00');
// Take a daily snapshot at 06:15, after the price fetch, but only if every
// source refreshed cleanly (the command skips + notifies otherwise).
Schedule::command('snapshots:daily')->dailyAt('06:15');
