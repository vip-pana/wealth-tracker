<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Free-text synthesis of the risk-profiling interview: the "why" behind
        // the structured horizon/risk_tolerance (risk capacity vs emotional
        // tolerance, reaction to drawdowns, income stability). Kept so the
        // advisor re-reads its own reasoning across sessions instead of it
        // living only in one chat's history.
        Schema::table('investor_profile', function (Blueprint $table): void {
            $table->text('notes')->nullable()->after('target_allocation');
        });
    }

    public function down(): void
    {
        Schema::table('investor_profile', function (Blueprint $table): void {
            $table->dropColumn('notes');
        });
    }
};
