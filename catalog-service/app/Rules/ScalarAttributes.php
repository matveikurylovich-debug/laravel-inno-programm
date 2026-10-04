<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ScalarAttributes implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $item) {
            if (! is_string($key) || $key === '') {
                $fail('Ключ атрибута должен быть непустой строкой.');

                return;
            }

            if (is_array($item) || is_object($item)) {
                $fail('Значение атрибута должно быть строкой, числом или логическим значением.');

                return;
            }
        }
    }
}
