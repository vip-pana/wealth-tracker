<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Personal context the advisor can't infer from the portfolio: the user's
        // name (so it can address them and write personally), their birth date
        // (age anchors horizon and how aggressive the glide-path should be), and a
        // free-text `memory` — durable facts/preferences the advisor records about
        // the user across sessions ("preferisce ETF ad accumulo", "PAC dal 2024").
        // This is distinct from `notes`, which is the risk-profiling synthesis.
        // All nullable — an unfilled profile stays valid.
        Schema::table('investor_profile', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('id');
            $table->date('birth_date')->nullable()->after('name');
            $table->text('memory')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('investor_profile', function (Blueprint $table): void {
            $table->dropColumn(['name', 'birth_date', 'memory']);
        });
    }
};
