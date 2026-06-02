<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('aspsp_name');
            $table->string('aspsp_country', 2);
            $table->string('state')->unique();          // correlates the /auth redirect
            $table->string('session_id')->nullable();   // Enable Banking session, set on callback
            $table->timestamp('valid_until')->nullable();
            $table->string('status')->default('pending'); // pending | active | expired
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_connection_id')->constrained()->cascadeOnDelete();
            $table->string('uid');                       // Enable Banking account uid
            $table->string('iban')->nullable();
            $table->string('name')->nullable();
            $table->string('currency', 3)->nullable();
            // The asset this balance feeds, identified logically (name + category)
            // so the sync follows the asset across monthly rows instead of being
            // pinned to one month's id.
            $table->string('linked_name')->nullable();
            $table->foreignId('linked_category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('bank_connections');
    }
};
