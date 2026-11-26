<?php

declare(strict_types=1);

return [
    'payment_ttl_minutes' => (int) env('ORDER_PAYMENT_TTL_MINUTES', 30),
    'reservation_ttl_minutes' => (int) env('CATALOG_RESERVATION_TTL_MINUTES', 45),
];
