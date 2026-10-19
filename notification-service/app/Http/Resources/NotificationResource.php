<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
        $recipient = $this->recipient ?? $this->recipient_email;

        return [
            // Превращаем системный ObjectId MongoDB в обычную строку
            'id' => (string) $this->_id,
            'event' => (string) $this->event,
            'channel' => (string) $this->channel,
            'recipient' => $recipient !== null ? (string) $recipient : null,
            'status' => (string) $this->status,
            'attempts' => (int) $this->attempts,
            'is_dlq' => (bool) $this->is_dlq,
            'error_message' => $this->error_message !== null ? (string) $this->error_message : null,
            'payload' => is_array($this->payload) ? $this->payload : null,

            // Приводим даты к единому международному стандарту ISO 8601
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
