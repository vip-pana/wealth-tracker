<!-- purpose: Code conventions, idioms, and shared utilities. Read this before introducing new patterns. -->
# Design Patterns

<!-- exodia:section:intro -->
> **Progressive disclosure:** this file holds short guardrails only (do/don't, 2-3 lines per topic). Detailed explanations live in `./docs/`. Drafted sections cite files and link to deep dives when a topic warrants more than ~3 lines.

<!-- exodia:section:body -->
## PHP conventions

- `declare(strict_types=1)` at the top of every PHP file.
- Validation goes in `FormRequest` classes under `app/Http/Requests/`, never inline.
- Every Eloquent model carries `@property` PHPDoc and generic relation types (`HasMany<X, $this>`); required for PHPStan level 9.
- Read request input with `$request->string('k')->value()` / `$request->integer('k')`, never `$request->input()`/`get()` (avoids `mixed`).

## Frontend conventions

- TypeScript strict — no implicit `any`; React components use named exports.
- Inertia pages define their prop types at the top of the page file (`resources/js/Pages/`).
- Pure money/date/chart logic lives in tested helpers under `resources/js/lib/` (e.g. `formatters.ts`, `metrics.ts`, `goalMath.ts`) — extract it there, don't inline it in JSX.
- **Part-to-whole is a bar, not a donut.** Bars won over a donut for the milestone target allocation: a donut can't compare near-equal slices (two categories at 5%), drops a 0% category entirely, and shows one step in isolation — which loses the glide path, the whole point of a milestone. Donuts stay where the job is a single at-a-glance snapshot (`Goal/SmallDonut.tsx`, current vs target). Same allocation, two forms by context: `Advisor/Widgets/MilestoneAllocationBar.tsx` is the 2px stacked bar for chat bubbles, `Goal/MilestoneAllocationDetail.tsx` the per-category bars with a delta vs the previous step for the roomier goal card. When comparing allocations across steps, compare **effective** shares (`applyAllocationCaps`) — a binding `cap_amount` makes nominal percentages report movement that doesn't exist.

## Controllers & actions

- Controllers are thin and invokable; business logic lives in `app/Actions/*` classes extending `app/Actions/Action.php`. Don't fatten controllers.
- **Period navigation = `?month=YYYY-MM-01`.** A page that shows one month at a time takes the month as a query param pinned to the 1st, validated by a FormRequest with a `month(): string` accessor defaulting to the current month, and resolved by an Action that returns `month` + `availableMonths` among its props. The page navigates with `router.get(url, { month })` and a prev / `Select` / next control. Used by Bilancio (`Assets\IndexController`) and Entrate e Uscite (`Cashflow\IndexController`). No Laravel paginator is used anywhere. Two flavours of the visit: `preserveState: false` (Bilancio) for a clean remount, or a partial reload — `only: ['<list prop>', 'month']` with `preserveState` + `preserveScroll` (Cashflow) — when the page holds local state worth keeping and the other props are month-independent.
- **A month-scoped page may carry whole-history props** — Bilancio's positions card and Cashflow's monthly averages. Label them in the UI as spanning the full history, otherwise they read as wrong when the user navigates to an old month.

## Testing

- PHPUnit (`tests/Unit`, `tests/Feature`) with in-memory SQLite; Vitest for frontend `lib/` helpers. See [../../docs/testing.md](../../docs/testing.md) for the full guide and the dev-DB safety override.

## Claude-specific rules

- Don't add docblocks, comments, or type annotations to code you didn't change.
- Don't create new files unless strictly necessary — prefer editing existing ones.
- Don't add error handling for scenarios that can't happen given the current architecture.

## L3 Data

<!-- exodia:section:l3 -->
- `reviews.jsonl`: PR review checks, migrations, anti-patterns.
