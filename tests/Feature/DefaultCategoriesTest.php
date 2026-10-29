<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MacroCategory;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DefaultCategoriesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $defaultCategories = true;

    public function test_migrating_a_fresh_install_leaves_it_with_usable_categories(): void
    {
        $categories = Category::query()->orderBy('sort_order')->get();

        $this->assertSame(
            ['Obbligazioni', 'Azioni', 'Bitcoin', 'Oro', 'Liquidità', 'Fondo Pensione'],
            $categories->pluck('name')->all(),
        );
    }

    public function test_every_default_category_is_complete_enough_to_file_an_asset_under(): void
    {
        foreach (Category::query()->get() as $category) {
            $this->assertInstanceOf(MacroCategory::class, $category->macro_category, $category->name);
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $category->color, $category->name);
            $this->assertTrue($category->investable, $category->name);
        }
    }

    public function test_the_seed_skips_an_install_that_already_has_categories(): void
    {
        $migration = require database_path('migrations/2026_10_20_000001_seed_default_categories.php');

        $before = Category::query()->get()->pluck('id')->all();
        $migration->up();

        $this->assertSame($before, Category::query()->get()->pluck('id')->all());
    }

    public function test_deleted_defaults_are_not_resurrected_by_a_later_run(): void
    {
        Category::query()->get()->each->delete();

        $migration = require database_path('migrations/2026_10_20_000001_seed_default_categories.php');
        $migration->up();

        $this->assertSame(0, Category::query()->count());
        $this->assertSame(6, DB::table('categories')->count());
    }

    public function test_a_fresh_install_starts_with_no_goal_of_its_own(): void
    {
        $this->assertSame(0, DB::table('goal')->count());
    }
}
