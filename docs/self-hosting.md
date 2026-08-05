# Running it on your own machine

How to move the app off the development Mac and onto a machine that stays on — a
box at home reached over Tailscale, or a VPS. The application code is identical
either way; only the exposure differs, so switching later costs nothing.

## What you need

- A Linux machine that stays powered on, with Docker and the Compose plugin.
- The repo, checked out on that machine.
- The secrets from the current `.env` (see [Move the secrets](#3-move-the-secrets)).

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

`SESSION_LIFETIME=43200` and `SESSION_ENCRYPT=true` should already be set; keep
them.

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

## 5. Reach it from your phone: Tailscale

Tailscale is a private network between your own devices. The app is never
published to the internet, so the login is a second lock rather than the only
one.

```bash
curl -fsSL https://tailscale.com/install.sh | sh
sudo tailscale up
```

Install Tailscale on your phone, sign in with the same account, and the machine
appears by name.

Two settings are needed, and the second one is the step that trips people up:

```bash
tailscale ip -4          # the machine's tailnet address, 100.x.y.z
```

In `.env`:

```
BIND_ADDRESS=100.x.y.z
APP_URL=http://<machine-name>.<your-tailnet>.ts.net:8080
```

Then recreate — `.env` changes need `--force-recreate`, not a restart:

```bash
docker compose -f docker-compose.prod.yml up -d --force-recreate
```

> **Why `BIND_ADDRESS` matters.** The compose file defaults the port to
> `127.0.0.1`, which is right for a VPS behind a reverse proxy but refuses
> connections arriving over Tailscale — that traffic lands on the tailnet
> interface, not on loopback. The symptom is an app that answers fine on the
> machine itself and times out from the phone. Binding to the tailnet IP fixes
> it while keeping the app unreachable from the LAN and the internet.
>
> **Never use `0.0.0.0`.** That publishes the app to the whole local network,
> discarding the reason for hosting it privately.

Check it from the machine before reaching for the phone:

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://$(tailscale ip -4):8080/up
```

`200` means the phone will reach it too.

## 6. Start it

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

The first build takes a few minutes (PHP extensions, Composer, the frontend
bundle). It runs three processes: the web server, the queue worker (the AI
advisor replies from a queued job) and the scheduler (prices, transaction
import, the daily snapshot, the Scalable keep-alive, and the nightly backup).

Verify all three, rather than assuming:

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

On a home machine with no public domain, nothing changes: the consent redirect
still needs the throwaway tunnel (`scripts/tunnel.sh`), run when you connect or
renew a bank.

On a VPS with a stable domain this gets simpler — register the redirect URL once
with Enable Banking, set `ENABLE_BANKING_REDIRECT_URL`, and the tunnel is no
longer needed.

## Everyday operation

```bash
# Update to the latest code
git pull && docker compose -f docker-compose.prod.yml up -d --build

# Logs
docker compose -f docker-compose.prod.yml logs -f app

# Shell
docker compose -f docker-compose.prod.yml exec app bash

# Forgotten password (also signs out every session)
docker compose -f docker-compose.prod.yml exec -it app php artisan user:password
```

## If you later move to a VPS

Nothing in the application changes. What does:

- **The login stops being optional.** The app is publicly reachable, so the
  password is the only thing in front of it.
- **Put HTTPS in front.** A reverse proxy (Caddy is the least work) terminating
  TLS on the host, forwarding to `127.0.0.1:8080`. Keep the localhost binding.
- **Set `APP_URL` to the https domain**, or the bank-consent flow trips the
  browser's mixed-content block.
- **Scalable gets less convenient** — re-authenticating needs SSH rather than
  sitting down at the machine.
- **Backups are already off-site**, so that part needs no thought. The staging
  directory (`BACKUP_DIR`) still has to exist.
