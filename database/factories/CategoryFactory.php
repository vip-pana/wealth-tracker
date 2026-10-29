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
    /**
     * Distinguishes the generated names. `fake()->unique()->word()` cannot do
     * it past the 172nd category — the lorem list runs out and the unique
     * generator throws.
     */
    private static int $n = 0;

    /**
     * The name is generated, not drawn.
     *
     * A category name is part of the advisor firewall's instrument vocabulary,
     * because the boundary clauses say a category the user named after an
     * instrument *is* one. `fake()->unique()->word()` drew from a 172-word
     * lorem list holding `non`, `vero`, `sed`, `minus` and `dolore`, so every
     * test that ran the filter ran it against a vocabulary a few random words
     * wide and a different few each time. The live probe is where it showed:
     * it scored 3/14 and then 4/14 on byte-identical cached replies a minute
     * apart, with no code change in between.
     *
     * Two call sites pinned their own fixture once that was found; this is the
     * same repair at the place that makes it hold for every call site, present
     * and future. `Categoria 7` is inert because the filter matches a name
     * whole, so it can only match the literal two-token string.
     */
    public function definition(): array
    {
        return [
            'name' => 'Categoria '.(++self::$n),
            'color' => '#'.strtoupper(fake()->hexColor()),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
