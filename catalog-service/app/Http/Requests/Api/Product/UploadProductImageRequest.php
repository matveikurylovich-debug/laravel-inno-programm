<?php

namespace App\Http\Requests\Api\Product;

use Illuminate\Foundation\Http\FormRequest;

class UploadProductImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'image'      => ['required', 'image', 'mimes:jpeg,png,webp,jpg', 'max:5120'], // До 5 МБ
            'is_primary' => ['nullable', 'boolean'],
        ];
    }
}