<?php

namespace Tests\Unit;

use App\Rules\ScalarAttributes;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ScalarAttributesTest extends TestCase
{
    public function test_scalar_attribute_values_pass(): void
    {
        $validator = Validator::make(
            ['attributes' => ['brand' => 'Apple', 'ram' => 8, 'stylus' => true]],
            ['attributes' => ['array', new ScalarAttributes]],
        );

        $this->assertTrue($validator->passes());
    }

    public function test_nested_attribute_values_fail(): void
    {
        $validator = Validator::make(
            ['attributes' => ['brand' => ['Apple']]],
            ['attributes' => ['array', new ScalarAttributes]],
        );

        $this->assertTrue($validator->fails());
    }
}
