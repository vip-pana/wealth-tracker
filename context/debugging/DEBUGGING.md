<!-- purpose: How to diagnose common problems. Start here when something breaks. -->
# Debugging

<!-- exodia:section:intro -->
How to diagnose common problems. Start here when something breaks.

## Local Environment Setup

<!-- exodia:section:env-setup -->
Runs in Docker (container `wealth-tracker-app-1`); run commands inside it, not on the host. Node/PHP/pnpm versions are pinned in `package.json`, `composer.json`, and the Dockerfile — reference those. Vite dev server is on port 5173, the app on 8080. Env defaults in `.env.example`. Full ops detail: [../operations/OPERATIONS.md](../operations/OPERATIONS.md).

## How to use this module

<!-- exodia:section:how-to-use -->
1. Reproduce the symptom.
2. Search `playbooks.jsonl` for matching symptoms.
3. If found → follow the playbook's fix.
4. If not found → once solved, append a new playbook entry per the Self-Update Rules. Recurring footguns count: the footgun IS the symptom; name how it surfaces.

## Common Topics

<!-- exodia:section:topics -->
- **Vite / frontend** — after a container recreate or adding an export to a `lib/` module, Vite serves a stale pre-optimized bundle (e.g. `does not provide an export named X`, or 504 Outdated Optimize Dep). Clear `node_modules/.vite` and `docker compose up -d --force-recreate app`, then hard-refresh. (`pb-0001`)
- **Dates** — `Carbon::createFromFormat('Y-m-d', ...)` silently overflows out-of-range dates and throws on garbage; validate by round-tripping the formatted result. (`pb-0002`)
- **Tests / dev DB** — container env vars would point `RefreshDatabase` at the real DB; `tests/TestCase.php` forces in-memory SQLite at boot. Don't remove that override. (`pb-0003`)
- **CSV money** — European format `1.234,56` must not be parsed by naive comma→dot replace (gives `1.234`); handle the thousands separator. (`pb-0004`)

## L3 Data

<!-- exodia:section:l3 -->
- `playbooks.jsonl`: symptom → root cause → fix recipes. Includes recurring footguns (the footgun IS the symptom).
