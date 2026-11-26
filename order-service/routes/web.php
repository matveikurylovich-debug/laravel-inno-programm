<?php

declare(strict_types=1);

use App\Models\OutboxMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/ready', function (): JsonResponse {
    $oldest = OutboxMessage::query()->where('status', 'pending')->orderBy('id')->first();
    $lag = $oldest === null || $oldest->created_at === null
        ? 0
        : (int) abs(now()->diffInSeconds($oldest->created_at));
    $rawLimit = config('orders.outbox_lag_seconds', 120);
    $limit = is_int($rawLimit) ? $rawLimit : 120;

    if ($lag > $limit) {
        Log::warning('Outbox отстаёт', ['seconds' => $lag]);

        return response()->json(['ready' => false, 'outbox_lag_seconds' => $lag], 503);
    }

    return response()->json(['ready' => true, 'outbox_lag_seconds' => $lag]);
});
