<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Auth;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * The one-time secret that has to be presented to create the owner account.
 *
 * Without it, a fresh install is a land grab: /setup is open to whoever loads
 * it first, and the account it creates owns every financial record. On a LAN
 * that is a small window; on an instance reachable from the internet it is an
 * open door between `docker compose up` and the owner getting to a browser.
 *
 * The token proves possession of the machine rather than of a credential —
 * whoever can read the host's filesystem or the container's logs is already
 * the person entitled to own the instance. That is why it is written to disk
 * and echoed to the log instead of mailed or asked for: there is nowhere to
 * send it to on a brand-new install, and no account to send it from.
 *
 * Deleted the moment the account exists. Keeping it would leave a live secret
 * on disk guarding a door that RequireSetup has already bricked up.
 */
class SetupToken
{
    /**
     * Inside the container this sits in the bind-mounted data directory, so the
     * host sees it next to the database (`~/wealth-tracker-data/`) without
     * needing a shell in the container.
     */
    public function path(): string
    {
        return Config::string('auth.setup_token_path');
    }

    /**
     * The token for this install, generating one the first time it is asked
     * for. Generating on demand rather than only at boot means an install that
     * never ran the entrypoint — `composer dev`, a bare `php artisan serve` —
     * still gets a guarded /setup instead of an unguarded one.
     */
    public function current(): string
    {
        return $this->read() ?? $this->regenerate();
    }

    public function exists(): bool
    {
        return $this->read() !== null;
    }

    /**
     * Mint a new token, replacing any existing one. Written with a 0600 umask
     * rather than a chmod afterwards: a chmod leaves the file world-readable
     * for the moment between creation and the mode change, which is the one
     * moment the contents are worth stealing.
     */
    public function regenerate(): string
    {
        // Hex rather than mixed-case alphanumeric: this gets transcribed from a
        // terminal into a browser by hand, where case and look-alike glyphs are
        // where it goes wrong. 128 bits is far past guessing either way.
        $token = bin2hex(random_bytes(16));

        File::ensureDirectoryExists(dirname($this->path()));

        $previous = umask(0o077);

        try {
            File::put($this->path(), $token."\n");
        } finally {
            umask($previous);
        }

        // The other half of "read it from the logs". `docker compose logs` is
        // the only way in on a machine the owner reaches over ssh and does not
        // want to go poking around the data directory on.
        Log::warning('Setup token generated. Present it at /setup to create the owner account.', [
            'token' => $token,
            'path' => $this->path(),
        ]);

        return $token;
    }

    /**
     * Whether the value the visitor typed is the token. Compared in constant
     * time — the route is public by necessity, so it is the one place in the
     * app a timing oracle would be reachable by a stranger.
     */
    public function verify(?string $given): bool
    {
        if ($given === null || $given === '') {
            return false;
        }

        return hash_equals($this->current(), $given);
    }

    /** Called once the owner account exists; see the class note. */
    public function forget(): void
    {
        File::delete($this->path());
    }

    private function read(): ?string
    {
        if (! File::isFile($this->path())) {
            return null;
        }

        $token = trim(File::get($this->path()));

        return $token === '' ? null : $token;
    }
}
