<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            // The Cashflow page pages by month, filtering on booking_date alone.
            // The existing composite index leads with bank_account_id, so it
            // can't serve that range scan.
            $table->index('booking_date');
        });
    }

    public function down(): void
    {
        Schema::table('bank_transactions', function (Blueprint $table): void {
            $table->dropIndex(['booking_date']);
        });
    }
};
