<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Models\User;
use App\Rules\PhoneRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->exists('email')) {
            $normalized['email'] = User::normalizeEmail((string) $this->input('email'));
        }

        if ($this->exists('phone')) {
            $normalized['phone'] = PhoneRules::toE164($this->input('phone'));
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email:rfc',
                'max:255',
                'regex:/^[^@\s]+@[^@\s]+\.[^@\s]+$/',
                Rule::unique('users', 'email')->withoutTrashed(),
            ],
            'phone' => PhoneRules::rules(),
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.regex' => 'Укажите полный адрес электронной почты, например name@example.com.',
            'email.email' => 'Укажите полный адрес электронной почты, например name@example.com.',
        ];
    }
}
