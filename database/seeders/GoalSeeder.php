<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Goal;
use App\Models\GoalCategoryAllocation;
use App\Models\GoalMilestone;
use Illuminate\Database\Seeder;

class GoalSeeder extends Seeder
{
    public function run(): void
    {
        if (Goal::query()->exists()) {
            return;
        }

        $goal = Goal::create([
            'name' => 'Il primo milione',
            'description' => 'Ogni anno ri-bilianciamento del patrimonio',
            'target_value' => 1_000_000,
            'target_date' => null,
        ]);

        $allocations = [
            ['category_name' => 'Azioni',       'percentage' => 50.0],
            ['category_name' => 'Obbligazioni', 'percentage' => 15.0],
            ['category_name' => 'Oro',          'percentage' => 8.0],
            ['category_name' => 'Bitcoin',      'percentage' => 15.0],
            ['category_name' => 'Liquidità',    'percentage' => 12.0],
        ];

        foreach ($allocations as $alloc) {
            $category = Category::where('name', $alloc['category_name'])->first();
            if ($category === null) {
                continue;
            }
            GoalCategoryAllocation::create([
                'goal_id' => $goal->id,
                'category_id' => $category->id,
                'macro_category' => null,
                'percentage' => $alloc['percentage'],
            ]);
        }

        $milestones = [
            ['notes' => 'PAC invariato, non toccare BTC', 'target_value' => 50_000, 'target_date' => '2027-01-01'],
            ['notes' => 'Alza PAC a 1.000€, valuta uscita parziale BTC se >25% del totale', 'target_value' => 100_000, 'target_date' => '2030-01-01'],
            ['notes' => 'PAC 1.200-1.500€, BTC verso target 15%, primo check pensione integrativa', 'target_value' => 250_000, 'target_date' => '2035-01-01'],
            ['notes' => 'BTC sotto 10% o esci, inizia ribilanciamento verso allocazione difensiva', 'target_value' => 500_000, 'target_date' => '2042-01-01'],
            ['notes' => 'Non prelevare, lascia lavorare al 4% = ~40.000€/anno', 'target_value' => 1_000_000, 'target_date' => '2050-01-01'],
        ];

        foreach ($milestones as $m) {
            GoalMilestone::create([
                'goal_id' => $goal->id,
                'notes' => $m['notes'],
                'target_value' => $m['target_value'],
                'target_date' => $m['target_date'],
            ]);
        }
    }
}
