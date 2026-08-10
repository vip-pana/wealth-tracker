# Running it on your own machine

How to move the app off the development Mac and onto a machine that stays on — a
box at home reached over Tailscale, or a VPS. The application code is identical
either way; only the exposure differs, so switching later costs nothing.

## What you need

- A Linux machine that stays powered on, with Docker and the Compose plugin.
- The repo, checked out on that machine.
- The secrets from the current `.env` (see [Move the secrets](#3-move-the-secrets)).
- A Tailscale account, and HTTPS certificates enabled on the tailnet — the prod
  stack fronts the app with a Tailscale sidecar that terminates TLS (§5).

Architecture is not a concern: the Dockerfile picks the right Scalable CLI binary
for x86 or ARM at build time. Build on the target machine and it resolves itself.
Check with `uname -m` — `x86_64` or `aarch64`.

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

For a genuinely new install (no data to carry over), create the database
explicitly — this is the one case where an empty file is correct:

```bash
touch ~/wealth-tracker-data/database.sqlite
```

## 3. Move the secrets

Copy `.env` from the old machine, then check these:

| Key | Why it matters |
| --- | --- |
| `APP_KEY` | Carry it over. A new key makes existing encrypted sessions unreadable. |
| `BACKUP_ENCRYPTION_KEY` | **Carry it over.** A different key leaves every existing R2 archive unrecoverable. |
| `AWS_*`, `BACKUP_DISK` | R2 credentials — off-site backups do nothing without them. |
| `REGOLO_API_KEY` | The AI advisor. Cloud-hosted, so it works unchanged. |
| `ENABLE_BANKING_PRIVATE_KEY_PATH` | Copy the `.pem` too, and point the path at its new location. |
| `APP_URL` | Set to the address you actually open (see below). Absolute URLs and the bank redirect depend on it. |
| `TS_AUTHKEY` | New per machine — generate a fresh one rather than copying (§5). The prod stack refuses to start without it. |

`SESSION_LIFETIME=43200` and `SESSION_ENCRYPT=true` should already be set; keep
them.

**`SESSION_SECURE_COOKIE` follows whether you have HTTPS.** With the Tailscale
sidecar (§5) you do, so leave it `true`. Only if you serve plain `http://` —
skipping the sidecar and publishing a port instead — set it to `false`:

```
SESSION_SECURE_COOKIE=false
```

With `APP_ENV=production` and no explicit value, Laravel marks the session
cookie `secure`, so the browser only sends it over HTTPS. Over `http://` the
cookie never comes back and every login fails with **419 Page Expired** — an
error that points at CSRF and says nothing about cookies. That is not a security
downgrade behind Tailscale — WireGuard already encrypts the transport — but it
does cost you the bank-consent flow (§8), which needs a real HTTPS origin.

> **`APP_ENV` in `.env` is ignored in production.** `docker-compose.prod.yml`
> sets `APP_ENV=production` and `APP_DEBUG=false` as container environment
> variables, which win over the file. A stale `APP_ENV=local` left in `.env` is
> misleading but harmless. Ask the app what it actually believes rather than
> trusting the file — see [When something does not work](#when-something-does-not-work).

## 4. Move the database

It is around 1.3 MB, so this is a copy. Stop the app on the old machine first —
and copy the WAL files with it, or leave them behind entirely:

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
> index. Stopping the container first checkpoints the WAL cleanly.

Alternatively, restore from R2 on the new machine — the archive is already a
consistent snapshot:

```bash
php artisan backup:restore --list
php artisan backup:restore wealth-tracker/database-YYYYMMDD-HHMMSS.sqlite.enc
```

## 5. Reach it from your phone: Tailscale, with HTTPS

Tailscale is a private network between your own devices. The app is never
published to the internet, so the login is a second lock rather than the only
one.

The app does not publish a port on the host at all. `docker-compose.prod.yml`
runs a **Tailscale sidecar** that joins the tailnet as its own node named
`wealth-tracker`, obtains a Let's Encrypt certificate for
`https://wealth-tracker.<your-tailnet>.ts.net`, and proxies to the app over
loopback. The `app` container shares that network stack
(`network_mode: service:tailscale`), so the two are a single node as far as the
tailnet is concerned and the app is reachable from nowhere else.

Getting real HTTPS is not cosmetic: it is what removes the throwaway tunnel
from the Enable Banking consent flow (§8), and it lets the session cookie stay
`secure`.

Install Tailscale on the host and on your phone, signing in with the same
account:

```bash
curl -fsSL https://tailscale.com/install.sh | sh
sudo tailscale up
```

Two things on the admin console, both one-time:

- Turn on **HTTPS certificates** for the tailnet under
  [DNS settings](https://login.tailscale.com/admin/dns). Without it the sidecar
  cannot obtain a certificate and serve never binds 443. Do this *before* the
  first start.
- Generate a **reusable auth key** under
  [Settings → Keys](https://login.tailscale.com/admin/settings/keys).

Then in `.env`:

```
TS_AUTHKEY=tskey-auth-…
APP_URL=https://wealth-tracker.<your-tailnet>.ts.net
SESSION_SECURE_COOKIE=true
```

`APP_URL` has no port: the sidecar serves on 443. Then recreate — `.env` changes
need `--force-recreate`, not a restart:

```bash
docker compose -f docker-compose.prod.yml up -d --force-recreate
```

> **The `tailscale-state` volume holds the node identity.** Delete it and the
> next start registers a *new* node, which Tailscale names `wealth-tracker-1`.
> The hostname is baked into `APP_URL` and into the redirect registered with
> Enable Banking, so both silently break. Keep the volume across recreations.

Check it from the machine before reaching for the phone:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://wealth-tracker.<your-tailnet>.ts.net/up
```

`200` means the phone will reach it too — as long as the phone has Tailscale
connected. The `.ts.net` name resolves publicly but only answers over the
tailnet.

> **Earlier setups used `BIND_ADDRESS`**, publishing port 8080 on the tailnet IP
> and serving plain http. That still works if you skip the sidecar, but it costs
> you HTTPS, so the bank-consent flow goes back to needing a tunnel and
> `SESSION_SECURE_COOKIE` has to be `false`. The sidecar supersedes it; with
> `network_mode: service:tailscale` the app has no ports of its own to bind.

## 6. Start it

Pin the version to run in `.env` — the ghcr.io image tag without the leading
`v` — then pull and start:

```bash
echo 'APP_VERSION=1.5.0' >> .env     # the release you want
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
```

Nothing is compiled here: the image is built by CI when a release is published
(`.github/workflows/deploy.yml`) and pulled from `ghcr.io`. To build on this
machine instead — you are changing the Dockerfile, or running code that was
never released — add the override:

```bash
GIT_COMMIT=$(git rev-parse HEAD) docker compose \
  -f docker-compose.prod.yml -f docker-compose.build.yml up -d --build
```

Two containers come up — the Tailscale sidecar and the app — and the
app itself runs three processes: the web server, the queue worker (the AI
advisor replies from a queued job) and the scheduler (prices, transaction
import, the daily snapshot, the Scalable keep-alive, and the nightly backup).

Both containers should reach `healthy`:

```bash
docker compose -f docker-compose.prod.yml ps
```

Verify all three processes, rather than assuming:

```bash
docker compose -f docker-compose.prod.yml logs -f          # follow startup
docker compose -f docker-compose.prod.yml exec app \
  sh -c 'for p in /proc/[0-9]*; do tr "\0" " " < $p/cmdline 2>/dev/null | grep -q artisan && tr "\0" " " < $p/cmdline && echo; done'
```

Expect `artisan serve`, `artisan queue:work` and `artisan schedule:work`.

Then check the scheduled jobs are registered, and that a backup actually reaches
R2:

```bash
docker compose -f docker-compose.prod.yml exec app php artisan schedule:list
docker compose -f docker-compose.prod.yml exec app php artisan backup:run
docker compose -f docker-compose.prod.yml exec app php artisan backup:health
```

## 7. Reconnect Scalable

The broker session does not transfer — it is tied to the machine's CLI state.
Log in again from Settings → "Collega", or from the shell:

```bash
docker compose -f docker-compose.prod.yml exec -it app sc login
```

The session lives in the `scalable-cli` volume and survives container
recreation. `scalable:keep-alive` (every 6 hours) keeps it from lapsing; if the
scheduler is not running, the session dies and this becomes a recurring chore.

## 8. Enable Banking

The client credentials live in `.env`, but the signing key is a **file**, and
`ENABLE_BANKING_PRIVATE_KEY_PATH` points inside the container
(`/app/storage/app/enable-banking.pem`) — which is the mounted data directory.
So the `.pem` has to be copied into `~/wealth-tracker-data/` alongside the
database; carrying `.env` over is not enough:

```bash
scp ~/wealth-tracker-data/enable-banking.pem user@newmachine:~/wealth-tracker-data/
chmod 600 ~/wealth-tracker-data/enable-banking.pem
```

Without it every call fails at JWT signing, so the bank list comes back empty
and Settings reports **"Enable Banking non configurato (chiave/credenziali
mancanti)"** — which points at the credentials in `.env`, the one thing that is
actually fine. The empty list is then cached for 24 hours, so the warning
outlives the fix unless the cache is dropped:

```bash
docker compose -f docker-compose.prod.yml exec app \
  php artisan tinker --execute 'Cache::forget("enable_banking.aspsps.IT");'
```

### The consent redirect

Enable Banking sends the user back to an **HTTPS** callback after they approve
at their bank. With the Tailscale sidecar (§5) the app already has one, so this
is a one-time registration and **no tunnel is involved**:

1. Register `https://wealth-tracker.<your-tailnet>.ts.net/banking/callback` as
   the redirect on your Enable Banking application.
2. Put the identical URL in `.env`:

   ```
   ENABLE_BANKING_REDIRECT_URL=https://wealth-tracker.<your-tailnet>.ts.net/banking/callback
   ```

3. `docker compose -f docker-compose.prod.yml up -d --force-recreate`.

The two strings must match exactly — Enable Banking compares them literally, and
a trailing slash is enough to be rejected.

> **Keep Tailscale connected on the device you consent from.** The `.ts.net`
> name resolves publicly, but only answers over the tailnet. The bank redirects
> *your browser*, so if the phone drops off the tailnet mid-flow the callback
> lands on an unreachable host and the consent is lost — the bank side succeeded
> and the app never heard about it. Just run the flow again.

**When there is no HTTPS origin** — you skipped the sidecar and serve plain
http — the redirect still needs a throwaway tunnel each time you connect or
renew a bank. Use **`scripts/tunnel-prod.sh`**; `scripts/tunnel.sh` is the
development-Mac one and on Linux it fails on BSD `sed` and drives the dev
compose file.

```bash
./scripts/tunnel-prod.sh      # prints the redirect URL, Ctrl-C when done
```

Paste the printed URL into the Enable Banking portal, then open the app
**through the tunnel** to connect or renew. On Ctrl-C the script puts `APP_URL`
back to the tailnet address. A VPS with a stable domain behaves like the
sidecar case: register once, no tunnel.

## Staying up to date

Merging release-please's release PR is the whole deploy. Publishing the release
triggers `.github/workflows/deploy.yml`, which builds the image, pushes it to
ghcr.io, and then calls `deploy-to-host.yml` to tell this machine to run it.

Unattended deploys were avoided for a long time, for a good reason: a release
that needs a new `.env` key would take the app down overnight with nobody
watching. What makes them safe now is that the machine, not the workflow,
decides — `scripts/deploy-release.sh` refuses a release whose `.env` keys it
does not have, takes a backup first, and rolls back if the app does not answer.
The workflow only names a version.

### How the runner reaches a machine with no open ports

GitHub joins the tailnet as a throwaway node tagged `tag:ci`, which the tailnet
policy allows to reach exactly one host on exactly one port:

```json
{ "src": ["tag:ci"], "dst": ["tag:prod"], "ip": ["tcp:22"] }
```

It connects as the Unix user `deploy`, whose **login shell is
`/usr/local/sbin/deploy-shell`** — a wrapper that accepts one command,
`deploy <app> <version>`, resolves the app against a whitelist, validates the
version as a version, and executes that app's deploy script through a
single-command sudoers rule. There is no interactive shell behind that account,
and no ssh key anywhere: Tailscale SSH authenticates against the tailnet
identity.

So a leaked workflow secret buys the ability to deploy an existing release. It
does not buy a shell on the machine that holds the financial history.

That account serves every app on the box — funeral-docs deploys through the same
wrapper. The app is an argument checked against a list of known names, never a
path the caller supplies, so a third app is a line in the whitelist plus a line
in sudoers, not a second Unix account with its own copy of the wrapper to drift.
The wrapper is committed in both repos and must be kept identical: only the copy
installed at `/usr/local/sbin/deploy-shell` actually runs.

The `ssh` rule for `tag:ci` uses `"action": "accept"` rather than `"check"`. A
`check` rule demands an interactive browser confirmation, which a CI runner
cannot answer — the job would hang until it timed out.

> **Tagging the host removes it from `autogroup:self`.** The default policy
> grants you SSH to *your own* devices; a tagged device has no owner, so that
> rule stops covering it and `tagOwners` grants nothing on its own. The policy
> therefore names the account explicitly — `"users": ["pana"]`, not
> `autogroup:nonroot`, which does not expand to arbitrary Unix accounts. Get
> this wrong and you lock yourself out of the machine, so land the rule in the
> same policy save as the tag.

### The manual path

Still available and unchanged, for a hotfix or when the workflow is the thing
that is broken:

```bash
./scripts/deploy-release.sh 1.7.0
```

A daily check (09:05) compares the running build against the repository and
raises an in-app notification when it is behind — now a safety net for a deploy
that silently never ran, rather than the prompt to go and deploy by hand.

It works by stamping the image with its commit at build time (`GIT_COMMIT`,
passed by the deploy workflow), because `.git` is excluded from the build
context and the container otherwise has no way to know its own version. An
image built without it is stamped `unknown`, and the check then does nothing
rather than reporting a wrong answer.

What the deploy script does, wherever it is invoked from: refuses to start when
the release adds an `.env` key this machine does not have, backs up the database
first, and — if `/up` does not answer within two minutes — puts `APP_VERSION`
back and recreates the container on the previous image. That rollback costs no
build: the old image is still in the local store.

It also checks that the queue worker and the scheduler came back, not just the
web server. The app answers `/up` perfectly well with a dead worker, and the
symptom of that is the advisor never replying and no backup ever running —
nothing that fails loudly.

By hand, if you need the steps separately:

```bash
sed -i 's/^APP_VERSION=.*/APP_VERSION=1.6.0/' .env
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
```

Set `UPDATE_CHECK_REPOSITORY=` (empty) to switch the check off.

## Everyday operation

```bash
# Update to a released version (edit APP_VERSION in .env first)
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d

# Logs
docker compose -f docker-compose.prod.yml logs -f app

# Shell
docker compose -f docker-compose.prod.yml exec app bash

# Forgotten password (also signs out every session)
docker compose -f docker-compose.prod.yml exec -it app php artisan user:password
```

## When something does not work

The prod entrypoint runs `config:cache` at startup, so **`.env` edits need
`--force-recreate`, not `restart`** — otherwise the file is right and the app is
still using the old values. Start by asking the app what it actually believes:

```bash
docker compose -f docker-compose.prod.yml exec app php -r '
require "/app/vendor/autoload.php"; $a = require_once "/app/bootstrap/app.php";
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo "app.env:        ".config("app.env")."\n";
echo "app.url:        ".config("app.url")."\n";
echo "session.domain: ".var_export(config("session.domain"), true)."\n";
echo "session.secure: ".var_export(config("session.secure"), true)."\n";
echo "eb.redirect:    ".config("services.enable_banking.redirect_url")."\n";'
```

Ask it rather than reading `.env`: the compose file overrides several keys, so
the file and the running config genuinely disagree by design.

| Symptom | Cause |
| --- | --- |
| `FATAL: no database at …` in a restart loop | `WEALTH_TRACKER_DATA_DIR` points somewhere that does not exist, so Docker created an empty directory. Check with `docker compose -f docker-compose.prod.yml config \| grep source:` — a `.env` copied from another machine keeps that machine's absolute paths. |
| Stack will not start, `TS_AUTHKEY missing from .env` | The sidecar has no auth key. Generate a reusable one (§5). |
| The tailnet name became `wealth-tracker-1` | The `tailscale-state` volume was lost, so the sidecar registered as a new node. Delete the stale node in the admin console, restore or recreate the volume, and check `APP_URL` and the registered bank redirect still match. |
| Name resolves but nothing answers, from the phone | The phone is not connected to the tailnet. `.ts.net` names resolve publicly and answer only over Tailscale. |
| **419 Page Expired** on login | `session.secure` is `true` while serving over `http://`. With the sidecar you are on HTTPS, so suspect a stale cookie first (see below); without it, set `SESSION_SECURE_COOKIE=false`. |
| `sc login` fails with `Platform secure storage failure: DBus error` | The CLI is reaching for the OS keyring, which no container has. The entrypoint writes a file-backed `config.toml` at startup — if you hit this, the image predates that fix: pull a newer `APP_VERSION`. |
| `pull` fails with `manifest unknown` | No image was published for that `APP_VERSION`. Check the tag exists under the repository's Packages, and that the Deploy workflow for that release actually succeeded. |
| `pull` fails with `denied` / `unauthorized` | The ghcr package is private. Make it public once under Packages → Package settings → Change visibility, or `docker login ghcr.io` on the host. |
| Deploy job fails at the ssh step with `permission denied` | The tailnet policy no longer matches: check `tag:prod` is still on the host (a re-auth can drop it) and that the `ssh` rule for `tag:ci` names the `deploy` user. |
| Deploy job hangs at the ssh step until it times out | The `ssh` rule for `tag:ci` is `"action": "check"`, which waits for a browser confirmation nobody will give. It must be `"accept"`. |
| Deploy job fails with `Refused. The only accepted command is…` | Something asked the `deploy` account for a command other than `deploy <app> <version>`. If it arrived as two words, the installed `/usr/local/sbin/deploy-shell` is newer than the workflow that called it (or the reverse): reinstall the wrapper from `scripts/deploy-shell.sh` and check the caller passes `app:`. |
| Deploy job fails with `Refused: '<name>' is not a deployable app` | The app is not in the wrapper's whitelist. Add it there and to `/etc/sudoers.d/deploy`, then reinstall the wrapper — the copy under `/usr/local/sbin` is what runs. |
| Deploy job fails at `tailscale up` with `403 calling actor does not have enough permissions` | The OAuth client is missing the **Auth Keys → Write** scope. `Devices → Write` alone is not enough: the action mints an auth key to register the ephemeral node. |
| Deploy job fails with `validating docker-compose.prod.yml: stat .: permission denied` | Something invoked the deploy script from a directory the running user cannot enter — the `deploy` account's home is mode 750. Both scripts now `cd` away from it; an older copy of `/usr/local/sbin/deploy-shell` predates that fix. |
| The workflow is green but the app is on the old version | The host script rolled back: it found a missing `.env` key, or `/up` never answered. The job log has the reason; the app is still up on the previous release. |
| Login page loops without an error | `app.url` does not match the address you opened. |
| Advisor never replies | Queue worker not running — check all three processes (§6). |
| Data never refreshes, Scalable keeps logging out | Scheduler not running, so `scalable:keep-alive` never fires. |
| **"Enable Banking non configurato"** with the credentials set, bank syncs failing | The `.pem` was never copied into the data directory (§8). Confirm with `grep "private key file not accessible" storage/logs/laravel.log`, then drop the cached empty bank list. |
| Bank sync fails only on one bank, `{"status":429}` in the log | That bank's rate limit, not a fault. 429s are deliberately not retried; the next scheduler run picks it up. |
| Bank consent rejected before you reach the bank | `ENABLE_BANKING_REDIRECT_URL` does not match the redirect registered on the Enable Banking application, character for character. Compare against `eb.redirect` above, not against `.env`. |
| Consent approved at the bank, app never learns about it | The browser could not reach the callback — usually Tailscale dropped on that device mid-flow. Reconnect and run the flow again. |

After fixing a cookie-related problem, retry in a **private window**: a stale
cookie from the broken origin reproduces the same error on a fixed server.

## If you later move to a VPS

Nothing in the application changes. What does:

- **The login stops being optional.** The app is publicly reachable, so the
  password is the only thing in front of it.
- **Drop the Tailscale sidecar, or keep it.** Keeping it is the least work and
  leaves the app unreachable except over the tailnet. To publish on a domain
  instead, remove the `tailscale` service and the `network_mode`/`depends_on`
  lines from `app`, restore a `ports:` entry bound to `127.0.0.1:8080:8000`,
  and put a reverse proxy (Caddy is the least work) in front terminating TLS.
- **Set `APP_URL` to the https domain**, or the bank-consent flow trips the
  browser's mixed-content block. Re-register the bank redirect on the new
  domain — the `.ts.net` one stops being reachable.
- **Scalable gets less convenient** — re-authenticating needs SSH rather than
  sitting down at the machine.
- **Backups are already off-site**, so that part needs no thought. The staging
  directory (`BACKUP_DIR`) still has to exist.
