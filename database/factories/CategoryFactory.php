<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'color' => '#'.strtoupper(fake()->hexColor()),
            'icon' => null,
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
