<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_prices', function (Blueprint $table): void {
            $table->string('last_status')->nullable()->after('fetched_at');
            $table->timestamp('last_attempt_at')->nullable()->after('last_status');
            $table->string('last_error')->nullable()->after('last_attempt_at');
        });

        // A row can now exist for a ticker that has never fetched successfully
        // (status=failed, no price yet), so price and its timestamp must allow null.
        Schema::table('asset_prices', function (Blueprint $table): void {
            $table->decimal('price', 15, 8)->nullable()->change();
            $table->timestamp('fetched_at')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('asset_prices', function (Blueprint $table): void {
            $table->dropColumn(['last_status', 'last_attempt_at', 'last_error']);
        });
    }
};
