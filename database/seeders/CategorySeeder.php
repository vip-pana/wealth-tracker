<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Liquidità',  'color' => '#22c55e', 'icon' => '💰', 'sort_order' => 0],
            ['name' => 'Azioni',     'color' => '#3b82f6', 'icon' => '📈', 'sort_order' => 1],
            ['name' => 'Crypto',     'color' => '#f59e0b', 'icon' => '₿',  'sort_order' => 2],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['name' => $cat['name']], $cat);
        }
    }
}
