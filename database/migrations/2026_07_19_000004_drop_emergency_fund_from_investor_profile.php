<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The qualitative emergency-fund descriptor (none/partial/separate) is
        // superseded by the real buffer: categories flagged non-investable
        // (Category.investable = false) hold the actual emergency cash, which the
        // advisor now reads directly. A self-reported 3-state is redundant and
        // less trustworthy than the tagged assets, so drop it.
        Schema::table('investor_profile', function (Blueprint $table): void {
            $table->dropColumn('emergency_fund');
        });
    }

    public function down(): void
    {
        Schema::table('investor_profile', function (Blueprint $table): void {
            $table->string('emergency_fund')->nullable()->after('income_monthly');
        });
    }
};
