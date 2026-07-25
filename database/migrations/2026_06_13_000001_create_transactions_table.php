<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();

            // 'buy' | 'sell'. A buy adds shares at price_per_share; a sell
            // removes them. Average cost basis is derived from the buy history.
            $table->string('type');

            // Shares and price carry high precision: brokers fill PAC orders in
            // fractional shares (e.g. 3.069249) and crypto needs many decimals.
            $table->decimal('shares', 20, 8);
            $table->decimal('price_per_share', 20, 8);

            // Order fees in account currency, when known. Nullable: Scalable PAC
            // orders are commission-free, so most rows have none.
            $table->decimal('fees', 15, 2)->nullable();

            $table->date('date');

            // Stable id from an external source (e.g. the Scalable CLI), so a
            // re-import can dedupe instead of duplicating. NULL for manual rows.
            $table->string('external_id')->nullable()->unique();

            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['asset_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
