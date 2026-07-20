<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();

            // Stable id from Enable Banking (entry_reference, falling back to
            // transaction_id), so the daily overlapping re-fetch dedupes instead
            // of duplicating.
            $table->string('external_id')->unique();

            // Signed: negative is an outflow (DBIT), positive an inflow (CRDT).
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);

            $table->date('booking_date');
            $table->date('value_date')->nullable();

            $table->text('description')->nullable();
            $table->string('counterparty')->nullable();

            // Bank-provided category hint, used by later expense categorisation.
            $table->string('merchant_category_code')->nullable();

            // Original Enable Banking payload, kept for debugging and fields not
            // yet promoted to columns.
            $table->json('raw')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['bank_account_id', 'booking_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
    }
};
