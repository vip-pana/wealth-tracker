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

## Controllers & actions

- Controllers are thin and invokable; business logic lives in `app/Actions/*` classes extending `app/Actions/Action.php`. Don't fatten controllers.

## Testing

- PHPUnit (`tests/Unit`, `tests/Feature`) with in-memory SQLite; Vitest for frontend `lib/` helpers. See [../../docs/testing.md](../../docs/testing.md) for the full guide and the dev-DB safety override.

## Claude-specific rules

- Don't add docblocks, comments, or type annotations to code you didn't change.
- Don't create new files unless strictly necessary — prefer editing existing ones.
- Don't add error handling for scenarios that can't happen given the current architecture.

## L3 Data

<!-- exodia:section:l3 -->
- `reviews.jsonl`: PR review checks, migrations, anti-patterns.
