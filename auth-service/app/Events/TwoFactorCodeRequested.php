<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use App\Support\TwoFactorCacheKey;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TwoFactorCodeRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $code,
        public readonly int $ttlMinutes = TwoFactorCacheKey::TTL_MINUTES
    ) {}
}
