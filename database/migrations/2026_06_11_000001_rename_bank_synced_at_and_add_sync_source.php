<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A broker (Scalable), not only a bank, stamps this timestamp — rename it
        // to the honest, source-agnostic synced_at.
        Schema::table('assets', function (Blueprint $table): void {
            $table->renameColumn('bank_synced_at', 'synced_at');
        });

        // Which sync wrote the value: 'bank' | 'broker'. NULL when never synced.
        // Replaces the old "synced but not bank_linked = broker" exclusion guess.
        Schema::table('assets', function (Blueprint $table): void {
            $table->string('sync_source')->nullable()->after('synced_at');
        });

        // Backfill: classify already-synced rows the same way the app derives
        // bank-vs-broker today — 'bank' if an active, non-expired bank connection
        // links its (name, category_id), else 'broker'. Inlined (not
        // BankAccount::activeLinkKeys()) so it stays correct independent of app
        // code. 'active' mirrors BankConnection::STATUS_ACTIVE.
        $activeLinkKeys = DB::table('bank_accounts')
            ->join('bank_connections', 'bank_connections.id', '=', 'bank_accounts.bank_connection_id')
            ->whereNotNull('bank_accounts.linked_name')
            ->whereNotNull('bank_accounts.linked_category_id')
            ->where('bank_connections.status', 'active')
            ->where(function ($q): void {
                $q->whereNull('bank_connections.valid_until')
                    ->orWhere('bank_connections.valid_until', '>', Carbon::now());
            })
            ->get(['bank_accounts.linked_name', 'bank_accounts.linked_category_id'])
            ->map(fn (object $r): string => $r->linked_name.'|'.$r->linked_category_id)
            ->all();

        DB::table('assets')
            ->whereNotNull('synced_at')
            ->orderBy('id')
            ->each(function (object $row) use ($activeLinkKeys): void {
                $isBank = in_array($row->name.'|'.$row->category_id, $activeLinkKeys, true);
                DB::table('assets')
                    ->where('id', $row->id)
                    ->update(['sync_source' => $isBank ? 'bank' : 'broker']);
            });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table): void {
            $table->dropColumn('sync_source');
        });

        Schema::table('assets', function (Blueprint $table): void {
            $table->renameColumn('synced_at', 'bank_synced_at');
        });
    }
};
