<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A persisted, in-app notification feed. Replaces the scattered,
        // page-local toasts: server-side events (a queued report finishing, a
        // broker/bank connection lost during the nightly sync) accumulate here
        // until read. The panel shows only unread rows, so read_at doubles as
        // the dismiss flag — and resolving a condition (e.g. reconnecting a
        // broker) just sets read_at on the matching unread row.
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();

            $table->string('type');           // machine type, e.g. advisor_report_ready
            $table->string('level');          // info | success | warning
            $table->string('title');
            $table->string('body')->nullable();
            $table->string('action_url')->nullable(); // where the bell click takes you

            // For recurring state conditions (sync failed, consent expired) the
            // producer reuses one row instead of piling up duplicates: it keys
            // on dedupe_key and skips if an unread one already exists. One-shot
            // events (report ready) leave this null. Indexed for the lookup.
            $table->string('dedupe_key')->nullable()->index();

            $table->timestamp('read_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
