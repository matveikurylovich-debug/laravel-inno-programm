<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

class LoginRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->exists('email')) {
            $this->merge([
                'email' => User::normalizeEmail((string) $this->input('email')),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): User
    {
        $email = User::normalizeEmail($this->string('email')->toString());
        $password = $this->string('password')->toString();

        $user = User::query()->where('email', $email)->first();

        if (! $user instanceof User || ! Hash::check($password, (string) $user->password)) {
            throw new InvalidCredentialsException;
        }

        return $user;
    }
}
