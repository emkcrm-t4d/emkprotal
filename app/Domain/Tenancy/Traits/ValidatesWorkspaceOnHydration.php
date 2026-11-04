<?php

declare(strict_types=1);

namespace App\Domain\Tenancy\Traits;

use App\Domain\Tenancy\Contracts\TenantEntityInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait ValidatesWorkspaceOnHydration
{
    /**
     * Intercept Livewire component hydration on every incoming request.
     */
    public function hydrateValidatesWorkspaceOnHydration(): void
    {
        $this->validateWorkspaceHydration();
    }

    /**
     * Inspect public properties to ensure no cross-workspace tenant model is hydrated.
     */
    public function validateWorkspaceHydration(): void
    {
        $user = Auth::user();

        // Unauthenticated or Superadmin/Global Admin users bypass tenant hydration assertion
        if ($user === null || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']))) {
            return;
        }

        $activeWorkspaceId = (string) $user->workspace_id;

        // Inspect all public properties declared on the component
        $reflection = new \ReflectionClass($this);
        $properties = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $value = $property->getValue($this);

            if ($value instanceof TenantEntityInterface || $value instanceof Model) {
                $this->assertModelWorkspaceOwnership($value, $activeWorkspaceId);
            } elseif (is_iterable($value)) {
                foreach ($value as $item) {
                    if ($item instanceof TenantEntityInterface || $item instanceof Model) {
                        $this->assertModelWorkspaceOwnership($item, $activeWorkspaceId);
                    }
                }
            }
        }
    }

    /**
     * Assert model belongs strictly to the user's active workspace.
     */
    protected function assertModelWorkspaceOwnership(Model|TenantEntityInterface $model, string $activeWorkspaceId): void
    {
        $workspaceId = null;

        if ($model instanceof TenantEntityInterface) {
            $workspaceId = $model->getWorkspaceId();
        } elseif (isset($model->workspace_id)) {
            $workspaceId = (string) $model->workspace_id;
        }

        if ($workspaceId === null) {
            return;
        }

        if ($workspaceId !== $activeWorkspaceId) {
            Log::alert('Livewire hydration tenant tampering detected', [
                'component' => static::class,
                'model' => get_class($model),
                'model_id' => method_exists($model, 'getKey') ? $model->getKey() : null,
                'model_workspace' => $workspaceId,
                'user_workspace' => $activeWorkspaceId,
                'user_id' => Auth::id(),
            ]);

            \Illuminate\Support\Facades\App::abort(403, 'Unauthorized workspace entity.');
        }
    }
}
