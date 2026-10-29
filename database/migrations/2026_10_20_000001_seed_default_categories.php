<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A fresh install has no categories, and an asset cannot be created without
     * one — so the app is unusable from the moment it starts until the owner
     * finds the category screen. The seeder that holds this list is never run
     * on a real install: nothing in the Docker entrypoint or the README calls
     * `db:seed`, only `migrate`. So the starting set is created here, where it
     * is guaranteed to run exactly once on every install.
     *
     * Guarded on the table being empty rather than on each name, and recorded
     * in the migrations table, so an owner who renames or deletes these does
     * not find them resurrected on the next start. Soft-deleted rows count:
     * deleting all six is a deliberate choice, not an empty table.
     *
     * Written with the query builder rather than the Category model on purpose
     * — a migration has to keep running years after the model it was written
     * against has moved on.
     */
    public function up(): void
    {
        if (DB::table('categories')->count() > 0) {
            return;
        }

        $now = now();

        DB::table('categories')->insert(array_map(
            fn (array $category): array => $category + ['created_at' => $now, 'updated_at' => $now],
            [
                ['name' => 'Obbligazioni', 'color' => '#2427ff', 'sort_order' => 0, 'macro_category' => 'ETF'],
                ['name' => 'Azioni', 'color' => '#0ce708', 'sort_order' => 1, 'macro_category' => 'ETF'],
                ['name' => 'Bitcoin', 'color' => '#f2da64', 'sort_order' => 2, 'macro_category' => 'Cripto'],
                ['name' => 'Oro', 'color' => '#ffde05', 'sort_order' => 3, 'macro_category' => 'ETF'],
                ['name' => 'Liquidità', 'color' => '#6366f1', 'sort_order' => 4, 'macro_category' => 'Liquidità'],
                ['name' => 'Fondo Pensione', 'color' => '#a855f7', 'sort_order' => 5, 'macro_category' => 'Fondo Pensione'],
            ],
        ));
    }

    /**
     * Deliberately empty. The rows are indistinguishable from ones the owner
     * created or edited by the time anyone rolls back, and deleting a category
     * cascades into assets and snapshot values.
     */
    public function down(): void {}
};
