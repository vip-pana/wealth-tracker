<?php

declare(strict_types=1);

namespace App\Actions\Snapshots;

use App\Actions\Action;
use App\Models\Snapshot;
use App\Models\SnapshotCategoryValue;
use Illuminate\Support\Facades\DB;

class StoreSnapshot extends Action
{
    public function __construct(
        private readonly ComputeValuesAsOf $computeValuesAsOf,
    ) {}

    public function run(string $date): void
    {
        DB::transaction(function () use ($date) {
            ['byCategory' => $byCat, 'total' => $total] = $this->computeValuesAsOf->run($date);

            $snapshot = Snapshot::updateOrCreate(
                ['date' => $date],
                ['total_value' => $total]
            );

            foreach ($byCat as $catId => $value) {
                SnapshotCategoryValue::updateOrCreate(
                    ['snapshot_id' => $snapshot->id, 'category_id' => $catId],
                    ['value' => $value]
                );
            }

            SnapshotCategoryValue::where('snapshot_id', $snapshot->id)
                ->whereNotIn('category_id', array_keys($byCat))
                ->delete();
        });
    }
}
