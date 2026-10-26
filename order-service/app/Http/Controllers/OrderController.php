<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CreateOrderRequest;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use OpenApi\Attributes as OA;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orders,
    ) {}

    #[OA\Get(
        path: '/api/orders',
        summary: 'Список заказов',
        security: [['bearerAuth' => []]],
        tags: ['Orders'],
        responses: [
            new OA\Response(response: 200, description: 'Пагинированный список заказов'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
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

    #[OA\Post(
        path: '/api/orders',
        summary: 'Создать заказ',
        security: [['bearerAuth' => []]],
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(
                name: 'Idempotency-Key',
                in: 'header',
                required: true,
                schema: new OA\Schema(type: 'string'),
                description: 'Ключ идемпотентности'
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['store_id', 'location_id', 'items'],
                properties: [
                    new OA\Property(property: 'store_id', type: 'integer'),
                    new OA\Property(property: 'location_id', type: 'integer'),
                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        items: new OA\Items(
                            required: ['product_id', 'quantity'],
                            properties: [
                                new OA\Property(property: 'product_id', type: 'integer'),
                                new OA\Property(property: 'quantity', type: 'integer', minimum: 1),
                            ],
                            type: 'object'
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Заказ создан'),
            new OA\Response(response: 422, description: 'Ошибка валидации / бизнес-логики'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
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
                (int) $request->validated('location_id'),
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

    #[OA\Get(
        path: '/api/orders/{id}',
        summary: 'Детали заказа',
        security: [['bearerAuth' => []]],
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Заказ'),
            new OA\Response(response: 403, description: 'Недостаточно прав'),
            new OA\Response(response: 404, description: 'Не найден'),
        ]
    )]
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

    #[OA\Post(
        path: '/api/orders/{id}/cancel',
        summary: 'Отменить заказ',
        security: [['bearerAuth' => []]],
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Заказ отменён'),
            new OA\Response(response: 400, description: 'Отмена недоступна'),
            new OA\Response(response: 403, description: 'Недостаточно прав'),
            new OA\Response(response: 404, description: 'Не найден'),
        ]
    )]
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

    #[OA\Delete(
        path: '/api/orders/{id}',
        summary: 'Удалить заказ',
        security: [['bearerAuth' => []]],
        tags: ['Orders'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Удалён'),
            new OA\Response(response: 403, description: 'Недостаточно прав'),
            new OA\Response(response: 404, description: 'Не найден'),
        ]
    )]
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
