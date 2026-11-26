<?php

declare(strict_types=1);

use App\Rules\ScalarAttributes;
use Illuminate\Support\Facades\Validator;

it('accepts scalar attribute values', function (): void {
    $validator = Validator::make(
        ['attributes' => ['brand' => 'Apple', 'ram' => 8, 'stylus' => true]],
        ['attributes' => ['array', new ScalarAttributes]],
    );

    expect($validator->passes())->toBeTrue();
});

it('rejects nested array attribute values', function (): void {
    $validator = Validator::make(
        ['attributes' => ['brand' => ['Apple']]],
        ['attributes' => ['array', new ScalarAttributes]],
    );

    expect($validator->fails())->toBeTrue();
});
