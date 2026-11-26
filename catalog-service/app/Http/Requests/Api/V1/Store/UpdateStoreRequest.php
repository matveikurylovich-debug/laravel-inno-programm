<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Store;

use App\Models\Store;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

class UpdateStoreRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $store = $this->storeRecord();

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                Rule::unique('stores', 'slug')->ignore($store->id),
            ],
            'city' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'working_hours' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitized(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        if (array_key_exists('slug', $validated) && (! is_string($validated['slug']) || trim($validated['slug']) === '')) {
            unset($validated['slug']);
        }

        return $validated;
    }

    private function storeRecord(): Store
    {
        $store = $this->route('store');

        if (! $store instanceof Store) {
            throw new LogicException('Route parameter [store] must be a Store instance.');
        }

        return $store;
    }
}
