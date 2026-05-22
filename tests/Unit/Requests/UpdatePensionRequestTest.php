<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Http\Requests\Pension\UpdatePensionRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UpdatePensionRequestTest extends TestCase
{
    public function test_passes_with_empty_payload(): void
    {
        $rules = (new UpdatePensionRequest)->rules();
        $v = Validator::make([], $rules);

        $this->assertTrue($v->passes());
    }

    public function test_passes_with_partial_update(): void
    {
        $rules = (new UpdatePensionRequest)->rules();
        $v = Validator::make([
            'value' => 20000,
        ], $rules);

        $this->assertTrue($v->passes());
    }

    public function test_rejects_negative_value(): void
    {
        $rules = (new UpdatePensionRequest)->rules();
        $v = Validator::make([
            'value' => -100,
        ], $rules);

        $this->assertTrue($v->errors()->has('value'));
    }

    public function test_rejects_year_out_of_range(): void
    {
        $rules = (new UpdatePensionRequest)->rules();
        $v = Validator::make([
            'year' => 3000,
        ], $rules);

        $this->assertTrue($v->errors()->has('year'));
    }
}
