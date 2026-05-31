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

## External account sync — built on a branch, verified live, awaiting merge

Goal: pull balances from banks instead of typing them. Researched in depth
(2026-05). Findings:

- **Banks via Enable Banking open banking** — the viable path. Its free
  "restricted mode" lets an individual read their *own* pre-linked accounts in
  production with no TPP licence or eIDAS certs (a licence is only needed to
  serve *third parties*). Signups are open. **This works.**
- **GoCardless/Nordigen** — was the obvious choice but **disabled new signups in
  July 2025**; abandoned in favour of Enable Banking.
- **Brokers (Scalable / Directa)** — no public API; open banking covers banks,
  not investment brokers, so brokers **stay manual**. Trading212 has a free
  official API and remains the only viable broker path *if* an account exists.
- Plaid (US-only) / Tink / wealthAPI are B2B with no self-service — not for a
  personal app.

A full Enable Banking integration is built on branch
`feat/enable-banking-open-banking` (4 WIP commits, **not merged to main**):
- **Phase 1** — `EnableBankingClient`: JWT RS256 signed with the app's PEM key,
  `aspsps` / `auth` / `sessions` / account balances; timeout+retry; inert when
  unconfigured. Tested with `Http::fake`.
- **Phase 2** — balances flow into an asset's `value` (Option A) via
  `FetchBankBalances`, wired into `FetchAllPrices`.
- **Phase 3** — `bank_connections` + `bank_accounts` tables; `/banking` routes
  (connect → consent → callback → link account to asset → disconnect); a
  Settings "Conti bancari" section.

**Verified live (2026-05-31):** read real balances end to end — Revolut 327,91 €
and Isybank 4.466,02 € — through the actual API with the user's own credentials.

**To use it / merge it:**
- An HTTPS consent callback is required: run a tunnel (cloudflared/ngrok) to
  `…/banking/callback` and set that URL both on the Enable Banking app and in
  `ENABLE_BANKING_REDIRECT_URL`. The free-tier tunnel URL changes per restart.
- The full in-app UI round-trip (click "Collega" → return from consent) was not
  driven live yet — only the pieces (auth/sessions/balance live, the rest via
  `Http::fake`). Drive it once with a tunnel before merging.
- Sessions expire (~90 days); renewal is a manual re-consent (PSD2 requires the
  user present) surfaced as "Riconnetti".
- The branch carries migrations adding `assets.bank_synced_at` and the two bank
  tables; the dev DB is rolled back so main stays aligned.
- Credentials are configured locally: `ENABLE_BANKING_APPLICATION_ID` and a PEM
  key at `~/wealth-tracker-data/enable-banking.pem` (kept out of git).

## Explicitly out of scope (decided, not forgotten)

These were considered and set aside as a poor fit for a single-user personal
tracker — listed so we don't reconsider them by accident:

- Macro-level goal allocations UI (backend ready, but not relevant in practice)
- Tags, reminders, multi-user/teams
- Generic full-text search (ticker lookup above covers the real need)

(Trash / restore-from-UI was once here but shipped — see the "Elementi
eliminati" section in Settings.)
