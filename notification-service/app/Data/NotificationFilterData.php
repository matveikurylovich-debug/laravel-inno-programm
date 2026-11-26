<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\NotificationStatus;
use Carbon\CarbonInterface;

final readonly class NotificationFilterData
{
    public function __construct(
        public ?NotificationStatus $status = null,
        public ?string $channel = null,
        public ?string $recipient = null,
        public ?CarbonInterface $dateFrom = null,
        public ?CarbonInterface $dateTo = null,
        public int $perPage = 15,
    ) {}
}
