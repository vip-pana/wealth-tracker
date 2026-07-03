<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When the user last opened this session. Used to flag a session in the
        // sidebar whose latest assistant reply arrived after they last read it —
        // e.g. a chat answer that finished generating while they were elsewhere.
        Schema::table('advisor_sessions', function (Blueprint $table): void {
            $table->timestamp('last_read_at')->nullable()->after('error');
        });
    }

    public function down(): void
    {
        Schema::table('advisor_sessions', function (Blueprint $table): void {
            $table->dropColumn('last_read_at');
        });
    }
};
