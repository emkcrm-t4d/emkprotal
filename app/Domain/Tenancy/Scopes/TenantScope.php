<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

final class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();

        // Superadmin and Global Admin retain cross-workspace query visibility
        if (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin'])) {
            return;
        }

        // Enforce strict workspace isolation
        if (!empty($user->workspace_id)) {
            $builder->where($model->qualifyColumn('workspace_id'), '=', (string) $user->workspace_id);
        } else {
            // Unbound staff user: fail-closed by forcing empty result
            $builder->whereRaw('1 = 0');
        }
    }
}
