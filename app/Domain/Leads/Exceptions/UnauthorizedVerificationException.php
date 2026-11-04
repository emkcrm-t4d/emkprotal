<?php

declare(strict_types=1);

namespace App\Domain\Leads\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;

class UnauthorizedVerificationException extends AuthorizationException
{
    //
}
