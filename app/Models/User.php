<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'workspace_id', 'guard', 'is_active', 'must_reset_password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'workspace_id',
        'guard',
        'is_active',
        'must_reset_password',
    ];

    /**
     * In-memory or attribute-based role assignment.
     *
     * @var array<string>
     */
    public array $assignedRoles = [];

    /**
     * Assign role to user for testing/runtime authorization.
     */
    public function assignRole(string ...$roles): self
    {
        foreach ($roles as $role) {
            if (!in_array($role, $this->assignedRoles, true)) {
                $this->assignedRoles[] = $role;
            }
        }

        return $this;
    }

    /**
     * Determine if user has the given role.
     */
    public function hasRole(string|array $roles): bool
    {
        $roleList = is_array($roles) ? $roles : [$roles];

        if (isset($this->role) && in_array($this->role, $roleList, true)) {
            return true;
        }

        if (!empty(array_intersect($roleList, $this->assignedRoles))) {
            return true;
        }

        return false;
    }

    /**
     * Determine if user has Superadmin privileges.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    /**
     * Determine if user has Global Admin privileges.
     */
    public function isGlobalAdmin(): bool
    {
        return $this->hasRole('global_admin');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_reset_password' => 'boolean',
        ];
    }
}
