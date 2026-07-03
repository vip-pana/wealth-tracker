# Testing Guide

## Setup

Tests use PHPUnit with an in-memory SQLite database. Enable it in `phpunit.xml` by uncommenting:

```xml
<env name="DB_CONNECTION" value="sqlite"/>
<env name="DB_DATABASE" value=":memory:"/>
```

Use `RefreshDatabase` in test classes that hit the database — it runs migrations fresh for each test.

## Running tests

```bash
php artisan test              # all suites
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature
php artisan test --filter=StoreAssetRequestTest
```

## Test organization

```
tests/
  Unit/
    Requests/     — FormRequest validation rules (isolated, no HTTP)
    Models/       — model methods and computed properties
  Feature/
    Controllers/  — full HTTP flow via Inertia (creates DB records, checks redirects)
```

## FormRequest tests (Unit)

Test validation rules in isolation — no HTTP request needed:

```php
use App\Http\Requests\StoreAssetRequest;
use Illuminate\Support\Facades\Validator;

it('requires category_id', function () {
    $rules = (new StoreAssetRequest())->rules();
    $v = Validator::make([], $rules);
    expect($v->errors()->has('category_id'))->toBeTrue();
});

it('passes with valid data', function () {
    $rules = (new StoreAssetRequest())->rules();
    $v = Validator::make([
        'category_id' => 1,
        'name' => 'ETF',
        'value' => 1000.00,
        'date' => '2025-01-01',
    ], $rules);
    expect($v->passes())->toBeTrue();
});
```

## Feature / controller tests

Use `$this->post()` / `$this->put()` / `$this->delete()` and assert redirects + DB state:

```php
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores an asset', function () {
    $category = Category::factory()->create();

    $this->post('/assets', [
        'category_id' => $category->id,
        'name' => 'ETF',
        'value' => 1000,
        'date' => '2025-01-01',
    ])->assertRedirect();

    $this->assertDatabaseHas('assets', ['name' => 'ETF']);
});
```

## Factories

Factories live in `database/factories/`. Create one per model when needed — Laravel will auto-discover them.

```php
// database/factories/CategoryFactory.php
public function definition(): array
{
    return [
        'name' => fake()->word(),
        'color' => '#' . fake()->hexColor(),
        'icon' => null,
        'sort_order' => fake()->numberBetween(0, 100),
    ];
}
```

## What to test

| Layer | What | How |
|---|---|---|
| `FormRequest` | required fields, formats, unique rules | Unit — `Validator::make()` |
| Controllers | happy path, validation failure redirect, DB side-effects | Feature — HTTP calls |
| Models | custom methods if added | Unit |

Do **not** mock the database in feature tests — use `RefreshDatabase` with the real SQLite in-memory DB.

## Frontend tests (Vitest)

`pnpm run test` runs Vitest over `resources/js/**/*.{test,spec}.{ts,tsx}` in a
`happy-dom` environment (config in `vitest.config.ts`; jest-dom matchers and
per-test `cleanup()` load from `resources/js/test/setup.ts`).

Two layers:

- **Pure logic** (`*.test.ts`, e.g. `lib/goalMath.test.ts`) — import a function,
  assert on outputs. Cheapest and most stable; extend these whenever logic is
  factored into a `lib/` helper.
- **Component** (`*.test.tsx`) — render a component with `@testing-library/react`,
  drive it with `@testing-library/user-event`, assert on what the user sees.
  Reserve for components with **state logic** (forms, dialogs, chat), not purely
  presentational ones. Examples: `Components/Advisor/NewConversation.test.tsx`
  (a chip click sends the question), `TypewriterText.test.tsx` (the title reveal
  doesn't replay on remount), `Data/AssetForm.test.tsx` and
  `Goal/GoalFormDialog.test.tsx` (form logic + submit path),
  `Data/AssetTable.test.tsx` (grouping/totals + freshness badges),
  `Dashboard/PortfolioInsights.test.tsx` (threshold-driven hints),
  `Goal/GoalProgress.test.tsx` (derived progress/deviation values),
  `Advisor/Conversation.test.tsx` (optimistic send, rollback, send guard).
  Pure helpers get a plain `*.test.ts`: `Advisor/types.test.ts`
  (`pickQuestions` LCG), `Advisor/enterAnimation.test.ts` (one-shot flag).

### Testing components that use Inertia's `useForm`

A form component (`AssetForm`, `GoalFormDialog`) drives its fields through
`useForm` from `@inertiajs/react` and submits with `post`/`put`. To test the
*client* logic (mode switching, computed values, validation gates, which verb
is called) without a network, `vi.mock('@inertiajs/react', …)` and back
`useForm` with a real `useState` store plus `vi.fn()` spies for `post`/`put`/
`router.post`. See `AssetForm.test.tsx` for the pattern. Assert on the spy
(`expect(put).toHaveBeenCalledWith('/assets/7', …)`) to distinguish create vs
edit.

### happy-dom limits to design around

- **Radix `Select` can't be driven** — its trigger has `pointer-events: none`
  under happy-dom, so `userEvent.click` on the options throws. Seed the value a
  different way (e.g. pass an `editAsset` with `category_id` already set) rather
  than opening the dropdown.
- **`Intl.NumberFormat` grouping is unreliable** — the container's Node ICU may
  drop the thousands separator (`1234,50` instead of `1.234,50`). Assert on the
  digits without the group separator, or query the element by role/structure
  rather than by its full formatted text.
- **Adjacent JSX text nodes break `getByText`** — a template like
  `{pct}% raggiunto` renders as *two* text nodes, so an exact-string
  `getByText('50.0% raggiunto')` finds nothing. Match on the element's combined
  `textContent` (`document.querySelectorAll('span').some(s => s.textContent === …)`)
  or assert on `document.body.textContent`.

### Other Inertia/network surfaces to mock

- **`axios`** — components that call the server directly (e.g. `Conversation`)
  import `axios`. `vi.mock('axios', …)` with `get`/`post` spies lets you drive
  optimistic-update, rollback, and polling logic. A never-resolving
  `mockReturnValue(new Promise(() => {}))` freezes a request "in flight" to test
  in-flight state (send guards, disabled buttons).
- **`ToastContext`** — imperative toasts go through `useToast()`. Wrap the
  component in `<ToastContext.Provider value={vi.fn()}>` and assert on the spy.
- **`<Head>`** — a component that renders Inertia's `<Head>` (e.g. `GoalProgress`)
  needs `vi.mock('@inertiajs/react', () => ({ Head: () => null }))`.

### Test-fixture footgun

- **`??` swallows an intentional `null` prop.** A render helper that defaults a
  nullable prop with `props.value ?? fallback` turns a deliberate `null` (e.g.
  "no snapshot yet") back into the fallback. Use an `'key' in over` presence
  check instead, so `null` is passed through.

> **pnpm is pinned** to `package.json` `packageManager` (`pnpm@10.18.0`) via
> corepack, so the container's pnpm matches the store that built `node_modules`
> — this avoids the `ERR_PNPM_UNEXPECTED_STORE` (v10 vs v11) footgun. Run
> `corepack enable` once in a fresh container; then plain `pnpm` uses the pin.
