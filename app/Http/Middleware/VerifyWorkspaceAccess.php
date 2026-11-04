<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Workspaces\Models\Workspace;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class VerifyWorkspaceAccess
{
    /**
     * Handle an incoming request across workspace-scoped boundaries.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        }

        // Superadmin and Global Admin hold cross-workspace access
        if (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin'])) {
            return $next($request);
        }

        $routeWorkspace = $request->route('workspace');

        // Normalise string parameter if Route-Model binding resolved a Workspace instance
        if ($routeWorkspace instanceof Workspace) {
            $routeWorkspaceId = (string) $routeWorkspace->id;
        } else {
            $routeWorkspaceId = (string) $routeWorkspace;
        }

        // Validate slug regex (^[a-z0-9-]+$)
        if ($routeWorkspaceId === '' || !preg_match('/^[a-z0-9-]+$/', $routeWorkspaceId)) {
            Log::warning('Malformed workspace identifier blocked', [
                'user_id' => $user->id,
                'user_workspace' => $user->workspace_id,
                'attempted_workspace' => $routeWorkspaceId,
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
            ]);

            abort(Response::HTTP_FORBIDDEN, 'Unauthorized workspace access.');
        }

        // Superadmin and Global Admin hold cross-workspace access
        if (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin'])) {
            return $next($request);
        }

        if (empty($user->workspace_id) || $routeWorkspaceId !== (string) $user->workspace_id) {
            // Fail closed: Log security attempt and abort immediately
            Log::warning('Cross-workspace route access attempt blocked', [
                'user_id' => $user->id,
                'user_workspace' => $user->workspace_id,
                'attempted_workspace' => $routeWorkspaceId,
                'ip' => $request->ip(),
                'url' => $request->fullUrl(),
            ]);

            abort(Response::HTTP_FORBIDDEN, 'Unauthorized workspace access.');
        }

        return $next($request);
    }
}
