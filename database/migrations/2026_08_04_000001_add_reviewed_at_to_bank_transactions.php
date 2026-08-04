<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            // When the user last looked at this row. Distinct from is_manual,
            // which means "the user overrode the machine, never reclassify":
            // agreeing with the classifier is a review but not an override, and
            // that is the common case. Indexed because the pending count runs on
            // every page load.
            $table->timestamp('reviewed_at')->nullable()->after('is_manual')->index();
        });

        // Everything already in the table has been gone through, so the user
        // starts with an empty review queue rather than months of backlog.
        DB::table('bank_transactions')->update(['reviewed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->dropColumn('reviewed_at');
        });
    }
};
