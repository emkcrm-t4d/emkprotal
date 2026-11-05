<?php

declare(strict_types=1);

namespace App\Domain\Payments\Exceptions;

use RuntimeException;

class UnsupportedPaymentOperationException extends RuntimeException
{
}
