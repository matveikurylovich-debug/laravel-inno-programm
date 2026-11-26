<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Profile;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class ProfileDestroyRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
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
                $fail(__('auth.password'));
            }
        };
    }
}
