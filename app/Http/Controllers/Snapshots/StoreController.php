<?php

declare(strict_types=1);

namespace App\Http\Controllers\Snapshots;

use App\Actions\Prices\FetchAllPrices;
use App\Actions\Snapshots\StoreSnapshot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Snapshots\StoreSnapshotRequest;
use App\Jobs\BackupDatabase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class StoreController extends Controller
{
    public function __construct(
        private readonly StoreSnapshot $storeSnapshot,
        private readonly FetchAllPrices $fetchAllPrices,
    ) {}

    public function __invoke(StoreSnapshotRequest $request): RedirectResponse
    {
        $date = $request->snapshotDate();

        // Snapshotting today: refresh live values (bank balances, wallets, ticker
        // prices) first so the permanent record captures the current figures, not
        // whatever the last refresh left behind. Bank balances are "now", so this
        // only applies to a today-dated snapshot, never a backdated one.
        if ($date === Carbon::now()->toDateString()) {
            $this->fetchAllPrices->run();
        }

        $this->storeSnapshot->run($date);

        BackupDatabase::dispatch();

        return redirect()->back()->with('success', 'Snapshot salvato.');
    }
}
