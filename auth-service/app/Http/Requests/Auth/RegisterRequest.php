<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email:rfc', 'regex:/^[^@\s]+@[^@\s]+\.[^@\s]+$/', 'max:255', 'unique:users'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-\(\)]+$/'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.email' => 'Укажите полный адрес электронной почты, например name@example.com.',
            'email.regex' => 'Укажите полный адрес электронной почты, например name@example.com.',
        ];
    }
}
