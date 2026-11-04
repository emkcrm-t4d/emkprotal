<?php

declare(strict_types=1);

namespace App\Domain\Leads\Enums;

enum VerificationStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case VERIFIED = 'verified';
    case MISSING_ITEMS = 'missing_items';
    case REUPLOAD_REQUESTED = 'reupload_requested';
    case REJECTED = 'rejected';
}
