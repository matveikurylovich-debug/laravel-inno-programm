<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\OrderItem;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/** @mixin OrderItem */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $item = $this->resource;

        if (! $item instanceof OrderItem) {
            throw new LogicException('OrderItemResource requires an OrderItem model.');
        }

        return [
            'id' => $item->id,
            'order_id' => $item->order_id,
            'product_id' => $item->product_id,
            'product_name' => $item->product_name,
            'unit_price' => Money::decimal($item->unit_price),
            'quantity' => $item->quantity,
            'subtotal' => Money::decimal($item->subtotal),
        ];
    }
}
