<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\NotificationLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin NotificationLog
 */
class NotificationLogResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     event_id: string|null,
     *     user_id: int|string|null,
     *     event: string,
     *     channel: string,
     *     recipient: string|null,
     *     status: string,
     *     attempts: int,
     *     is_dlq: bool,
     *     error_message: string|null,
     *     sent_at: string|null,
     *     replayed_by: int|string|null,
     *     replayed_at: string|null,
     *     created_at: string|null,
     *     updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->getKey(),
            'event_id' => $this->event_id,
            'user_id' => $this->user_id,
            'event' => $this->event,
            'channel' => $this->channel->value,
            'recipient' => $this->recipient,
            'status' => $this->status->value,
            'attempts' => $this->attempts,
            'is_dlq' => $this->is_dlq,
            'error_message' => $this->error_message,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'replayed_by' => $this->replayed_by,
            'replayed_at' => $this->replayed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
