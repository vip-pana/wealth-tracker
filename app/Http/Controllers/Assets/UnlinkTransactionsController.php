<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assets;

use App\Actions\Transactions\UnlinkAssetTransactions;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;

class UnlinkTransactionsController extends Controller
{
    public function __construct(
        private readonly UnlinkAssetTransactions $unlink,
    ) {}

    public function __invoke(Asset $asset): RedirectResponse
    {
        $this->unlink->run($asset);

        return redirect()->back()->with('success', 'Asset scollegato dalle transazioni. La quantità ora è modificabile a mano.');
    }
}
