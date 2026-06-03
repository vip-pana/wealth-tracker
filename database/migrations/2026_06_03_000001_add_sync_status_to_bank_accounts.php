<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table): void {
            // Persist the outcome of the last balance sync, mirroring asset_prices'
            // last_status/last_error so a failed bank sync stays visible after the
            // one-time toast is gone. ok | failed | null (never synced).
            $table->string('last_sync_status')->nullable();
            $table->string('last_sync_error')->nullable();
            $table->timestamp('last_sync_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table): void {
            $table->dropColumn(['last_sync_status', 'last_sync_error', 'last_sync_at']);
        });
    }
};
