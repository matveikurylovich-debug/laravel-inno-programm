<?php

declare(strict_types=1);

return [
    'retention_days' => (int) env('NOTIFICATION_LOG_RETENTION_DAYS', 90),
];
