<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Преобразование сущности из MongoDB в массив для JSON-ответа.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Превращаем системный ObjectId MongoDB в обычную строку
            'id' => (string) $this->_id,
            'event' => $this->event,
            'channel' => $this->channel,
            'recipient' => $this->recipient ?? $this->recipient_email,
            'status' => $this->status,
            'attempts' => (int) $this->attempts,
            'is_dlq' => (bool) $this->is_dlq,
            'error_message' => $this->error_message,
            'payload' => $this->payload,

            // Приводим даты к единому международному стандарту ISO 8601
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
