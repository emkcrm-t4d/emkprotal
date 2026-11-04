<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Platform\Registry\ModuleRegistryInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function __construct(
        protected ModuleRegistryInterface $moduleRegistry
    ) {}

    /**
     * Intercept request and abort with 404 if the requested module is disabled for the workspace.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $moduleKey): Response
    {
        $workspace = $request->route('workspace') ?? $request->attributes->get('workspace');
        $workspaceId = is_object($workspace) && isset($workspace->id)
            ? (string) $workspace->id
            : ($workspace ?? $request->user()?->workspace_id);

        $workspaceId = is_string($workspaceId) ? $workspaceId : null;

        if (!$this->moduleRegistry->isModuleEnabledForWorkspace($moduleKey, $workspaceId)) {
            abort(404, "Module [{$moduleKey}] is not enabled for this workspace.");
        }

        return $next($request);
    }
}
