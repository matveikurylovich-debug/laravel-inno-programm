<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Token;

enum TokenType: string
{
    case Access = 'access';
    case Refresh = 'refresh';
}
