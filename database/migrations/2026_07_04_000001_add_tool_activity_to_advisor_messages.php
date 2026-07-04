<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // While a reply is generated in the background, the advisor may call
        // tools to pull fresh data. The job writes a human-readable label of the
        // tool currently running here (e.g. "Sto controllando la tua posizione
        // Bitcoin…") so the polling UI can show what it's doing instead of a
        // blank wait. Cleared once the reply is done.
        Schema::table('advisor_messages', function (Blueprint $table): void {
            $table->string('tool_activity')->nullable()->after('error');
        });
    }

    public function down(): void
    {
        Schema::table('advisor_messages', function (Blueprint $table): void {
            $table->dropColumn('tool_activity');
        });
    }
};
