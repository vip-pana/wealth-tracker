# Self-hosting Wealth Tracker

How to run the app on a machine that stays on — a box at home, a NAS, a VPS.
Everything here applies to any install; nothing assumes you are the project's
author or that you use the same services.

> If you are reading this to understand how the **project's own instance** is
> deployed — the Tailscale sidecar, the GitHub Actions deploy, the tailnet
> policy — that is a worked example of one particular setup and lives in
> [reference-deployment.md](reference-deployment.md). You do not need any of it.

## What you need

- A Linux machine that stays powered on, with Docker and the Compose plugin.
- The repo, checked out on that machine (for the compose files; the application
  image is pulled, not built).
- A decision about how you will reach it — see [§5](#5-decide-how-you-reach-it).

Architecture is not a concern: the Dockerfile picks the right Scalable CLI
binary for x86 or ARM at build time, and the published images cover both. Check
yours with `uname -m` — `x86_64` or `aarch64`.

## 1. Install Docker

```bash
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker "$USER"    # log out and back in for this to take effect
docker run --rm hello-world        # verify
```

## 2. Create the data directories

The entrypoint **refuses to start when the database file is missing**, rather
than creating an empty one — a failed volume mount otherwise looks like a
working app holding no data. So the directories must exist before the first run.

```bash
mkdir -p ~/wealth-tracker-data ~/wealth-tracker-backups
```

For a new install, create the database file explicitly. This is the one case
where an empty file is correct:

```bash
touch ~/wealth-tracker-data/database.sqlite
```

Override the locations with `WEALTH_TRACKER_DATA_DIR` and
`WEALTH_TRACKER_BACKUP_DIR` in `.env` if you keep data elsewhere.

## 3. Configure `.env`

```bash
cp .env.example .env
echo "base64:$(head -c 32 /dev/urandom | base64)"
```

Put the printed value in `APP_KEY` — the app will not start without one. Then
the keys that matter:

| Key | Why it matters |
| --- | --- |
| `APP_KEY` | Encrypts sessions. Changing it later logs everyone out; losing it is unrecoverable for encrypted data. |
| `APP_URL` | The address you actually open, scheme included. Absolute URLs and the bank redirect depend on it. |
| `APP_VERSION` | The released image tag to run, without the leading `v` (`1.10.1`, not `v1.10.1`). Pin it — empty means `latest`, and then a plain restart can silently change the running version. |
| `SESSION_SECURE_COOKIE` | `true` if something terminates HTTPS in front of the app, `false` if you serve plain `http://`. See below. |
| `BACKUP_DISK`, `BACKUP_ENCRYPTION_KEY` | Off-site backups. See [database.md](database.md). |

`SESSION_LIFETIME=43200` and `SESSION_ENCRYPT=true` are sensible defaults for a
single-user app opened from a phone; keep them.

**`SESSION_SECURE_COOKIE` follows whether you have HTTPS.** With
`APP_ENV=production` and no explicit value, Laravel marks the session cookie
`secure`, so the browser only sends it over HTTPS. Over `http://` the cookie
never comes back and every login fails with **419 Page Expired** — an error
that points at CSRF and says nothing about cookies. If you serve plain http:

```
SESSION_SECURE_COOKIE=false
```

That is not a security hole in itself if the transport is already private (a
VPN, an SSH tunnel), but it does cost you the bank-consent flow
([§8](#8-optional-enable-banking)), which needs a real HTTPS origin.

> **`APP_ENV` in `.env` is ignored in production.** The compose files set
> `APP_ENV=production` and `APP_DEBUG=false` as container environment
> variables, which win over the file. A stale `APP_ENV=local` left in `.env` is
> misleading but harmless. Ask the app what it actually believes rather than
> trusting the file — see
> [When something does not work](#when-something-does-not-work).

## 4. Bring an existing database, if you have one

Skip this for a new install. The database is a single SQLite file of a few
megabytes, so moving it is a copy — but stop the app on the old machine first:

```bash
# On the old machine
docker compose stop app
scp ~/wealth-tracker-data/database.sqlite user@newmachine:~/wealth-tracker-data/
```

> **Never copy `database.sqlite` while the app is running, and never copy it
> without also handling `database.sqlite-wal`.** In WAL mode the `-wal` file
> holds committed pages not yet in the main file. Copying the `.sqlite` alone
> leaves a stale WAL that SQLite replays over it, corrupting B-trees while the
> rows stay readable — so nothing looks wrong until a query walks a broken
> index and the app dies with `database disk image is malformed`. Stopping the
> container first checkpoints the WAL cleanly.

Restoring from a backup archive avoids the problem entirely, because the
archive is already a consistent snapshot:

```bash
php artisan backup:restore --list
php artisan backup:restore wealth-tracker/database-YYYYMMDD-HHMMSS.sqlite.enc
```

## 5. Decide how you reach it

The app speaks plain HTTP on port 8000 inside the container and terminates no
TLS of its own. There are three sane arrangements, and the one you pick decides
which compose file you use.

### a. Loopback only — the simplest thing that works

`docker-compose.standalone.yml` publishes the port on `127.0.0.1` and nothing
else. You reach the app from the machine itself, or through an SSH tunnel:

```bash
ssh -L 8000:127.0.0.1:8000 user@yourmachine
```

Set `APP_URL=http://localhost:8000` and `SESSION_SECURE_COOKIE=false`. Nothing
is exposed, so this is also the right starting point for trying the app out
before committing to a domain.

### b. A reverse proxy with a real certificate

The normal setup for a VPS with a domain. Keep the port on loopback and put
Caddy (least work) or nginx in front, terminating TLS and proxying to
`127.0.0.1:8000`. A whole Caddyfile for this is two lines:

```
wealth.example.com {
	reverse_proxy 127.0.0.1:8000
}
```

Set `APP_URL=https://wealth.example.com` and leave `SESSION_SECURE_COOKIE=true`.
The app trusts `X-Forwarded-*` headers only when `APP_URL` (or the bank
redirect) is a remote HTTPS address, so this works without further
configuration — and a local-only install never trusts a proxy it does not have.

> **The app is now publicly reachable and the password is the only thing in
> front of it.** That is a different threat model from a private install: make
> sure the first account is created before you point DNS at it (see
> [First run](#7-first-run)), and keep the machine patched.

### c. A private network — Tailscale, WireGuard, or similar

Gets you a real HTTPS name without exposing anything to the internet. The
project's own instance runs a Tailscale sidecar that joins the tailnet as its
own node and obtains a Let's Encrypt certificate, which is what
`docker-compose.prod.yml` sets up; the full walkthrough is in
[reference-deployment.md](reference-deployment.md). Any other VPN works the
same way: terminate TLS at something on the private network and treat it as
case (b).

## 6. Start it

Pin the version in `.env`, then pull and start. Nothing is compiled on your
machine — the image is built by CI when a release is published and pulled from
ghcr.io:

```bash
echo 'APP_VERSION=1.10.1' >> .env     # the release you want
docker compose -f docker-compose.standalone.yml pull
docker compose -f docker-compose.standalone.yml up -d
```

To build on this machine instead — you changed the Dockerfile, or you are
running code that was never released:

```bash
GIT_COMMIT=$(git rev-parse HEAD) docker compose \
  -f docker-compose.standalone.yml -f docker-compose.build.yml up -d --build
```

One container comes up, and it runs **three** processes: the web server
(FrankenPHP), the queue worker (the AI advisor replies from a queued job) and
the scheduler (prices, transaction import, the daily snapshot, the nightly
backup). Verify all three rather than assuming — the app answers `/up`
perfectly well with a dead worker, and the symptom of that is the advisor never
replying and no backup ever running:

```bash
docker compose -f docker-compose.standalone.yml ps      # should reach healthy
docker compose -f docker-compose.standalone.yml logs -f # follow startup
docker compose -f docker-compose.standalone.yml top
```

Expect `frankenphp`, `artisan queue:work` and `artisan schedule:work`.

Then check the scheduled jobs are registered:

```bash
docker compose -f docker-compose.standalone.yml exec app php artisan schedule:list
```

### Why `-u appuser` on anything that writes

The app does not run as root. The entrypoint starts as root, takes ownership of
the mounted directories, and re-execs itself through `gosu` as `appuser`, so the
web server, the queue worker and the scheduler all run unprivileged — a flaw
reached over HTTP is not a flaw reached as root.

`docker compose exec` does **not** inherit that: it lands you as root. Reading
is harmless, but anything that *writes* — `backup:run`, `config:clear`,
`sc login`, `user:password` — leaves root-owned files the app itself then
cannot rewrite or prune. The symptom arrives days later as a backup directory
that never shrinks, or a cache the app cannot clear. So:

```bash
docker compose -f docker-compose.standalone.yml exec -u appuser app <command>
```

The uid is not fixed at 1000. The image is pulled, not built here, so it cannot
know this host's uid; the entrypoint instead adopts whichever uid owns
`~/wealth-tracker-data` and moves `appuser` onto it. That is right on nearly
every host — set `PUID` / `PGID` in `.env` if yours is unusual (an NFS mount, a
directory owned by a service account). The startup log says which it picked:

```
Dropping privileges: running as appuser (1000:1000)
```

## 7. First run

There is no account yet, so every page redirects to `/setup`, which asks for a
**setup code** before it will create one. Without that code a fresh instance is
a land grab: whoever loads `/setup` first becomes the owner.

The code is printed at startup and written to the data directory:

```bash
docker compose -f docker-compose.standalone.yml logs app | grep "Setup code"
cat ~/wealth-tracker-data/setup-token.txt
docker compose -f docker-compose.standalone.yml exec -u appuser app php artisan setup:token
```

Paste it into `/setup`, choose an email and a password, and the code is deleted
— `/setup` is closed server-side from then on. If you think it leaked before
you used it, `php artisan setup:token --new` replaces it.

> There is no password reset. The way back in is
> `php artisan user:password` from a shell on the machine.

## 8. Optional: Enable Banking

Open-banking balances and transactions. Two things are needed beyond the
credentials in `.env`:

**The signing key is a file.** `ENABLE_BANKING_PRIVATE_KEY_PATH` points inside
the container (`/app/storage/app/enable-banking.pem`), which is the mounted
data directory — so the `.pem` has to live next to the database:

```bash
cp enable-banking.pem ~/wealth-tracker-data/
chmod 600 ~/wealth-tracker-data/enable-banking.pem
```

Without it every call fails at JWT signing, the bank list comes back empty, and
Settings reports **"Enable Banking non configurato (chiave/credenziali
mancanti)"** — which points at the credentials in `.env`, the one thing that is
actually fine. The empty list is then cached for 24 hours, so the warning
outlives the fix unless the cache is dropped:

```bash
docker compose -f docker-compose.standalone.yml exec app \
  php artisan tinker --execute 'Cache::forget("enable_banking.aspsps.IT");'
```

**The consent redirect must be HTTPS**, and must match the redirect registered
on your Enable Banking application character for character — a trailing slash
is enough to be rejected:

```
ENABLE_BANKING_REDIRECT_URL=https://wealth.example.com/banking/callback
```

Then recreate the container (see the note on `--force-recreate` below). If you
serve plain http you have no HTTPS origin, and the consent flow needs a
throwaway tunnel each time you connect or renew a bank —
`scripts/tunnel-prod.sh` prints a URL to register, and restores `APP_URL` on
Ctrl-C. A stable HTTPS domain means registering once and never tunnelling.

More detail in [enable-banking-usage.md](enable-banking-usage.md).

## 9. Optional: Scalable Capital

Broker balances through the official CLI, which runs headless in the container.
The session is tied to the container's CLI state, so it is set up once per
machine — from Settings → "Collega", or from a shell:

```bash
docker compose -f docker-compose.standalone.yml exec -it -u appuser app sc login
```

`-u appuser` matters here: as root the CLI writes its session where the app
cannot read it, and the login looks like it worked right up until the next sync
finds no session. The session lives in the `scalable-cli` volume and survives
container recreation; `scalable:keep-alive` (every 6 hours) stops it lapsing,
so it needs the scheduler to be running.

## Everyday operation

```bash
# Logs
docker compose -f docker-compose.standalone.yml logs -f app

# Shell, as the account the app runs as
docker compose -f docker-compose.standalone.yml exec -u appuser app bash

# Forgotten password (also signs out every session)
docker compose -f docker-compose.standalone.yml exec -it -u appuser app php artisan user:password

# Run a backup now
docker compose -f docker-compose.standalone.yml exec -u appuser app php artisan backup:run
```

Backups are covered in [database.md](database.md). The short version: set
`BACKUP_DISK` and `BACKUP_ENCRYPTION_KEY` or your backups never leave the
machine, and keep the key somewhere other than the backup destination.

## Updating

Edit `APP_VERSION`, pull, recreate:

```bash
sed -i 's/^APP_VERSION=.*/APP_VERSION=1.11.0/' .env
docker compose -f docker-compose.standalone.yml pull
docker compose -f docker-compose.standalone.yml up -d
```

`scripts/deploy-release.sh` does the same thing with guard rails, and is worth
using once you have data you care about: it refuses a release whose `.env` keys
this machine does not have, takes a backup first, and — if `/up` does not answer
within two minutes — puts `APP_VERSION` back and recreates on the previous
image. That rollback costs no build, because the old image is still in the
local store. It also checks that the queue worker and the scheduler came back,
not just the web server.

A daily check (09:05) compares the running build against the repository and
raises an in-app notification when it is behind. It works by stamping the image
with its commit at build time (`GIT_COMMIT`), because `.git` is excluded from
the build context; an image built without it is stamped `unknown` and the check
does nothing rather than reporting a wrong answer. Set
`UPDATE_CHECK_REPOSITORY=` (empty) to switch it off.

## When something does not work

The entrypoint runs `config:cache` at startup, so **`.env` edits need
`--force-recreate`, not `restart`** — otherwise the file is right and the app is
still using the old values. Start by asking the app what it actually believes,
rather than reading `.env`: the compose file overrides several keys, so the file
and the running config genuinely disagree by design.

```bash
docker compose -f docker-compose.standalone.yml exec app php -r '
require "/app/vendor/autoload.php"; $a = require_once "/app/bootstrap/app.php";
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "app.env:        ".config("app.env")."\n";
echo "app.url:        ".config("app.url")."\n";
echo "session.domain: ".var_export(config("session.domain"), true)."\n";
echo "session.secure: ".var_export(config("session.secure"), true)."\n";
echo "eb.redirect:    ".config("services.enable_banking.redirect_url")."\n";'
```

| Symptom | Cause |
| --- | --- |
| `FATAL: no database at …` in a restart loop | The data directory is not mounted where the app expects, so Docker created an empty one. Check with `docker compose -f docker-compose.standalone.yml config \| grep source:` — a `.env` copied from another machine keeps that machine's absolute paths. |
| **419 Page Expired** on login | `session.secure` is `true` while serving over `http://`. Set `SESSION_SECURE_COOKIE=false`, or put HTTPS in front. Retry in a private window: a stale cookie from the broken origin reproduces the error on a fixed server. |
| Login page loops without an error | `app.url` does not match the address you opened. |
| `pull` fails with `manifest unknown` | No image was published for that `APP_VERSION`. Check the tag exists under the repository's Packages. |
| `pull` fails with `denied` / `unauthorized` | The ghcr package is private, or you are not logged in. `docker login ghcr.io`. |
| `Permission denied` writing the database or a log | The entrypoint adopted the wrong uid, or a root-owned file is left over. `ls -ln ~/wealth-tracker-data` and compare with the `Dropping privileges:` line in the startup log; set `PUID`/`PGID`, or `sudo chown -R <uid>:<gid>` the stragglers. |
| The backup directory only ever grows; nothing is pruned | Archives were written by a root `docker compose exec … backup:run`, so the scheduled prune — which runs as `appuser` — cannot delete them. `sudo chown` them to the app's uid, and use `-u appuser` from now on. |
| `sc login` succeeded but syncs report no session | The login ran as root, so the session landed somewhere the app cannot read. Redo it with `-u appuser`. |
| `sc login` fails with `Platform secure storage failure: DBus error` | The CLI is reaching for the OS keyring, which no container has. The entrypoint writes a file-backed `config.toml` at startup — if you hit this, the image predates that fix: pull a newer `APP_VERSION`. |
| Advisor never replies | Queue worker not running — check all three processes (§6). |
| Data never refreshes, Scalable keeps logging out | Scheduler not running, so `scalable:keep-alive` never fires. |
| **"Enable Banking non configurato"** with the credentials set | The `.pem` was never copied into the data directory (§8). Confirm with `grep "private key file not accessible" storage/logs/laravel.log`, then drop the cached empty bank list. |
| Bank sync fails only on one bank, `{"status":429}` in the log | That bank's rate limit, not a fault. 429s are deliberately not retried; the next scheduler run picks it up. |
| Bank consent rejected before you reach the bank | `ENABLE_BANKING_REDIRECT_URL` does not match the redirect registered on the Enable Banking application, character for character. Compare against `eb.redirect` above, not against `.env`. |
| Consent approved at the bank, app never learns about it | The browser could not reach the callback. Run the flow again from a device that can. |
