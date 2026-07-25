<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investor_profile', function (Blueprint $table): void {
            // How many months of expenses the emergency fund should cover.
            // Configurable target; 6 is the common prudent default.
            $table->unsignedSmallInteger('emergency_fund_months')->default(6)->after('income_monthly');
        });
    }

    public function down(): void
    {
        Schema::table('investor_profile', function (Blueprint $table): void {
            $table->dropColumn('emergency_fund_months');
        });
    }
};
