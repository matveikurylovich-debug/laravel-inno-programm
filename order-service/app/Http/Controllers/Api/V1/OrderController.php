<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Order\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Orders\CancelOrder;
use App\Services\Orders\CreateOrder;
use Ecommerce\PlatformKit\Laravel\JwtUser;
use Ecommerce\PlatformKit\Laravel\PermissionMap;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly CreateOrder $createOrder,
        private readonly CancelOrder $cancelOrder,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Order::class);
        $rawId = $request->user()?->getAuthIdentifier();
        $userId = is_int($rawId) || is_string($rawId) ? (int) $rawId : 0;

        $query = Order::with(['items', 'payment'])->latest();

        if (! $this->hasPermission($request, 'orders.view-any')) {
            $query->where('user_id', $userId);
        }

        $orders = $query->paginate(15);

        return response()->json(
            $orders->through(fn (Order $order): array => new OrderResource($order)->resolve($request)),
        );
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->createOrder->handle($request->toData());

        return new OrderResource($order)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Order $order): JsonResponse
    {
        $this->authorize('view', $order);
        $order->load(['items', 'payment']);

        return new OrderResource($order)
            ->exposeClientSecret()
            ->response();
    }

    public function cancel(Order $order): JsonResponse
    {
        $this->authorize('cancel', $order);

        return new OrderResource($this->cancelOrder->cancel($order))->response();
    }

    private function hasPermission(Request $request, string $permission): bool
    {
        $user = $request->user();

        return $user instanceof JwtUser && PermissionMap::allows($user->roles(), $permission);
    }
}
