<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Single-row global state for the Scalable broker sync. Unlike bank
        // accounts, the connection health (CLI session valid) is global, not
        // per-asset — so a failed sync stays visible in Settings after the
        // one-time refresh toast is gone. ok | failed | null.
        Schema::create('scalable_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('last_sync_status')->nullable();
            $table->string('last_sync_error')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scalable_connections');
    }
};
