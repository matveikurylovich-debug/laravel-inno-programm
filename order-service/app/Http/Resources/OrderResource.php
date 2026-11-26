<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\Role;
use App\Models\Order;
use App\Support\Money;
use Ecommerce\PlatformKit\Laravel\JwtUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

/** @mixin Order */
class OrderResource extends JsonResource
{
    private bool $exposeClientSecret = false;

    public function exposeClientSecret(bool $expose = true): self
    {
        $this->exposeClientSecret = $expose;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->resource;

        if (! $order instanceof Order) {
            throw new LogicException('OrderResource requires an Order model.');
        }

        return [
            'id' => $order->id,
            'user_id' => $order->user_id,
            'email' => $this->when($this->exposesBuyer($request, $order), $order->email),
            'store_id' => $order->store_id,
            'location_id' => $order->location_id,
            'status' => $order->status->value,
            'payment_status' => $order->payment_status->value,
            'total_amount' => Money::decimal($order->total_amount),
            'currency' => $order->currency,
            'cancel_reason' => $order->cancel_reason,
            'created_at' => $order->created_at?->toJSON(),
            'updated_at' => $order->updated_at?->toJSON(),
            'items' => $this->when(
                $order->relationLoaded('items'),
                fn () => OrderItemResource::collection($order->items)->resolve($request),
            ),
            'payment' => $this->when(
                $order->relationLoaded('payment'),
                function () use ($request, $order): ?array {
                    $payment = $this->paymentResource($request, $order);

                    return $payment?->resolve($request);
                },
            ),
        ];
    }

    private function exposesBuyer(Request $request, Order $order): bool
    {
        $user = $request->user();
        if (! $user instanceof JwtUser) {
            return true;
        }

        $analystOnly = $user->hasRole(Role::Analyst->value) && ! $user->hasRole(Role::Admin->value);

        return ! $analystOnly || $this->isOwner($request, $order);
    }

    private function isOwner(Request $request, Order $order): bool
    {
        return (string) $order->user_id === (string) $request->user()?->getAuthIdentifier();
    }

    private function paymentResource(Request $request, Order $order): ?PaymentResource
    {
        if ($order->payment === null) {
            return null;
        }

        return new PaymentResource($order->payment)
            ->exposeClientSecret($this->exposeClientSecret && $this->isOwner($request, $order));
    }
}
