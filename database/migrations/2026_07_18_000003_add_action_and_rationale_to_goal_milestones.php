<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A milestone was only a label + amount + date, so the advisor's "what to
        // do here" ended up crammed into the label or lost to prose. Split it into
        // structured fields the card can show separately: `action` (the concrete
        // step to take at this tappa) and `rationale` (why — the reasoning a
        // consultant would give). `notes` stays the short label ("Metà percorso").
        Schema::table('goal_milestones', function (Blueprint $table): void {
            $table->text('action')->nullable()->after('notes');
            $table->text('rationale')->nullable()->after('action');
        });
    }

    public function down(): void
    {
        Schema::table('goal_milestones', function (Blueprint $table): void {
            $table->dropColumn(['action', 'rationale']);
        });
    }
};
