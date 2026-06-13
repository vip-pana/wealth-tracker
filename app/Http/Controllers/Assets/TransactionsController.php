<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assets;

use App\Actions\Transactions\ComputePosition;
use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;

class TransactionsController extends Controller
{
    public function __construct(
        private readonly ComputePosition $computePosition,
    ) {}

    /**
     * The transaction history and derived position for one asset, for the
     * view-only transactions dialog. Read-only: transactions are imported, not
     * entered here.
     */
    public function __invoke(Asset $asset): JsonResponse
    {
        $transactions = $asset->transactions()
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $livePrice = $asset->ticker !== null
            ? AssetPrice::where('ticker', $asset->ticker)->value('price')
            : null;

        $position = $this->computePosition->run(
            $transactions,
            is_numeric($livePrice) ? (float) $livePrice : null,
        );

        return response()->json([
            'transactions' => $transactions->map(fn (Transaction $t): array => [
                'id' => $t->id,
                'type' => $t->type,
                'source' => $t->source,
                'shares' => $t->shares,
                'price_per_share' => $t->price_per_share,
                'fees' => $t->fees,
                'date' => $t->date->format('Y-m-d'),
                'notes' => $t->notes,
            ])->all(),
            'position' => $position,
        ]);
    }
}
