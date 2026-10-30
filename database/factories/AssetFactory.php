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
    /** Distinguishes the generated names. */
    private static int $n = 0;

    /**
     * The name is generated, not drawn, for the same reason CategoryFactory's
     * is: an asset name is part of the advisor firewall's instrument
     * vocabulary — heldPattern() reads the name, the ticker and the ISIN — so
     * a lorem draw put a different handful of Latin words into the vocabulary
     * on every run. `Posizione 7` is inert because the filter matches a name
     * whole.
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => 'Posizione '.(++self::$n),
            'value' => fake()->randomFloat(2, 100, 10000),
            'date' => fake()->date('Y-m-d'),
            'notes' => null,
        ];
    }
}
