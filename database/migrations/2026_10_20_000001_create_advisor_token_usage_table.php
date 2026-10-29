<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advisor_token_usage', function (Blueprint $table): void {
            $table->id();

            // nullOnDelete, NOT cascade: deleting a conversation must not erase
            // what it already spent. Cascading here would make DELETE /advisor/
            // {session} a one-click reset of the daily cap — the budget would be
            // trivially bypassable by whoever is burning it.
            $table->foreignId('session_id')->nullable()->constrained('advisor_sessions')->nullOnDelete();

            // The accounting day, stored rather than derived from created_at so
            // the daily sum is a plain indexed equality rather than a range over
            // a timestamp whose timezone could drift.
            $table->date('day');

            $table->unsignedInteger('prompt_tokens');
            $table->unsignedInteger('completion_tokens');

            // Denormalised so the cap query never has to add two columns.
            $table->unsignedInteger('total_tokens');

            $table->timestamps();

            $table->index('day');
            $table->index(['session_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advisor_token_usage');
    }
};
