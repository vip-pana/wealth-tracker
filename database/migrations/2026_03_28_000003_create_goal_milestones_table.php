<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goal_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goal_id')->constrained('goal')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->float('target_value');
            $table->date('target_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_milestones');
    }
};
