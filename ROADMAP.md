# Roadmap

Ideas discussed for after 1.0. Nothing here is committed work — it's a
considered backlog with scope decisions already made, so we can pick one up
later without re-litigating it. Ordered roughly by value-to-effort, not priority.

## Robustness

### Price-fetch fallback to a secondary API
When the primary live-price fetch for a ticker fails, fall back to a second
source instead of silently keeping a stale value. Continues the "honest prices"
line that shipped in 1.0 (`priceFreshness`). Touches `app/Http/Clients/` and the
`app/Actions/Prices/*` actions. Low risk, high fit. **Strongest near-term candidate.**

### Richer feedback on a failed price refresh
The "Aggiorna prezzi" button always reports success even when some tickers
failed. Surface *which* tickers couldn't be refreshed. Small, pairs naturally
with the fallback above.

### Validation / anomaly warnings on input
Warn on suspicious values at entry time (e.g. an asset that jumps ±500% vs the
previous month — likely a typo). Advisory, not blocking.

## Insight (needs a design pass first)

Turn charts into stated conclusions. **Open question, deliberately unresolved:**
which insights are genuinely non-obvious vs. already visible at a glance? YTD
growth is visible from the chart; "at the current pace you reach your goal by
[date]" is not. Before building, decide the 2-3 that earn their place. Likely
candidates: goal-ETA at current pace, best/worst month, period-over-period
comparison (quarter vs quarter, not just month-over-month).

## Ticker lookup / autocomplete

When adding a ticker-based asset, search and select from a list instead of
typing the symbol by hand (reduces typos like `ISAC` vs `ISAC.MI`). This is a
ticker search, *not* full-text search across the app. Touches the asset form
plus a new search endpoint (probably backed by the same price-provider APIs).

## Multi-currency — scope: BOTH (display + on-entry)

Decided scope: store each asset in its **original currency** AND be able to view
everything **converted** to a primary currency. The fullest, costliest option:
needs a `currency` field on assets, a historical exchange-rate feed, and
conversion both on write (freeze rate at entry for snapshots) and on read
(dashboard/snapshot totals in the primary currency). Motivation: convert
snapshots to a different currency, and convert a foreign-currency asset to the
primary currency at entry. **Future, not urgent.**

## Recurring expenses — scope: register/list only

A new dimension: *flows* (money out each month), distinct from the current
*wealth* model (what you own). Scope decided: a simple register — a page to log
recurring expenses (name, amount, frequency, category) with a monthly/annual
total. **No link to net worth / forecast for now.** Examples: subscriptions,
mortgage, rent, loans. It's a self-contained mini-module; keep it isolated from
the asset/snapshot model unless we later decide to integrate it.

## Explicitly out of scope (decided, not forgotten)

These were considered and set aside as a poor fit for a single-user personal
tracker — listed so we don't reconsider them by accident:

- Macro-level goal allocations UI (backend ready, but not relevant in practice)
- Trash / restore-from-UI (soft delete + toast undo already cover real need)
- Tags, reminders, multi-user/teams
- Generic full-text search (ticker lookup above covers the real need)
