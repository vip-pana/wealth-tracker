<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Net monthly income is now observed from bank transactions
        // (ComputeMonthlySalary), so the hand-entered figure is dropped — a
        // manual value only went stale against the real salary.
        Schema::table('investor_profile', function (Blueprint $table): void {
            $table->dropColumn('income_monthly');
        });
    }

    public function down(): void
    {
        Schema::table('investor_profile', function (Blueprint $table): void {
            $table->decimal('income_monthly', 15, 2)->nullable()->after('risk_tolerance');
        });
    }
};
