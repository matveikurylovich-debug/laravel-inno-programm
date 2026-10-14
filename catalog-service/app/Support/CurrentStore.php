<?php

namespace App\Support;

/**
 * Магазин текущего запроса.
 * Пустое значение значит «платформенный доступ»: витрина списка магазинов и админ без store_id.
 */
class CurrentStore
{
    private ?int $id = null;

    public function set(?int $id): void
    {
        $this->id = $id;
    }

    public function id(): ?int
    {
        return $this->id;
    }
}
