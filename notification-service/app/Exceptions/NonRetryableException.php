<?php

declare(strict_types=1);

namespace App\Exceptions;

use Ecommerce\PlatformKit\Messaging\NonRetryableMessageException;

class NonRetryableException extends NonRetryableMessageException {}
