# Changelog

All notable changes to Wealth Tracker are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-05-30

First stable release. A single-user personal wealth tracker: log assets by
category each month, take point-in-time snapshots, and watch net worth evolve
through charts, forecasts and goals.

### Tracking & input
- Log assets per category each month, by manual value or by ticker + quantity.
- Live prices for ETFs (Yahoo Finance) and crypto, refreshed daily and on demand.
- Each ticker row shows how fresh its price is and flags prices older than 24h or missing.
- Copy a whole month's assets forward when starting a new month.
- Fondo Pensione tracked as an annual, year-end illiquid asset on its own page.

### Snapshots & net worth
- Take a snapshot any day (not just monthly); each is a dated photo of net worth.
- Current net worth shown live on the input page.
- A snapshot freezes per-category values, so historical charts never drift.

### Analysis & goals
- Dashboard: net-worth line, allocation donut, stacked bars, growth rate,
  month-over-month comparison and a forecast, each at category or macro level.
- Analysis page with category and date-range filters, pagination and CSV export.
- One savings goal with target value, target date, per-category allocation
  targets and milestones, plus a current-vs-target allocation comparison.

### Data safety
- Soft delete across assets, categories, goals and pension entries.
- Undo a deletion straight from the toast.
- Automatic database backup nightly, after every snapshot, and on demand,
  using atomic SQLite snapshots synced to cloud storage.
- CSV import/export with a documented template.

### Interface
- Light and neutral dark themes, collapsible sidebar.
- Responsive across desktop and mobile (drawer navigation, stacked cards,
  mobile-friendly tables and dialogs).

### Quality & tooling
- Laravel 12 / PHP 8.4 backend, React 19 + TypeScript (strict) via Inertia.js.
- PHPStan level 9, Laravel Pint, ESLint and TypeScript strict all enforced.
- 106 PHP tests and 53 frontend tests covering the money paths
  (valuation, forecast, growth, CSV import/export, snapshot state, dashboard builders).
- A shared pre-push hook and GitHub Actions CI run the full gate in Docker.

[1.0.0]: https://github.com/vip-pana/wealth-tracker/releases/tag/v1.0.0
