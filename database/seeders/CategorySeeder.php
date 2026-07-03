<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MacroCategory;
use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Obbligazioni', 'color' => '#2427ff', 'sort_order' => 0, 'macro_category' => MacroCategory::ETF],
            ['name' => 'Azioni',       'color' => '#0ce708', 'sort_order' => 1, 'macro_category' => MacroCategory::ETF],
            ['name' => 'Bitcoin',      'color' => '#f2da64', 'sort_order' => 2, 'macro_category' => MacroCategory::Cripto],
            ['name' => 'Oro',          'color' => '#ffde05', 'sort_order' => 3, 'macro_category' => MacroCategory::ETF],
            ['name' => 'Liquidità',    'color' => '#6366f1', 'sort_order' => 4, 'macro_category' => MacroCategory::Liquidita],
            ['name' => 'Fondo Pensione', 'color' => '#a855f7', 'sort_order' => 5, 'macro_category' => MacroCategory::FondoPensione],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['name' => $cat['name']], $cat);
        }
    }
}
