<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Store\StoreStoreRequest;
use App\Http\Requests\Api\V1\Store\UpdateStoreRequest;
use App\Http\Resources\StoreResource;
use App\Models\StockReservation;
use App\Models\Store;
use App\Models\StoreLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StoreController extends Controller
{
    public function index(): JsonResponse
    {
        $stores = Store::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return StoreResource::collection($stores)
            ->additional(['success' => true])
            ->response();
    }

    public function store(StoreStoreRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $data */
        $data = $request->validated();
        $data['is_active'] ??= true;

        $store = DB::transaction(static function () use ($data): Store {
            /** @var Store $store */
            $store = Store::query()->create($data);
            StoreLocation::query()->create([
                'store_id' => $store->id,
                'name' => $store->name,
                'city' => $data['city'] ?? 'Минск',
                'address' => $data['address'] ?? 'Не указан',
                'phone' => $data['phone'] ?? null,
                'working_hours' => $data['working_hours'] ?? '10:00 - 22:00',
                'is_active' => true,
            ]);

            return $store;
        });

        return new StoreResource($store)
            ->additional([
                'success' => true,
                'message' => 'Магазин создан',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateStoreRequest $request, Store $store): JsonResponse
    {
        $store->update($request->sanitized());

        return new StoreResource($store)
            ->additional([
                'success' => true,
                'message' => 'Магазин обновлен',
            ])
            ->response();
    }

    public function destroy(Store $store): JsonResponse
    {
        $hasReservations = StockReservation::query()
            ->where('store_id', $store->id)
            ->where('status', ReservationStatus::Reserved)
            ->exists();

        if ($hasReservations) {
            abort(409, 'Нельзя отключить магазин с активными резервами');
        }

        $store->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Магазин отключен',
        ]);
    }
}
