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

            $snapshot = Snapshot::withTrashed()->firstOrNew(['date' => $date]);
            $snapshot->total_value = $total;
            $snapshot->deleted_at = null;
            $snapshot->save();

            foreach ($byCat as $catId => $value) {
                $categoryValue = SnapshotCategoryValue::withTrashed()
                    ->firstOrNew(['snapshot_id' => $snapshot->id, 'category_id' => $catId]);
                $categoryValue->value = $value;
                $categoryValue->deleted_at = null;
                $categoryValue->save();
            }

            SnapshotCategoryValue::where('snapshot_id', $snapshot->id)
                ->whereNotIn('category_id', array_keys($byCat))
                ->delete();
        });
    }
}
