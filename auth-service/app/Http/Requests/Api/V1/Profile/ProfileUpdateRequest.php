<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Profile;

use App\Models\User;
use App\Rules\PhoneRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
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
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->id)->withoutTrashed(),
            ],
            'phone' => PhoneRules::rules(),
        ];
    }
}
