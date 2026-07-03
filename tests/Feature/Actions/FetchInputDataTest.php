<?php

declare(strict_types=1);

namespace Tests\Feature\Actions;

use App\Actions\Input\FetchInputData;
use App\Models\Asset;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FetchInputDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_previous_values_map_the_prior_month_by_category_and_name(): void
    {
        $category = Category::factory()->create();
        Asset::factory()->create(['category_id' => $category->id, 'name' => 'ETF Mondo', 'value' => 1000, 'date' => '2026-05-01']);
        // Current month: the value being edited — must not leak into previousValues.
        Asset::factory()->create(['category_id' => $category->id, 'name' => 'ETF Mondo', 'value' => 1200, 'date' => '2026-06-01']);

        $data = app(FetchInputData::class)->run('2026-06-01');

        $this->assertSame(1000.0, $data['previousValues'][$category->id.'|ETF Mondo']);
    }

    public function test_previous_values_is_empty_for_the_earliest_month(): void
    {
        $category = Category::factory()->create();
        Asset::factory()->create(['category_id' => $category->id, 'name' => 'ETF Mondo', 'value' => 1000, 'date' => '2026-06-01']);

        $data = app(FetchInputData::class)->run('2026-06-01');

        $this->assertSame([], $data['previousValues']);
    }
}
