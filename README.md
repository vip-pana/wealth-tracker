# Wealth Tracker

Personal wealth tracker: log assets by category each month, take monthly snapshots, and visualize net worth over time with charts and forecasts. No authentication — single-user local app.

## Stack

- **Backend**: Laravel 12, PHP 8.4, SQLite
- **Frontend**: React 19 + TypeScript (strict), Inertia.js v2, Tailwind CSS, Recharts
- **Package manager**: pnpm

## Running with Docker (recommended)

```bash
cp .env.example .env
docker compose up
```

The app will be available at [http://localhost:8080](http://localhost:8080).

The SQLite database is persisted in a named Docker volume (`sqlite_data`).

### Accessing from a phone on the same Wi-Fi

Start with `DEV_HOST` set to your computer's LAN IP so the Vite assets are
served from that address instead of `localhost`:

```bash
DEV_HOST=192.168.1.7 docker compose up   # use your machine's LAN IP
```

Then open `http://192.168.1.7:8080` from the phone (same Wi-Fi, computer
awake, container running). Without `DEV_HOST` it defaults to `localhost`
for normal desktop use.

## Running locally

**Requirements**: PHP 8.4+, Composer, Node.js 22+, pnpm

```bash
cp .env.example .env
composer install
pnpm install
php artisan migrate
composer dev        # starts all services (PHP server + Vite)
```

The app will be available at [http://localhost:8000](http://localhost:8000).

## Development commands

| Task | Command |
|---|---|
| Start dev server | `composer dev` |
| Run migrations | `php artisan migrate` |
| PHP lint check | `composer lint` |
| PHP lint fix | `composer lint:fix` |
| PHP static analysis | `composer analyse` |
| JS lint check | `pnpm run lint` |
| JS lint fix | `pnpm run lint:fix` |
| TypeScript check | `pnpm run typecheck` |
| Frontend tests | `pnpm run test` |
| Backend tests | `php artisan test` |
| Enable the pre-push hook | `composer setup-hooks` |

## Pre-push checks

A pre-push git hook (`.githooks/pre-push`) runs the full gate and **blocks
the push** if anything fails. Enable it once per clone:

```bash
composer setup-hooks   # sets git config core.hooksPath .githooks
```

The hook (and CI, on every push/PR) runs, in order:

1. `composer lint` — Laravel Pint style check
2. `composer analyse` — PHPStan level 9
3. `pnpm run lint` — ESLint
4. `pnpm run typecheck` — TypeScript strict
5. `pnpm run test` — Vitest (frontend)
6. `php artisan test` — PHPUnit (backend)

To bypass once (discouraged): `git push --no-verify`.

## Optional integrations

- **Database & backups** — [docs/database.md](docs/database.md)
- **Enable Banking** (open-banking bank balances) —
  [docs/enable-banking-usage.md](docs/enable-banking-usage.md)

### Scalable Capital sync (macOS)

Syncs Scalable Capital broker balances into assets. **Stopgap** until the
official Scalable CLI is allowlisted — it uses an unofficial local proxy that
may break and may violate Scalable's ToS; use at your own risk. Optional: skip
it entirely by leaving `SCALABLE_BALANCE_URL` blank.

Scalable has no public API, so a small Node proxy runs **on the Mac** (kept
alive by a launchd agent). You log in once through a real browser with 2FA; the
proxy holds the ~8h session and the app reads balances from it. The 2FA login
must happen on the Mac desktop — the container can't open a browser.

**Setup on a new machine** (needs `git`, `node`, `corepack`, `openssl`, `curl`):

```bash
./scripts/scalable-proxy-setup.sh        # clones the proxy, installs deps +
                                         # Chrome, starts a launchd agent, prints a token
```

Then set in `.env` (the installer prints the token) and clear the config:

```
SCALABLE_BALANCE_URL=http://host.docker.internal:3141
SCALABLE_GATEWAY_TOKEN=<printed token>
SCALABLE_CASH_CATEGORY_ID=<id of your "Liquidità" category>
SCALABLE_CASH_ASSET_NAME=Scalable Liquidità
```

```bash
docker compose exec app php artisan config:clear
```

Set each holding's **ISIN** in its asset (edit modal) so the sync can match it.

**Daily use**: the proxy stays on; when the session expires, click
**Impostazioni → Scalable Capital → "Collega / Riconnetti"** (opens a browser on
the Mac for 2FA). Manage the agent with `launchctl load/unload
~/Library/LaunchAgents/com.vincenzo.scalable-proxy.plist`.

> Note: the default `pnpm` shim crashes under Node 23 (`node:sqlite`), so the
> scripts use `corepack pnpm@9` and call `node`/`tsx` directly.

Full guide & troubleshooting: [docs/scalable-proxy.md](docs/scalable-proxy.md).
