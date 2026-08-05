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

## Authentication

Single user, one password. No registration, no password reset, no OAuth — for one
account those add attack surface without removing risk. Every route sits inside a
`Route::middleware('auth')` group in `routes/web.php`, so a new route is protected
unless it is deliberately declared outside; `tests/Feature/Auth/AuthenticationTest.php`
pins that with a per-surface sweep.

**`/banking/callback` is deliberately outside the auth group.** The bank redirects
the browser there after consent and cannot present our session. Protecting it
breaks the consent flow only against the real bank, so a test guards it.

First run: `RequireSetup` (global on the web group, ordered before `auth` via
`$middleware->priority()`) diverts to `/setup`, which creates the account and
signs in. Once an account exists the route is closed server-side — it is what
creates the owner of every financial record, so hiding the link would not be
enough. Forgotten password: `php artisan user:password`, which also signs out
every existing session (the answer to a lost phone).

Sessions last 30 days and are encrypted (`SESSION_LIFETIME`, `SESSION_ENCRYPT`):
the app is opened from a phone, where re-entering the password each visit is
friction with no matching gain. Login is rate-limited to 5 attempts per
IP + email.

Email and password are changed from Settings → Account. Both re-check the
current password — the email is the login identifier, so changing it is as much
a credential change as the password. Changing the password deletes every *other*
session (keeping the current one) and clears `remember_token`: that is the
in-app answer to a lost phone, `user:password` being the terminal one. Note
`Auth::logoutOtherDevices()` is deliberately not used — without the
`AuthenticateSession` middleware, which this app does not run, it is a silent
no-op; the sessions are deleted directly instead.

## Backup & restore

<!-- exodia:section:variants -->
Dev DB lives at `~/wealth-tracker-data/database.sqlite`, bind-mounted into the container. Backups run in-app (`App\Actions\Backup\CreateBackup`): an atomic `VACUUM INTO` snapshot, encrypted (AES-256-CBC + HMAC) and uploaded to the disk named by `BACKUP_DISK` — nightly via the scheduler at 03:00, after every snapshot (queued `BackupDatabase` job), and on demand (Settings → "Backup ora", or `php artisan backup:run`). Restore with `php artisan backup:restore --list` then `backup:restore <key>`. `backup:health` (09:00) raises a notification when the newest off-site archive goes stale, since a backup chain that stops is otherwise silent. The former host-side `launchd` + `scripts/backup.sh` path was removed: it only worked on the Mac that had Docker and the Proton Drive client. Full procedure, encryption details and the test-suite DB-safety override: [../../docs/database.md](../../docs/database.md). **Never overwrite the live DB without explicit confirmation.** **`BACKUP_ENCRYPTION_KEY` must be stored outside the backup destination — without it archives are unrecoverable.**

## Integrations

The Scalable Capital sync uses the official `sc` CLI, installed in the image and run headless via a file-backed session (config.toml seeded into the `scalable-cli` volume by the dev compose command). Enable with `SCALABLE_CLI_ENABLED=true`; log in from Settings → "Collega" (in-app device code) or `docker exec -it wealth-tracker-app-1 sc login`. See the README "Scalable Capital sync" section.

## Localization / i18n

<!-- exodia:section:i18n -->
N/A — UI strings are Italian, hardcoded; no translation framework.

## L3 Data

<!-- exodia:section:l3 -->
- `variants.yaml`: per-variant behavioral differences (any axis the codebase varies along).
