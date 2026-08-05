<?php

declare(strict_types=1);

return [
    /*
     * The repository to compare the running build against, as "owner/name".
     * Set to null to disable the daily update check entirely.
     */
    'repository' => env('UPDATE_CHECK_REPOSITORY', 'vip-pana/wealth-tracker'),

    'branch' => env('UPDATE_CHECK_BRANCH', 'main'),

    /*
     * The commit the running image was built from, stamped in by the Dockerfile
     * (GIT_COMMIT build arg). .git is excluded from the build context, so the
     * app has no other way to know its own version. Left unset — a local
     * `php artisan serve`, say — the check reports nothing rather than guessing.
     */
    'commit' => env('APP_COMMIT'),

    /*
     * GitHub allows 60 unauthenticated API calls per hour per IP, and this uses
     * one a day, so a token is optional. Set one if the IP is shared and hits
     * the limit.
     */
    'github_token' => env('GITHUB_TOKEN'),
];
