<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'workspace_id', 'guard', 'is_active', 'must_reset_password', 'zoho_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable {
        HasRoles::assignRole as protected spatieAssignRole;
    }

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
        'zoho_id',
    ];

    /**
     * Resolve guard name for Spatie Permission checks based on user guard attribute.
     */
    public function guardName(): string
    {
        return $this->guard ?? 'web';
    }

    /**
     * Assign the given role to the model, creating it if it does not yet exist.
     */
    public function assignRole(...$roles): static
    {
        $guard = $this->guardName();
        $roleClass = $this->getRoleClass();

        $resolvedRoles = collect($roles)->flatten()->map(function ($role) use ($roleClass, $guard) {
            if (is_string($role)) {
                return $roleClass::findOrCreate($role, $guard);
            }

            return $role;
        });

        $this->spatieAssignRole($resolvedRoles->all());

        return $this;
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
     * Determine if user has Workspace Manager privileges.
     */
    public function isWorkspaceManager(): bool
    {
        return $this->hasRole('workspace_manager');
    }

    /**
     * Determine if user has access to a specific workspace.
     */
    public function hasWorkspaceAccess(string $workspaceId): bool
    {
        if ($this->isSuperAdmin() || $this->isGlobalAdmin()) {
            return true;
        }

        return !empty($this->workspace_id) && $this->workspace_id === $workspaceId;
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
