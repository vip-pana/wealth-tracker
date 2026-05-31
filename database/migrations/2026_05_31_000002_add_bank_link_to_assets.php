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
            // When a bank account was last synced into this asset's value.
            // The account→asset link itself lives in the bank_accounts table.
            $table->timestamp('bank_synced_at')->nullable()->after('wallet_address');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn('bank_synced_at');
        });
    }
};
