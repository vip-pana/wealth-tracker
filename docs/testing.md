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
  doesn't replay on remount).

> **pnpm is pinned** to `package.json` `packageManager` (`pnpm@10.18.0`) via
> corepack, so the container's pnpm matches the store that built `node_modules`
> — this avoids the `ERR_PNPM_UNEXPECTED_STORE` (v10 vs v11) footgun. Run
> `corepack enable` once in a fresh container; then plain `pnpm` uses the pin.
