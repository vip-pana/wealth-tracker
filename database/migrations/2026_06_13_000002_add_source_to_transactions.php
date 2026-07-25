<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            // How the buy/sell came about: 'savings_plan' (a recurring PAC
            // order), 'single' (a one-off order), or 'manual' (entered by hand,
            // not from a broker import). Lets the advisor reason about
            // discipline/consistency, not just the raw amounts. Nullable for
            // rows imported before this column existed.
            $table->string('source')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            $table->dropColumn('source');
        });
    }
};
