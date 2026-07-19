<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A milestone allocation is a percentage per category, but a user may
        // want a category to stop tracking its percentage past an absolute
        // amount: "keep liquidity at 15%, but never more than 50k", or "Bitcoin
        // never over 100k". This optional cap lives on the allocation row so any
        // category (not just liquidity) can carry one, and several caps can
        // apply to the same milestone. The amount is in the portfolio's currency
        // (kept currency-agnostic — not named after the euro). Null = no cap.
        Schema::table('goal_category_allocations', function (Blueprint $table): void {
            $table->decimal('cap_amount', 14, 2)->nullable()->after('percentage');
        });
    }

    public function down(): void
    {
        Schema::table('goal_category_allocations', function (Blueprint $table): void {
            $table->dropColumn('cap_amount');
        });
    }
};
