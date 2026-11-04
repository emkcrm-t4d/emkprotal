<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Traits;

use App\Domain\Tenancy\Scopes\TenantScope;
use App\Domain\Workspaces\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    /**
     * Boot the BelongsToTenant trait for the model.
     */
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (Model $model): void {
            if (empty($model->workspace_id) && Auth::check()) {
                $user = Auth::user();
                if (!empty($user->workspace_id)) {
                    $model->workspace_id = (string) $user->workspace_id;
                }
            }
        });
    }

    /**
     * Relationship to the Workspace anchor model.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id', 'id');
    }
}
