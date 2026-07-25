<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            // income | expense | transfer; null until classified. The amount
            // sign alone can't tell an internal transfer from a real flow.
            $table->string('flow_type')->nullable()->after('counterparty');

            // User's "don't count this" for one-off, out-of-the-ordinary items.
            // A separate axis from flow_type: an expense stays an expense but is
            // kept out of the stats. A transfer is excluded by nature already.
            $table->boolean('excluded')->default(false)->after('flow_type');

            // Once the user classifies a row, the auto-classifier leaves it
            // alone on the daily re-import: manual always wins.
            $table->boolean('is_manual')->default(false)->after('excluded');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->dropColumn(['flow_type', 'excluded', 'is_manual']);
        });
    }
};
