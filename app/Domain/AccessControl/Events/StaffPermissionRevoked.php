<?php

declare(strict_types=1);

namespace App\Domain\AccessControl\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StaffPermissionRevoked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $manager,
        public User $targetStaff,
        public string $permission
    ) {}
}
