<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Order;

use App\Data\CreateOrderData;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Каталог и снапшоты хранят store_id и product_id как целые числа.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'store_id' => ['required', 'integer', 'min:1'],
            'location_id' => ['required', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'between:1,1000'],
        ];
    }

    public function toData(): CreateOrderData
    {
        /** @var array{email: string, store_id: int, location_id: int, items: list<array{product_id: int, quantity: int}>} $validated */
        $validated = $this->validated();

        return new CreateOrderData(
            userId: (int) $this->user()?->getAuthIdentifier(),
            email: (string) $validated['email'],
            storeId: (int) $validated['store_id'],
            locationId: (int) $validated['location_id'],
            lines: array_map(
                static fn (array $line): array => [
                    'product_id' => (int) $line['product_id'],
                    'quantity' => (int) $line['quantity'],
                ],
                $validated['items'],
            ),
        );
    }
}
