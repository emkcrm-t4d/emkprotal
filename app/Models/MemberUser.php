<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class MemberUser extends User
{
    protected $table = 'users';

    protected static function booted(): void
    {
        static::addGlobalScope('member_guard', function (Builder $builder): void {
            $builder->where('guard', 'member');
        });

        static::creating(function (MemberUser $user): void {
            $user->guard = 'member';
        });
    }

    public function guardName(): string
    {
        return 'member';
    }
}
