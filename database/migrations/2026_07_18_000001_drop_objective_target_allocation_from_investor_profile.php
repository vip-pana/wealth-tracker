<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The Goal section is now the single source of the objective and target
        // allocation (name/target_value/target_date + GoalCategoryAllocation).
        // These profile fields duplicated that data as a free-text override,
        // which shadowed the structured Goal figures in the advisor context and
        // made the goal interview re-ask for a target the user had already set.
        // The profile keeps only horizon/risk_tolerance/notes (the psychological
        // side the data can't reveal).
        Schema::table('investor_profile', function (Blueprint $table): void {
            $table->dropColumn(['objective', 'target_allocation']);
        });
    }

    public function down(): void
    {
        Schema::table('investor_profile', function (Blueprint $table): void {
            $table->string('objective')->nullable();
            $table->string('target_allocation')->nullable();
        });
    }
};
