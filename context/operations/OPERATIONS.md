<!-- purpose: Environments, configuration, deploys, and any per-variant behavior. Read when touching env vars, build config, or anything that differs between environments or variants. -->
# Operations

<!-- exodia:section:intro -->
How to build, test, run, and back up the app. Single-user, local-first; runs in Docker against a bind-mounted SQLite file.

## Environments

<!-- exodia:section:environments -->
Local dev only — no staging/prod tiers. Config defaults live in `.env.example` (SQLite, `QUEUE_CONNECTION=database`, cache=database). Docker runs five processes via `concurrently`: PHP serve, a queue worker (`queue:listen`), the scheduler runner (`schedule:work`), pail logs, and the Vite dev server; the container is `wealth-tracker-app-1`. **Run project commands inside that container, not on the host** (host `node_modules`/`vendor` differ from the container's). The same process list is mirrored in `composer dev`.

The Laravel scheduler is defined in `routes/console.php` (`prices:fetch` daily at 06:00) and only runs because `schedule:work` is in the start command — defining a schedule is not enough without a runner.

## Commands

<!-- exodia:section:config -->
Canonical list is in `composer.json` / `package.json` scripts — reference, don't copy. Key ones:
- `composer dev` — start everything; `php artisan migrate` — migrations.
- `composer lint` / `composer lint:fix` — Pint; `composer analyse` — PHPStan level 9.
- `composer rector` / `composer rector:fix` — Rector (PHP 8.4 upgrade + code-quality + dead-code, config in `rector.php`). `composer rector` is a dry-run and part of the gate (it fails if any fix is pending); apply with `rector:fix`, then `lint:fix`.
- `pnpm run lint` / `lint:fix` — ESLint; `pnpm run typecheck` — tsc; `pnpm run test` — Vitest.
- `php artisan test` — PHPUnit.

## Deploy

<!-- exodia:section:deploy -->
No deploy pipeline. The quality gate has two forms:
- **Pre-push hook** (`.githooks/pre-push`, enable with `composer setup-hooks`): the same seven checks **in order, inside Docker**: Pint → PHPStan → Rector (dry-run) → ESLint → tsc → Vitest → PHPUnit.
- **CI** (`.github/workflows/ci.yml`): the same checks split into two parallel jobs — a `php` job (Pint, PHPStan, Rector, PHPUnit, with a `vendor` cache keyed on `composer.lock`) and a `js` job (ESLint, tsc, Vitest, with pnpm cache). `concurrency` cancels superseded runs on the same ref.

Dependency updates are automated by Dependabot (`.github/dependabot.yml`): weekly grouped PRs for composer, npm (pnpm-aware), and github-actions, all `chore(deps)`/`chore(ci)`.

Releases are automated by release-please (`.github/workflows/release.yml`, config in `.release-please-config.json`, version tracked in `.release-please-manifest.json` + `version.txt`). It reads conventional commits on `main`, keeps a rolling release PR that bumps the version and updates `CHANGELOG.md`; merging that PR creates the git tag. Do not hand-edit `CHANGELOG.md` release sections or tag manually (current: `v1.0.0`).

## Backup & restore

<!-- exodia:section:variants -->
Dev DB lives at `~/wealth-tracker-data/database.sqlite`, bind-mounted into the container. Backups are atomic `sqlite3 .backup` / `VACUUM INTO` snapshots synced to Proton Drive — nightly (launchd at 03:00), after every snapshot (queued `BackupDatabase` job), and on demand (Settings → "Backup ora", or `~/wealth-tracker-data/backup.sh`). Full restore procedure and the test-suite DB-safety override: [../../docs/database.md](../../docs/database.md). **Never overwrite the live DB without explicit confirmation.**

## Integrations

The Scalable Capital sync uses the official `sc` CLI, installed in the image and run headless via a file-backed session (config.toml seeded into the `scalable-cli` volume by the dev compose command). Enable with `SCALABLE_CLI_ENABLED=true`; log in from Settings → "Collega" (in-app device code) or `docker exec -it wealth-tracker-app-1 sc login`. See the README "Scalable Capital sync" section.

## Localization / i18n

<!-- exodia:section:i18n -->
N/A — UI strings are Italian, hardcoded; no translation framework.

## L3 Data

<!-- exodia:section:l3 -->
- `variants.yaml`: per-variant behavioral differences (any axis the codebase varies along).
