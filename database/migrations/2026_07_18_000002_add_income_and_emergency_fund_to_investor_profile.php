<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The goal interview kept re-asking the user's monthly income and whether
        // they hold a separate emergency fund every session, because neither was
        // persisted. They're part of the risk CAPACITY the advisor reasons over,
        // so persist them structured: income as a monthly figure, the buffer as a
        // 3-state (none / partial / separate). Nullable — an unfilled profile is
        // valid and the advisor asks for what's missing.
        Schema::table('investor_profile', function (Blueprint $table): void {
            $table->decimal('income_monthly', 10, 2)->nullable()->after('risk_tolerance');
            $table->string('emergency_fund')->nullable()->after('income_monthly');
        });
    }

    public function down(): void
    {
        Schema::table('investor_profile', function (Blueprint $table): void {
            $table->dropColumn(['income_monthly', 'emergency_fund']);
        });
    }
};
