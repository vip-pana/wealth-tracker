<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Clients\ScalableCliClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class ScalableKeepAlive extends Command
{
    protected $signature = 'scalable:keep-alive';

    protected $description = 'Ping the Scalable CLI so its rolling refresh token stays alive between syncs';

    public function handle(ScalableCliClient $cli): int
    {
        if (! Config::boolean('services.scalable.cli.enabled', false)) {
            return Command::SUCCESS;
        }

        // isLoggedIn() runs `whoami`, which flows through the CLI's auth layer:
        // if the short-lived access token has expired it uses the refresh token,
        // which ROTATES and resets the session window. Running this well within
        // the refresh window keeps the session alive without a manual re-login.
        // We don't act on the result — a false just means the session had already
        // lapsed, which the next sync surfaces to the user.
        $cli->isLoggedIn();

        return Command::SUCCESS;
    }
}
