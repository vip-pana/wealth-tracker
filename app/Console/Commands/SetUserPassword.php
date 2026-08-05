<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class SetUserPassword extends Command
{
    protected $signature = 'user:password {email? : The account to update}';

    protected $description = 'Set the account password (the way back in when it is forgotten)';

    public function handle(): int
    {
        /** @var string|null $email */
        $email = $this->argument('email');

        $user = $email !== null
            ? User::query()->where('email', $email)->first()
            : User::query()->orderBy('id')->first();

        if ($user === null) {
            $this->error($email !== null
                ? "No account with email {$email}."
                : 'No account exists yet — open the app and use the setup page.');

            return Command::FAILURE;
        }

        $this->line('Setting the password for '.$user->email);

        /** @var string $password */
        $password = $this->secret('New password') ?? '';
        /** @var string $confirm */
        $confirm = $this->secret('Confirm password') ?? '';

        if ($password !== $confirm) {
            $this->error('The passwords do not match.');

            return Command::FAILURE;
        }

        // Same rule as SetupRequest, so the terminal path cannot be used to set
        // a weaker password than the UI accepts.
        $validator = Validator::make(
            ['password' => $password],
            ['password' => ['required', Password::min(12)->letters()->numbers()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return Command::FAILURE;
        }

        // The cast hashes it. Clearing sessions is the point of a password
        // change: a lost phone holds a valid 30-day session, and this is what
        // revokes it.
        $user->update(['password' => $password, 'remember_token' => null]);
        DB::table('sessions')->delete();

        $this->newLine();
        $this->info('Password updated. Every existing session has been signed out.');
        $this->newLine();

        return Command::SUCCESS;
    }
}
