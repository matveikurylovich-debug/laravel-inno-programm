<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Support\CurrentStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class StoreController extends Controller
{
    public function __construct(private readonly CurrentStore $currentStore) {}

    #[OA\Get(
        path: '/api/v1/stores',
        summary: 'Список магазинов',
        tags: ['Stores'],
        responses: [
            new OA\Response(response: 200, description: 'Список магазинов'),
        ]
    )]
    public function index(): JsonResponse
    {
        $query = Store::query()->orderBy('name');

        if ($this->currentStore->id() !== null) {
            $query->whereKey($this->currentStore->id());
        } else {
            $query->where('is_active', true);
        }

        $stores = $query->get();

        return response()->json([
            'success' => true,
            'data' => $stores,
        ]);
    }

    #[OA\Post(
        path: '/api/v1/stores',
        summary: 'Создать магазин',
        security: [['bearerAuth' => []]],
        tags: ['Stores'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string', nullable: true),
                    new OA\Property(property: 'city', type: 'string', nullable: true),
                    new OA\Property(property: 'address', type: 'string', nullable: true),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'working_hours', type: 'string', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Магазин создан'),
            new OA\Response(response: 403, description: 'Нет доступа'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        if ($this->currentStore->id() !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Нет доступа к созданию другого магазина',
            ], 403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:stores,slug'],
            'city' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'working_hours' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $data['is_active'] = $data['is_active'] ?? true;

        $store = Store::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Магазин создан',
            'data' => $store,
        ], 201);
    }

    #[OA\Put(
        path: '/api/v1/stores/{store}',
        summary: 'Обновить магазин',
        security: [['bearerAuth' => []]],
        tags: ['Stores'],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string', nullable: true),
                    new OA\Property(property: 'city', type: 'string'),
                    new OA\Property(property: 'address', type: 'string'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true),
                    new OA\Property(property: 'working_hours', type: 'string'),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Магазин обновлён'),
            new OA\Response(response: 403, description: 'Нет доступа'),
        ]
    )]
    #[OA\Patch(
        path: '/api/v1/stores/{store}',
        summary: 'Частично обновить магазин',
        security: [['bearerAuth' => []]],
        tags: ['Stores'],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Магазин обновлён'),
        ]
    )]
    public function update(Request $request, Store $store): JsonResponse
    {
        if ($denied = $this->denyForeignStore($store)) {
            return $denied;
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:stores,slug,'.$store->id],
            'city' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'working_hours' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $store->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Магазин обновлен',
            'data' => $store,
        ]);
    }

    #[OA\Delete(
        path: '/api/v1/stores/{store}',
        summary: 'Удалить магазин',
        security: [['bearerAuth' => []]],
        tags: ['Stores'],
        parameters: [
            new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Магазин удалён'),
            new OA\Response(response: 403, description: 'Нет доступа'),
        ]
    )]
    public function destroy(Store $store): JsonResponse
    {
        if ($denied = $this->denyForeignStore($store)) {
            return $denied;
        }

        $store->delete();

        return response()->json([
            'success' => true,
            'message' => 'Магазин удален',
        ]);
    }

    private function denyForeignStore(Store $store): ?JsonResponse
    {
        $storeId = $this->currentStore->id();

        if ($storeId !== null && (int) $store->id !== $storeId) {
            return response()->json([
                'success' => false,
                'message' => 'Нет доступа к этому магазину',
            ], 403);
        }

        return null;
    }
}
