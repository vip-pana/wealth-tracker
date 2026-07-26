# Roadmap

Ideas considered for after 1.0. Nothing here is committed work — it's a
backlog with scope decisions already made, so one can be picked up later
without re-litigating it. Ordered roughly by value-to-effort, not priority.

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

## Stop / interrupt a generating advisor reply — needs streaming first

A "Stop" button to interrupt the AI advisor while it's replying. A logical-only
cancel was tried and removed: it hid the reply in the UI, but couldn't free the
model.

**Why the simple version isn't enough.** The reply is produced by a *single*
queued worker making a *blocking, non-abortable* `AdvisorProvider::chat()` call
to the model. A cancel can mark the turn cancelled and have the job discard its
result, but the worker stays busy inside that call until the model finishes — so
a cancelled question still occupies the worker for its full generation time.

**What a real abort needs.** Switch chat generation to streaming and interrupt
it between chunks:
- `AdvisorProvider::chatStream()` already exists (unused for chat yet) — have the
  chat job stream instead of calling `chat()`.
- Between chunks, check a cancel flag on the assistant message; if set, stop
  consuming the stream and return, freeing the worker near-immediately.
- Frontend: a Stop button while `awaitingReply`, a `POST …/message/{id}/cancel`
  endpoint setting the flag, and the reply poll must keep *every* locally-
  cancelled turn cancelled so a late `done` can't resurrect it.
- Note the prior warning in `MessageController`: a synchronous streamed response
  "froze the whole server" — that's why generation is queued. Streaming must
  stay in the background job, not the web request.

## External account sync

Goal: pull balances from banks instead of typing them by hand.

- **Banks via open banking** — the viable path for an individual reading their
  *own* pre-linked accounts. Needs an HTTPS consent callback (a tunnel in dev)
  and per-provider consent that expires periodically (renewal is a manual
  re-consent, since PSD2 requires the user to be present).
- **Brokers** — no general open-banking coverage; broker balances stay manual
  unless a broker exposes its own API.

Sketch of the shape: a signed-JWT API client, balances flowing into an asset's
`value` via a fetch action, and `bank_connections` / `bank_accounts` tables
behind a `/banking` connect → consent → callback → link → disconnect flow, with
a Settings section to manage it.

## Inertia v3 opportunities (now that we're on v3)

The v2→v3 upgrade unlocked features we don't use yet. Ranked by value-to-effort
for this app; none are required, they're refinements to reach for when touching
the relevant area.

- **Instant visits — HIGH value, LOW effort.** Inertia swaps to the target page
  component immediately and merges server data when it arrives, so navigation
  feels instant. Opt-in per visit/link. The app has 7 top-level pages the user
  hops between; this is the single biggest perceived-speed win for the least
  work — flip it on for the main nav links and measure. *Effort: hours.*
- **`useHttp` for the advisor chat — MEDIUM value, MEDIUM effort.** The chat in
  `Conversation.tsx` hand-rolls optimistic send + rollback + in-flight guards
  with a raw axios call and manual `useState`. `useHttp` gives the same
  reactive state (`processing`/`errors`/`progress`/`isDirty`) and cancellation
  out of the box, shrinking that component's bespoke logic. Value is code
  simplicity + fewer edge cases, not a user-visible change. *Effort: ~1 day,
  touches a well-tested component so re-verify the 16 Conversation tests.*
- **Optimistic updates on toggles — LOW/MEDIUM value, LOW effort.** Small
  interactions (privacy hide/show, mark-notification-read, snapshot pin) could
  apply instantly and auto-rollback on error via the router/Form option,
  instead of waiting a round-trip. Nice polish; only worth it where the current
  wait is actually noticeable. *Effort: hours per interaction.*
- **`useLayoutProps` — LOW value here.** Lets layouts declare overridable
  defaults without provide/inject. We have a single `AppLayout` and no
  layout-prop juggling, so little to gain today; note it if the layout ever
  grows variants. *Effort: n/a until needed.*
- **Zero-config dev SSR — already in effect.** v3 does SSR in dev without a
  separate Node server. We disabled it under test (`INERTIA_SSR_ENABLED=false`
  in phpunit.xml) but it runs in dev; no action needed, listed so the behaviour
  isn't mistaken for a regression.

## Explicitly out of scope (decided, not forgotten)

Considered and set aside as a poor fit for a single-user personal tracker:

- Macro-level goal allocations UI (backend ready, but not relevant in practice)
- Tags, reminders, multi-user/teams
- Generic full-text search (ticker lookup above covers the real need)
