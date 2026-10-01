<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RealtimeNotificationEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  int|string  $userId  ID пользователя, которому шлём пуш
     * @param  string  $type  Тип события: 'user.registered', 'auth.password_reset', 'auth.two_factor_code', 'auth.profile_update_confirm'
     * @param  string  $title  Заголовок алерта
     * @param  string  $message  Текст алерта
     * @param  array  $data  Дополнительные данные (например, код или ссылка)
     */
    public function __construct(
        public int|string $userId,
        public string $type,
        public string $title,
        public string $message,
        public array $data = []
    ) {}

    /**
     * Канал вещания.
     * Отправляем в персональный канал конкретного пользователя: user.{userId}
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('user.'.$this->userId),
        ];
    }

    /**
     * Имя события, которое поймает фронтенд
     */
    public function broadcastAs(): string
    {
        return 'notification.received';
    }

    /**
     * Данные, которые улетят в сокет
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'created_at' => now()->toIso8601String(),
        ];
    }
}
