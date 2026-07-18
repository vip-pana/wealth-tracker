<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The target allocation is moving from one global set per goal to one set
        // PER MILESTONE (a glide-path: the allocation the user aims for at each
        // tappa). An allocation row now optionally belongs to a milestone; rows
        // with milestone_id = null remain the goal's legacy global allocation
        // during the phased migration. cascadeOnDelete so removing a milestone
        // drops its allocations with it.
        Schema::table('goal_category_allocations', function (Blueprint $table): void {
            $table->foreignId('milestone_id')->nullable()->after('goal_id')
                ->constrained('goal_milestones')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goal_category_allocations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('milestone_id');
        });
    }
};
