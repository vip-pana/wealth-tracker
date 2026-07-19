<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Not every asset the user tracks is meant to be invested: bank accounts
        // held as an emergency fund are parked cash, not part of the investment
        // strategy. Marking a category non-investable keeps its value in total
        // net worth (it's still the user's money) but excludes it from the
        // investment-only metrics — allocation, concentration, volatility,
        // forecast, and the goal's target comparison. Orthogonal to
        // macro_category (an emergency fund is still liquidity, just not to be
        // invested). Defaults to true so every existing category stays investable.
        Schema::table('categories', function (Blueprint $table): void {
            $table->boolean('investable')->default(true)->after('macro_category');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn('investable');
        });
    }
};
