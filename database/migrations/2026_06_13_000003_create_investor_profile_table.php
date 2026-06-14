<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investor_profile', function (Blueprint $table): void {
            $table->id();

            // The context the advisor can't infer from the data: how long the
            // money is invested, how much volatility the user stomachs, what
            // it's for, and an optional target allocation (left null when the
            // user's strategy is still vague — the advisor can help shape it).
            $table->string('horizon')->nullable();       // short | medium | long
            $table->string('risk_tolerance')->nullable(); // low | medium | high
            $table->string('objective')->nullable();      // free text
            $table->string('target_allocation')->nullable(); // free text, optional

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_profile');
    }
};
