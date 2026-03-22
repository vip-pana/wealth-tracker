# Wealth Tracker — Agent Guide

## What this app does

Personal wealth tracker: log assets by category each month, take monthly snapshots, visualize net worth over time with charts and forecasts. No authentication — single-user local app.

## Stack

- **Backend**: Laravel 12, PHP 8.2, SQLite
- **Frontend**: React 19 + TypeScript (strict), Inertia.js v2, Tailwind CSS, Recharts
- **Package manager**: pnpm
- **Quality**: PHPStan level 9, Laravel Pint, ESLint v9, TypeScript strict

## Architecture

### No REST API

Controllers return `Inertia::render()` for GET routes and `redirect()->back()` for mutations. There is no JSON API — the frontend receives typed props directly from controllers via Inertia. Do not introduce API endpoints or JSON responses.

### Key directories

```
app/Http/Controllers/   — 5 controllers (Analytics, Asset, Category, Snapshot + base)
app/Http/Requests/      — FormRequest classes for all mutations
app/Models/             — Eloquent models with PHPDoc @property annotations
resources/js/Pages/     — Inertia page components (Dashboard, InputData, Analysis, Settings)
resources/js/Components/— Shared React components (Charts/, Data/, Layout/, UI/)
database/migrations/    — SQLite schema
routes/web.php          — All routes defined here
```

### PHP conventions

- `declare(strict_types=1)` at the top of every PHP file
- Validation lives in `FormRequest` classes in `app/Http/Requests/`, never inline in controllers
- PHPDoc `@property` annotations on all Eloquent models (required for PHPStan)
- Generic types on all Eloquent relation methods: `HasMany<X, $this>`, `BelongsTo<X, $this>`
- Use `$request->string('key')->value()` / `$request->integer('key')` instead of `$request->input()` or `$request->get()` to avoid `mixed` types (PHPStan level 9 requirement)

### Frontend conventions

- TypeScript strict mode — no implicit `any`
- React components use named exports
- Pages receive props via Inertia — define prop types at the top of each page file

## Data model

| Table | Key columns | Notes |
|---|---|---|
| `categories` | name, color, icon, sort_order | Color is a hex string `#RRGGBB` |
| `assets` | category_id, name, value (float), date, notes | One entry per asset per month |
| `monthly_snapshots` | date, total_value | Computed from assets, not live |
| `snapshot_category_values` | snapshot_id, category_id, value | Per-category breakdown of a snapshot |

Snapshots are created manually via `POST /snapshots` — they are not auto-computed on asset save.

## Commands

| Task | Command |
|---|---|
| Start dev server | `composer dev` |
| PHP lint check | `composer lint` |
| PHP lint fix | `composer lint:fix` |
| PHP static analysis | `composer analyse` |
| JS lint check | `pnpm run lint` |
| JS lint fix | `pnpm run lint:fix` |
| TypeScript check | `pnpm run typecheck` |
| Run migrations | `php artisan migrate` |

## Pre-push checks (run in order)

1. `composer lint` — Laravel Pint style check
2. `composer analyse` — PHPStan level 9
3. `pnpm run lint` — ESLint
4. `pnpm run typecheck` — TypeScript strict

All four must pass before pushing. Fix style violations automatically with `composer lint:fix` / `pnpm run lint:fix`.
