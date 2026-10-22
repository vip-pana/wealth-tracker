# Wealth Tracker

A single-user personal wealth tracker: log your assets by category each month,
take point-in-time snapshots, and follow your net worth through charts,
forecasts and a savings goal. One account, created on first run and protected
by a password — see [First run](#first-run).

## Features

- **Dashboard** — net worth over time, allocation, and portfolio insights.
- **Assets & snapshots** — log assets by category each month; freeze monthly
  snapshots to track history honestly. The same section shows the share
  positions with their true per-position return and transaction history.
- **Goal** — a savings goal with milestones and progress tracking.
- **Cashflow** — optional bank-transaction ingestion (income vs. expense vs.
  internal transfer), derived from open-banking data.
- **AI advisor** — an in-app assistant that reasons over your own portfolio.
  Runs against a local model (Ollama) by default, or a cloud model; it
  interviews you to build a risk profile and helps you shape your goal. It is
  an informational tool, not a financial adviser: it never tells you to buy,
  sell or hold a specific instrument, and that limit is enforced in code as
  well as in the prompt — see
  [docs/ai-advisor-firewall.md](docs/ai-advisor-firewall.md).

## Stack

- **Backend**: Laravel 12, PHP 8.4, SQLite
- **Frontend**: React 19 + TypeScript (strict), Inertia.js v2, Tailwind CSS, Recharts
- **AI**: [Prism](https://github.com/prism-php/prism) as the LLM transport (Ollama / Anthropic / OpenAI-compatible)
- **Package manager**: pnpm

## Running it

Two different things, and it is worth picking the right one:

- **Trying it out, or working on it** — the development stack below. Source
  mounted, Vite with hot reload, `php artisan serve`. Never expose it.
- **Running it for real**, on a machine that stays on —
  [docs/self-hosting.md](docs/self-hosting.md). A released image from ghcr.io, a
  real web server, the queue worker and the scheduler, backups. Start there; it
  assumes nothing about your setup.

## Running with Docker (recommended)

```bash
cp .env.example .env
docker compose up
```

The app will be available at [http://localhost:8080](http://localhost:8080).

A new install starts with a usable set of categories and no goal — create your
own from the Goal page.

The SQLite database lives on the host at `~/wealth-tracker-data/database.sqlite`
(bind-mounted into the container), so it's a normal file you can back up or
inspect. Override the location with `WEALTH_TRACKER_DATA_DIR`. See
[docs/database.md](docs/database.md) for backups and restore.

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

## First run

The first time the app starts there is no account, and every page redirects to
`/setup`, which creates the one account that owns all of your financial data.

That page asks for a **setup code** before it will create anything. Without it,
a fresh instance is a land grab: whoever loads `/setup` first becomes the owner,
and on an instance reachable from the internet that window is open from the
moment the container starts until you get to a browser. The code proves you are
the person who installed the app, which is the only thing the app can check
before an account exists.

Three ways to read it — they all show the same code:

```bash
docker compose logs app | grep "Setup code"   # printed at startup
cat ~/wealth-tracker-data/setup-token.txt     # the data directory on the host
php artisan setup:token                       # or from a shell in the container
```

Paste it into `/setup`, choose an email and a password, and you are in. The code
is **deleted** as soon as the account exists — `/setup` is closed server-side
from then on, so there is nothing left for it to guard.

If you think the code leaked before you used it, `php artisan setup:token --new`
replaces it and invalidates the old one.

> There is no password reset. If you forget the password, the way back in is
> `php artisan user:password` from a shell on the machine.

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

- **Self-hosting** (running it on a machine that stays on) —
  [docs/self-hosting.md](docs/self-hosting.md)
- **Database & backups** — [docs/database.md](docs/database.md)
- **Enable Banking** (open-banking bank balances) —
  [docs/enable-banking-usage.md](docs/enable-banking-usage.md)
- **Contributing** — [CONTRIBUTING.md](CONTRIBUTING.md)

### AI advisor

The advisor is configured in `.env` via `ADVISOR_DRIVER`:

- `ollama` (default) — a local model; set `OLLAMA_MODEL` (and `OLLAMA_BASE_URL`)
  to a tool-capable model. Inert until a model is set.
- `anthropic` — Claude via the Anthropic API (`ANTHROPIC_API_KEY`), model in
  `ADVISOR_ANTHROPIC_MODEL`.
- `regolo` — an OpenAI-compatible endpoint (`REGOLO_BASE_URL` / `REGOLO_API_KEY`
  / `REGOLO_MODEL`).

The advisor's system prompts live in `resources/advisor-prompts/*.txt`. Those
files are gitignored; the committed `*.txt.example` files are used as a fallback,
so the advisor works out of the box. Copy an `.example` to its `.txt` to
customize a prompt without committing it.

What a prompt file **cannot** change is the boundary. `App\Advisor\AdvisorBoundaries`
is prepended in source to whatever prompt is loaded, and every reply is screened
against your own holdings before it is stored and again before it is shown.
Customising tone is a config edit; removing the boundary is a source edit.
[docs/ai-advisor-firewall.md](docs/ai-advisor-firewall.md) describes the three
layers and, explicitly, where they stop.

### Scalable Capital sync

Syncs Scalable Capital broker balances into assets via the **official Scalable
CLI** (`sc`), which runs headless in the container with a file-backed session.
Optional: skip it entirely by leaving `SCALABLE_CLI_ENABLED` unset.

Enable it in `.env`, then clear the config:

```
SCALABLE_CLI_ENABLED=true
SCALABLE_CASH_CATEGORY_ID=<id of your "Liquidità" category>
SCALABLE_CASH_ASSET_NAME=Scalable Liquidità
```

```bash
docker compose exec app php artisan config:clear
```

Set each holding's **ISIN** in its asset (edit modal) so the sync can match it;
uninvested cash (derived as total − securities − crypto) is written to the asset
named by `SCALABLE_CASH_ASSET_NAME` in the given category.

**Login**: from the app, **Impostazioni → Scalable Capital → "Collega /
Riconnetti"** — a link and device code appear; open the link, enter the code and
complete 2FA on any device. The session is long-lived; reconnect the same way
when it lapses, or from a terminal with `docker exec -it wealth-tracker-app-1 sc
login`. The daily 06:00 price job then reads balances automatically.

## License

Copyright (C) 2026 Vincenzo Panacciulli

Wealth Tracker is free software, licensed under the
[GNU Affero General Public License v3.0](LICENSE) (`AGPL-3.0-only`).

### What this means if you self-host it

**Run it, for yourself, however you like.** Install it on your own machine or
server, read the code, change it, keep your changes to yourself. The licence
asks nothing of you. This is the normal case and it stays free.

**Give a copy to someone else** — the code, a build, a Docker image — and the
copy travels with its source and with this licence. You cannot hand out a
modified binary and keep the changes private.

**Modify it and let other people use it over a network, and you must publish
your modified source to those people.** This is section 13 of the AGPL, and it
is what sets this licence apart from the GPL. It applies even though you never
distribute a file: running a modified version as a service that others use is
enough to trigger it. You must offer those users the complete corresponding
source of the version you are running — free of charge, downloadable from a
server, and offered visibly from the application's interface.

Two clarifications, because this clause is often read as stricter than it is:

- It triggers on *modification*. Running this project unmodified for your
  family, your flatmates, or your company changes nothing.
- It triggers towards *the users of your service*, not towards the public. You
  are not required to publish a fork on GitHub — though doing so is by far the
  simplest way to comply.

If you do run a modified version for others, the easiest way to satisfy section
13 is to keep (or add) a link to your source in the application's settings page.

### Commercial licensing

If you want to build on Wealth Tracker in a way the AGPL does not allow — in
particular, offering a modified version as a hosted service without releasing
your changes — a separate commercial licence is available. Get in touch at
<v.panacciulli@proton.me>.

### Contributing

Contributions are welcome. Because the project is offered under both the AGPL
and separate commercial terms, contributors are asked to accept the
[Contributor License Agreement](CLA.md) first. See
[CONTRIBUTING.md](CONTRIBUTING.md).

### Third-party code

Wealth Tracker is built on the [Laravel](https://laravel.com) application
skeleton, which is copyright (c) Taylor Otwell and licensed under the MIT
licence. Dependencies retain their own licences; see `composer.lock` and
`pnpm-lock.yaml`.
