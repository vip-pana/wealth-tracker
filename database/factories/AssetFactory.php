<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => fake()->word(),
            'value' => fake()->randomFloat(2, 100, 10000),
            'date' => fake()->date('Y-m-d'),
            'notes' => null,
        ];
    }
}
