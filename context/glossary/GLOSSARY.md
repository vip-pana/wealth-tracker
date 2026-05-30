<!-- purpose: Business language, entities, and how they relate. Read when a task involves business logic, models, or user-facing concepts. -->
# Glossary

<!-- exodia:section:intro -->
Business language, entities, and how they relate. Read this when a task involves business logic, models, or user-facing concepts. Source of truth: `app/Models/` and `database/migrations/`.

## Entities

<!-- exodia:section:entities -->
- **Asset** (`app/Models/Asset.php`) — one logged holding for a month: `category_id`, `name`, `value`, optional `ticker`/`wallet_address`/`quantity`, `date`, `notes`. Ticker assets price live (`currentValue()` = quantity × price; falls back to stored `value`).
- **Category** (`app/Models/Category.php`) — an asset bucket: `name`, hex `color`, emoji `icon`, `sort_order`, and a `MacroCategory` enum.
- **MacroCategory** (`app/Enums/MacroCategory.php`) — Liquidità, ETF, Cripto, Fondo Pensione; the last is illiquid (`isIlliquid()`).
- **Snapshot** (`app/Models/Snapshot.php`, table `snapshots`) — a dated photo of net worth: `date`, `total_value`.
- **SnapshotCategoryValue** — per-category breakdown of a snapshot.
- **AssetPrice** — last fetched live price for a ticker: `price`, `currency`, `fetched_at`.
- **Goal / GoalCategoryAllocation / GoalMilestone** — one savings goal with per-category (and macro) target allocations and milestones.

## Relationships

<!-- exodia:section:relationships -->
- Category 1:n Asset; Category 1:n SnapshotCategoryValue.
- Snapshot 1:n SnapshotCategoryValue.
- Goal 1:n GoalCategoryAllocation; Goal 1:n GoalMilestone.
- Pension entries are Assets in a `Fondo Pensione` macro category (annual, year-end, illiquid).

## User Journey

<!-- exodia:section:journey -->
1. Log assets for the month (manual value or ticker + quantity).
2. Take a snapshot — freezes current net worth per category.
3. View trends, allocation, forecast on the dashboard.
4. Set a goal with target value/date and allocation targets; track progress.

## Type System

<!-- exodia:section:types -->
Frontend types are hand-written in `resources/js/types/` (`models.ts`, `analytics.ts`, `index.d.ts`) to mirror the Inertia props each controller sends. PHP side relies on model `@property` PHPDoc. Keep both in sync when the schema changes.

## L3 Data

<!-- exodia:section:l3 -->
- `glossary.yaml`: extended terminology and synonyms.
