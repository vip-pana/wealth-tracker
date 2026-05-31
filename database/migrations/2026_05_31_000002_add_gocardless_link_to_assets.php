<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->string('gocardless_account_id')->nullable()->after('wallet_address');
            $table->timestamp('gocardless_synced_at')->nullable()->after('gocardless_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn(['gocardless_account_id', 'gocardless_synced_at']);
        });
    }
};
