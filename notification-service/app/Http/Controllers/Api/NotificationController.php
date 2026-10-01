<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Mail\Auth\PasswordResetMail;
use App\Mail\Auth\ProfileUpdateConfirmMail;
use App\Mail\Auth\TwoFactorCodeMail;
use App\Mail\Auth\UserRegisteredMail;
use App\Mail\Order\OrderCancelledMail;
use App\Mail\Order\OrderConfirmedMail;
use App\Mail\Order\OrderPaidMail;
use App\Models\NotificationLog;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Throwable;

class NotificationController extends Controller
{
    /**
     * Получение списка уведомлений с фильтрацией и пагинацией.
     * GET /api/notifications
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        // 1. Валидация входных параметров
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,sent,failed'],
            'channel' => ['nullable', 'string'],
            'recipient' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = NotificationLog::query();

        // 2. Фильтр по статусу (pending, sent, failed)
        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        // 3. Фильтр по каналу доставки (email и т.д.)
        if (! empty($validated['channel'])) {
            $query->where('channel', $validated['channel']);
        }

        // 4. Поиск по email получателя (проверяем оба поля: recipient и recipient_email)
        if (! empty($validated['recipient'])) {
            $recipient = $validated['recipient'];
            $query->where(function ($q) use ($recipient) {
                $q->where('recipient', 'like', "%{$recipient}%")
                    ->orWhere('recipient_email', 'like', "%{$recipient}%");
            });
        }

        // 5. Диапазон дат по полю created_at
        if (! empty($validated['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($validated['date_from'])->startOfDay());
        }

        if (! empty($validated['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($validated['date_to'])->endOfDay());
        }

        // Сортировка: свежие события первыми
        $query->orderBy('created_at', 'desc');

        // Пагинация: по умолчанию 15 записей на страницу
        $perPage = (int) ($validated['per_page'] ?? 15);
        $notifications = $query->paginate($perPage);

        return NotificationResource::collection($notifications);
    }

    /**
     * Детальная информация по конкретному уведомлению.
     * GET /api/notifications/{id}
     */
    public function show(string $id): JsonResponse|NotificationResource
    {
        $log = NotificationLog::find($id);

        if (! $log) {
            return response()->json([
                'message' => "Уведомление с ID [{$id}] не найдено.",
            ], 404);
        }

        return new NotificationResource($log);
    }

    /**
     * Повторная отправка упавшего уведомления.
     * POST /api/notifications/{id}/replay
     */
    public function replay(string $id): JsonResponse|NotificationResource
    {
        $log = NotificationLog::find($id);

        if (! $log) {
            return response()->json([
                'message' => "Уведомление с ID [{$id}] не найдено.",
            ], 404);
        }

        if ($log->status !== NotificationLog::STATUS_FAILED) {
            return response()->json([
                'message' => 'Повторная отправка доступна только для статуса failed.',
            ], 422);
        }

        $log->increment('attempts');

        try {
            $this->resend($log);

            $log->update([
                'status' => NotificationLog::STATUS_SENT,
                'sent_at' => now(),
                'error_message' => null,
                'is_dlq' => false,
            ]);

            return new NotificationResource($log->fresh());
        } catch (Throwable $e) {
            $log->update([
                'status' => NotificationLog::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Не удалось повторить отправку.',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    private function resend(NotificationLog $log): void
    {
        $payload = is_array($log->payload) ? $log->payload : [];
        $event = $payload['event'] ?? $log->event;
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $email = $log->recipient ?: ($log->recipient_email ?: ($payload['email'] ?? null));

        if (! $email) {
            throw new InvalidArgumentException('У записи нет email получателя.');
        }

        $mailable = match ($event) {
            'user.registered' => new UserRegisteredMail(
                name: $data['name'] ?? 'Пользователь',
            ),
            'auth.password_reset' => new PasswordResetMail(
                resetUrl: $data['reset_url'] ?? '#',
                expiresIn: (int) ($data['expires_in'] ?? 15),
            ),
            'auth.two_factor_code' => new TwoFactorCodeMail(
                code: (string) ($data['code'] ?? '000000'),
                ttlMinutes: (int) ($data['ttl_minutes'] ?? 5),
            ),
            'auth.profile_update_confirm' => new ProfileUpdateConfirmMail(
                code: (string) ($data['code'] ?? '000000'),
                field: $data['field'] ?? 'профиль',
            ),
            'order.paid' => new OrderPaidMail(
                orderId: $data['order_id'] ?? '—',
                amount: $data['amount'] ?? 0,
                currency: $data['currency'] ?? 'RUB',
                items: $data['items'] ?? [],
            ),
            'order.confirmed' => new OrderConfirmedMail(
                orderId: $data['order_id'] ?? '—',
                estimatedDelivery: $data['estimated_delivery'] ?? null,
            ),
            'order.cancelled' => new OrderCancelledMail(
                orderId: $data['order_id'] ?? '—',
                reason: $data['reason'] ?? 'не указана',
            ),
            default => throw new InvalidArgumentException("Событие [{$event}] нельзя переотправить."),
        };

        Mail::to($email)->send($mailable);
    }
}
