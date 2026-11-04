<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordReset
{
    /**
     * Intercept authenticated requests when first-login password reset is required.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_reset_password) {
            // Allow password update routes and logout route
            if ($request->routeIs('password.reset-required', 'password.update-required', 'logout')
                || $request->is('password/reset-required', 'password/update-required', 'logout')) {
                return $next($request);
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'Password reset is required before accessing the application.',
                    'password_reset_required' => true,
                ], Response::HTTP_FORBIDDEN);
            }

            return redirect()->route('password.reset-required');
        }

        return $next($request);
    }
}
