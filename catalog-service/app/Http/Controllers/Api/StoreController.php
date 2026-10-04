<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreController extends Controller
{
    public function index(): JsonResponse
    {
        $stores = Store::query()->where('is_active', true)->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $stores,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
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
        $store->delete();

        return response()->json([
            'success' => true,
            'message' => 'Магазин удален',
        ]);
    }
}
