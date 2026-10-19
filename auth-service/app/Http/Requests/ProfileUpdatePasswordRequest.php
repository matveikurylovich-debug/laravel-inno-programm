<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileUpdatePasswordRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', $this->matchesUserPassword('Неверный текущий пароль.')],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];
    }

    private function matchesUserPassword(string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($message): void {
            if (! is_string($value) || ! Hash::check($value, (string) $this->user()?->password)) {
                $fail($message);
            }
        };
    }
}
