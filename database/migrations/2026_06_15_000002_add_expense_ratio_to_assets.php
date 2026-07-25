<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            // Annual management cost (TER) as a percentage, e.g. 0.20 = 0.20%/yr.
            // Per-instrument, optional, edited by hand. Kept structured (not in
            // free-text notes) so the advisor can weight it by position value
            // and reason about the yearly cost drag on net return.
            $table->decimal('expense_ratio', 6, 4)->nullable()->after('isin');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn('expense_ratio');
        });
    }
};
