<?php

declare(strict_types=1);

return [
    'payment_ttl_minutes' => (int) env('ORDER_PAYMENT_TTL_MINUTES', 30),
    'reserve_timeout_minutes' => (int) env('ORDER_RESERVE_TIMEOUT_MINUTES', 5),
    'outbox_lag_seconds' => (int) env('ORDER_OUTBOX_LAG_SECONDS', 120),
];
