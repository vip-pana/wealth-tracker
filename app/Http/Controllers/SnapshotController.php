<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\MonthlySnapshot;
use App\Models\SnapshotCategoryValue;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SnapshotController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'month' => 'required|date_format:Y-m-d',
        ]);

        $month = $request->input('month');

        DB::transaction(function () use ($month) {
            // Compute total from assets for that month
            $total = Asset::whereDate('date', $month)->sum('value');

            // Compute per-category sums
            $byCat = Asset::whereDate('date', $month)
                ->selectRaw('category_id, SUM(value) as subtotal')
                ->groupBy('category_id')
                ->get();

            // Upsert snapshot
            $snapshot = MonthlySnapshot::updateOrCreate(
                ['date' => $month],
                ['total_value' => $total]
            );

            // Upsert per-category values
            foreach ($byCat as $row) {
                SnapshotCategoryValue::updateOrCreate(
                    ['snapshot_id' => $snapshot->id, 'category_id' => $row->category_id],
                    ['value' => $row->subtotal]
                );
            }

            // Remove stale category values (categories with no assets this month)
            $activeCatIds = $byCat->pluck('category_id');
            SnapshotCategoryValue::where('snapshot_id', $snapshot->id)
                ->whereNotIn('category_id', $activeCatIds)
                ->delete();
        });

        return redirect()->back()->with('success', 'Snapshot mensile salvato.');
    }
}
