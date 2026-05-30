<?php

declare(strict_types=1);

namespace App\Http\Controllers\Snapshots;

use App\Actions\Snapshots\StoreSnapshot;
use App\Http\Controllers\Controller;
use App\Http\Requests\Snapshots\StoreSnapshotRequest;
use App\Jobs\BackupDatabase;
use Illuminate\Http\RedirectResponse;

class StoreController extends Controller
{
    public function __construct(
        private readonly StoreSnapshot $storeSnapshot,
    ) {}

    public function __invoke(StoreSnapshotRequest $request): RedirectResponse
    {
        $this->storeSnapshot->run($request->snapshotDate());

        BackupDatabase::dispatch();

        return redirect()->back()->with('success', 'Snapshot salvato.');
    }
}
