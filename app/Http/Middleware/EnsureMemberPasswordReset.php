<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberPasswordReset
{
    /**
     * Intercept authenticated requests under the 'member' guard when password reset is required.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('member')->user() ?? $request->user('member');

        if ($user && $user->must_reset_password) {
            // Explicit whitelist to prevent redirect loops per PORT-01, DEC-06 (D-06), and T-09-03
            if ($request->routeIs('portal.password.reset', 'portal.password.update', 'portal.logout')
                || $request->is('portal/password/reset', 'portal/password/update', 'portal/logout')) {
                return $next($request);
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Password reset is required before accessing the member portal.',
                    'password_reset_required' => true,
                ], Response::HTTP_FORBIDDEN);
            }

            return redirect()->route('portal.password.reset');
        }

        if ($user && !$user->must_reset_password) {
            // Forward user away from reset screen if already compliant
            if ($request->routeIs('portal.password.reset', 'portal.password.update')
                || $request->is('portal/password/reset', 'portal/password/update')) {
                return redirect()->route('portal.profile');
            }
        }

        return $next($request);
    }
}
