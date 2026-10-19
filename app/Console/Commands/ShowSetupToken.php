<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Auth\SetupToken;
use App\Models\User;
use Illuminate\Console\Command;

class ShowSetupToken extends Command
{
    protected $signature = 'setup:token {--new : Replace the current token with a fresh one}';

    protected $description = 'Show the code /setup asks for before it will create the owner account';

    public function handle(SetupToken $token): int
    {
        // Run unconditionally by the prod entrypoint, so the normal case —
        // every start after the first — has to be quiet and successful rather
        // than printing a secret into the logs of a running instance forever.
        if (User::query()->exists()) {
            $this->line('An account already exists; /setup is closed and the token is not needed.');

            return Command::SUCCESS;
        }

        $value = $this->option('new') === true ? $token->regenerate() : $token->current();

        $this->newLine();
        $this->info('Setup code: '.$value);
        $this->line('Open /setup and enter it to create the owner account.');
        $this->line('Also stored at: '.$token->path());
        $this->newLine();

        return Command::SUCCESS;
    }
}
