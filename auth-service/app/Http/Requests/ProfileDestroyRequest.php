<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class ProfileDestroyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', $this->matchesUserPassword()],
        ];
    }

    private function matchesUserPassword(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! Hash::check($value, (string) $this->user()?->password)) {
                $fail('Неверный пароль.');
            }
        };
    }
}
