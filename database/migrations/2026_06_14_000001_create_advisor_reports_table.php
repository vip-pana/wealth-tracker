<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advisor_reports', function (Blueprint $table): void {
            $table->id();

            // Lifecycle of an on-demand, background-generated analysis:
            // 'pending' while the queued job runs, 'done' with content, or
            // 'failed' with an error. Kept single-row for now (the latest
            // analysis); history will come with chat sessions.
            $table->string('status');         // pending | done | failed
            $table->longText('content')->nullable();
            $table->string('error')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advisor_reports');
    }
};
