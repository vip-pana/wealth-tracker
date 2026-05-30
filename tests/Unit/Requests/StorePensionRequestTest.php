<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Http\Requests\Pension\StorePensionRequest;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StorePensionRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_passes_with_valid_basic_data(): void
    {
        $category = Category::factory()->create();
        $rules = (new StorePensionRequest)->rules();
        $v = Validator::make([
            'category_id' => $category->id,
            'name' => 'Report 2026',
            'value' => 15000.00,
            'year' => 2026,
        ], $rules);

        $this->assertTrue($v->passes());
    }

    public function test_requires_category_id(): void
    {
        $rules = (new StorePensionRequest)->rules();
        $v = Validator::make([
            'name' => 'X',
            'value' => 100,
            'year' => 2026,
        ], $rules);

        $this->assertTrue($v->errors()->has('category_id'));
    }

    public function test_requires_name(): void
    {
        $rules = (new StorePensionRequest)->rules();
        $v = Validator::make([
            'category_id' => 1,
            'value' => 100,
            'year' => 2026,
        ], $rules);

        $this->assertTrue($v->errors()->has('name'));
    }

    public function test_requires_value(): void
    {
        $rules = (new StorePensionRequest)->rules();
        $v = Validator::make([
            'category_id' => 1,
            'name' => 'X',
            'year' => 2026,
        ], $rules);

        $this->assertTrue($v->errors()->has('value'));
    }

    public function test_requires_year(): void
    {
        $rules = (new StorePensionRequest)->rules();
        $v = Validator::make([
            'category_id' => 1,
            'name' => 'X',
            'value' => 100,
        ], $rules);

        $this->assertTrue($v->errors()->has('year'));
    }

    public function test_rejects_negative_value(): void
    {
        $rules = (new StorePensionRequest)->rules();
        $v = Validator::make([
            'category_id' => 1,
            'name' => 'X',
            'value' => -10,
            'year' => 2026,
        ], $rules);

        $this->assertTrue($v->errors()->has('value'));
    }

    public function test_rejects_year_out_of_range(): void
    {
        $rules = (new StorePensionRequest)->rules();
        $v = Validator::make([
            'category_id' => 1,
            'name' => 'X',
            'value' => 100,
            'year' => 1900,
        ], $rules);

        $this->assertTrue($v->errors()->has('year'));
    }
}
