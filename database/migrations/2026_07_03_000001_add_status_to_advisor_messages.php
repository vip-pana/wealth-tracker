<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A chat reply is now generated in a background job (the local model can
        // take tens of seconds, and a streamed HTTP response froze the whole
        // dev/prod server). The assistant turn is inserted immediately as
        // `pending` and the UI polls until it flips to `done` (or `failed`).
        // Existing rows are complete, so they default to `done`.
        Schema::table('advisor_messages', function (Blueprint $table): void {
            $table->string('status')->default('done')->after('content');
            $table->string('error')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('advisor_messages', function (Blueprint $table): void {
            $table->dropColumn(['status', 'error']);
        });
    }
};
