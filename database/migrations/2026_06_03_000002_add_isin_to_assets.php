<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            // ISIN of the underlying security, when known. Identifies a holding
            // independently of its display name, so an external sync can match
            // it to the right asset.
            $table->string('isin')->nullable()->after('ticker');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn('isin');
        });
    }
};
