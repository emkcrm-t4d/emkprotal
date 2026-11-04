<?php

declare(strict_types=1);

namespace App\Domain\Leads\Enums;

enum VerificationStream: string
{
    case PAYMENT = 'payment';
    case DOCUMENT = 'document';
    case PHOTO = 'photo';
}
