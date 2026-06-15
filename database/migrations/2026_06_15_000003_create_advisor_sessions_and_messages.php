<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A session is a conversation with the AI advisor. Its `kind` records
        // why it started (a periodic report, a free chat, goal planning…) but
        // the structure is identical: a thread of messages. The periodic report
        // is simply the first assistant message of a kind=report session — there
        // is no separate "report" concept any more.
        Schema::create('advisor_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('kind');                 // report | chat | goal_planning | ...
            $table->string('title')->nullable();    // human label, e.g. "Analisi 15 giu 2026"
            $table->string('status');               // pending | done | failed (for the opening generation)
            $table->string('error')->nullable();
            $table->timestamps();
        });

        // One turn in a session. The opening report and every chat reply are
        // assistant messages; the user's questions are user messages.
        Schema::create('advisor_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('advisor_sessions')->cascadeOnDelete();
            $table->string('role');                 // assistant | user
            $table->longText('content');
            $table->timestamps();
        });

        // The old single-row report table is superseded by sessions; a report
        // is now a session. Nothing of value persisted there (it overwrote each
        // run), so drop it rather than migrate.
        Schema::dropIfExists('advisor_reports');
    }

    public function down(): void
    {
        Schema::dropIfExists('advisor_messages');
        Schema::dropIfExists('advisor_sessions');

        Schema::create('advisor_reports', function (Blueprint $table): void {
            $table->id();
            $table->string('status');
            $table->longText('content')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
        });
    }
};
