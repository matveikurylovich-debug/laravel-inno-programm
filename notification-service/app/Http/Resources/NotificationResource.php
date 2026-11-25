<?php

namespace App\Http\Resources;

use App\Models\NotificationLog;
use App\Support\SensitivePayload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;

class NotificationResource extends JsonResource
{
    /**
     * Преобразование сущности из MongoDB в массив для JSON-ответа.
     *
     * @return array{
     *     id: string,
     *     event: string,
     *     channel: string,
     *     recipient: string|null,
     *     status: string,
     *     attempts: int,
     *     is_dlq: bool,
     *     error_message: string|null,
     *     payload: array<string, mixed>|null,
     *     sent_at: string|null,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        $notification = $this->resource;

        if (! $notification instanceof NotificationLog) {
            throw new LogicException('NotificationResource requires a NotificationLog model.');
        }

        $recipient = $notification->recipient ?? $notification->recipient_email;
        $payload = $notification->payload;
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            $payload = is_array($decoded) ? $decoded : null;
        }

        return [
            // Превращаем системный ObjectId MongoDB в обычную строку
            'id' => (string) $notification->_id,
            'event' => (string) $notification->event,
            'channel' => (string) $notification->channel,
            'recipient' => $recipient !== null ? (string) $recipient : null,
            'status' => (string) $notification->status,
            'attempts' => (int) $notification->attempts,
            'is_dlq' => (bool) $notification->is_dlq,
            'error_message' => $notification->error_message,
            'payload' => is_array($payload) ? SensitivePayload::redact($payload) : null,

            // Приводим даты к единому международному стандарту ISO 8601
            'sent_at' => $notification->sent_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'updated_at' => $notification->updated_at?->toIso8601String(),
        ];
    }
}
