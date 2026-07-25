<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goal', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->float('target_value');
            $table->date('target_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal');
    }
};
