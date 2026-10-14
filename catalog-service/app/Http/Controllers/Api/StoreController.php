<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Support\CurrentStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    public function __construct(private readonly CurrentStore $currentStore) {}

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

    public function update(Request $request, Store $store): JsonResponse
    {
        if ($denied = $this->denyForeignStore($store)) {
            return $denied;
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:stores,slug,'.$store->id],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $store->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Магазин обновлен',
            'data' => $store,
        ]);
    }

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
