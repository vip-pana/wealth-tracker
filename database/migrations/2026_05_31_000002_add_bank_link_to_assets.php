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
            $table->string('bank_account_uid')->nullable()->after('wallet_address');
            $table->timestamp('bank_synced_at')->nullable()->after('bank_account_uid');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn(['bank_account_uid', 'bank_synced_at']);
        });
    }
};
