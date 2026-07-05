<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Generative UI: while composing a reply the advisor may call a tool
        // (e.g. simulate_pac) that, besides the text the model reasons over,
        // emits a structured widget the frontend renders as an interactive
        // component. The chosen widgets for the reply are stored here as JSON;
        // the model never sees them, so it cannot break their shape.
        Schema::table('advisor_messages', function (Blueprint $table): void {
            $table->json('widgets')->nullable()->after('tool_activity');
        });
    }

    public function down(): void
    {
        Schema::table('advisor_messages', function (Blueprint $table): void {
            $table->dropColumn('widgets');
        });
    }
};
