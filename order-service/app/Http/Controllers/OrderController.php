<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $userRole = (string) $request->attributes->get('auth_role', 'customer');
        $userId = (int) $request->attributes->get('auth_user_id');

        $query = Order::with(['items', 'payment'])->latest();

        if (! in_array($userRole, ['admin', 'analyst'], true)) {
            $query->where('user_id', $userId);
        }

        $orders = $query->paginate(15);

        return response()->json($orders);
    }

    public function store(CreateOrderRequest $request): JsonResponse
    {
        $email = $request->attributes->get('auth_email');

        if (! is_string($email) || $email === '') {
            return response()->json([
                'error' => 'Unprocessable',
                'details' => 'В токене нет email покупателя',
            ], 422);
        }

        try {
            $order = $this->orders->createOrder(
                (int) $request->attributes->get('auth_user_id'),
                (int) $request->validated('store_id'),
                $email,
                $request->validated('items'),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Unprocessable',
                'details' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $order,
        ], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $order = Order::query()->with(['items', 'payment'])->find($id);

        if ($order === null) {
            return response()->json([
                'error' => 'Not Found',
                'details' => 'Заказ не найден',
            ], 404);
        }

        $isOwner = (string) $order->user_id === (string) $request->attributes->get('auth_user_id');
        $isAdmin = in_array($request->attributes->get('auth_role'), ['admin', 'analyst'], true);

        if (! $isOwner && ! $isAdmin) {
            return response()->json([
                'error' => 'Forbidden',
                'details' => 'Недостаточно прав для просмотра заказа',
            ], 403);
        }

        return response()->json([
            'data' => $order,
        ]);
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $order = Order::query()->find($id);

        if ($order === null) {
            return response()->json([
                'error' => 'Not Found',
                'details' => 'Заказ не найден',
            ], 404);
        }

        if (! in_array($order->status, ['created', 'stock_reserved', 'payment_pending'], true)) {
            return response()->json([
                'error' => 'Bad Request',
                'details' => 'Отмена заказа разрешена только до момента shipped',
            ], 400);
        }

        $isOwner = (string) $order->user_id === (string) $request->attributes->get('auth_user_id');
        $isAdmin = $request->attributes->get('auth_role') === 'admin';

        if (! $isOwner && ! $isAdmin) {
            return response()->json([
                'error' => 'Forbidden',
                'details' => 'Недостаточно прав для отмены заказа',
            ], 403);
        }

        $order = $this->orders->cancelOrder($order);

        return response()->json([
            'data' => $order,
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $order = Order::query()->find($id);

        if ($order === null) {
            return response()->json([
                'error' => 'Not Found',
                'details' => 'Заказ не найден',
            ], 404);
        }

        $isOwner = (string) $order->user_id === (string) $request->attributes->get('auth_user_id');
        $isAdmin = $request->attributes->get('auth_role') === 'admin';

        if (! $isOwner && ! $isAdmin) {
            return response()->json([
                'error' => 'Forbidden',
                'details' => 'Недостаточно прав для удаления заказа',
            ], 403);
        }

        $this->orders->deleteOrder($order);

        return response()->json(null, 204);
    }
}
