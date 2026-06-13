<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'type' => Transaction::TYPE_BUY,
            'shares' => fake()->randomFloat(6, 0.1, 50),
            'price_per_share' => fake()->randomFloat(4, 10, 500),
            'fees' => null,
            'date' => fake()->date('Y-m-d'),
            'external_id' => null,
            'notes' => null,
        ];
    }

    public function sell(): self
    {
        return $this->state(['type' => Transaction::TYPE_SELL]);
    }
}
