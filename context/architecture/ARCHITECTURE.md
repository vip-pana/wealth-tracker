<!-- purpose: System shape, entry points, and module layout. Read this first to understand how the codebase is organized. -->
# Architecture

<!-- exodia:section:intro -->
A single-user personal wealth tracker built as a Laravel 12 + Inertia.js v2 monolith: controllers return server-rendered React pages with typed props, and there is **no REST/JSON API**.

## Entry Points & Routing

<!-- exodia:section:routing -->
All routes are declared in one file, `routes/web.php`. Controllers return `Inertia::render()` for GET routes and `redirect()->back()` for mutations — props flow straight to React, no JSON API. Do not introduce API endpoints or JSON responses.

### Key Files

- `routes/web.php` — every route
- `app/Http/Controllers/` — domain-grouped invokable controllers (Analytics, Assets, Categories, Goals, Pension, Prices, Snapshots, Backup)
- `app/Http/Requests/` — a `FormRequest` per mutation; validation never lives inline in controllers

## Modules & Boundaries

<!-- exodia:section:modules -->
- `app/Models/` — Eloquent models (source of truth for the data model; see [../glossary/GLOSSARY.md](../glossary/GLOSSARY.md))
- `app/Actions/` — single-purpose action classes extending `app/Actions/Action.php`; controllers stay thin and delegate (e.g. `Snapshots/`, `Dashboard/`, `Analytics/`, `Prices/`, `Input/`)
- `app/Http/Clients/` — external price feeds (Yahoo Finance, CoinGecko, Blockstream)
- `app/Jobs/` — queued work (e.g. `BackupDatabase`)
- `resources/js/Pages/` — Inertia pages (Dashboard, InputData, Analysis, Goal, Pension, Settings)
- `resources/js/Components/` — shared React (`Charts/`, `Data/`, `Layout/`, `ui/`)

## State Management

<!-- exodia:section:state -->
The backend owns all persistent state (SQLite via Eloquent). The frontend keeps only local component state; every mutation round-trips to a controller. Core tables (`assets`, `categories`, `goals`, pension entries) use soft deletes so deletions are recoverable (see [../operations/OPERATIONS.md](../operations/OPERATIONS.md)).

## Build

<!-- exodia:section:build -->
Vite + `laravel-vite-plugin` build the React/TS frontend; in dev the Vite server runs on port 5173 (a `public/hot` file points to it). Toolchain versions live in `package.json` and `composer.json` — reference them, do not copy.

## Runtime Model

<!-- exodia:section:runtime -->
Server-rendered React over Inertia (no separate SPA fetch layer). A database-backed queue worker (`php artisan queue:listen`) processes jobs; live prices are pulled from external HTTP clients on demand and on a daily schedule. Snapshots are point-in-time photos: created manually via `POST /snapshots`, never auto-computed on asset save.

## L3 Data

<!-- exodia:section:l3 -->
- `decisions.jsonl`: Architecture Decision Records for non-obvious choices.
