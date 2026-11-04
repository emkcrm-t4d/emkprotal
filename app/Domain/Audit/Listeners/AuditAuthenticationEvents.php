<?php

declare(strict_types=1);

namespace App\Domain\Audit\Listeners;

use App\Domain\Audit\Models\AuditLog;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditAuthenticationEvents
{
    /**
     * Handle user login event.
     */
    public function handleLogin(Login $event): void
    {
        $user = $event->user;

        AuditLog::create([
            'workspace_id' => $user?->workspace_id,
            'actor_id' => $user?->getAuthIdentifier(),
            'actor_guard' => $event->guard ?? 'web',
            'event_type' => AuditLog::EVENT_LOGIN_SUCCESS,
            'auditable_type' => $user ? get_class($user) : null,
            'auditable_id' => $user?->getKey(),
            'old_values' => null,
            'new_values' => [
                'email' => $user?->email,
            ],
            'reason' => 'Successful authentication',
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent() ?? 'System / Console',
            'created_at' => now(),
        ]);
    }

    /**
     * Handle failed authentication attempt.
     */
    public function handleFailed(Failed $event): void
    {
        $attemptedEmail = $event->credentials['email']
            ?? $event->credentials['username']
            ?? 'unknown';

        $user = $event->user;

        // DEC-11 / ASVS V8.2: Password submitted in credentials must NEVER be logged
        AuditLog::create([
            'workspace_id' => $user?->workspace_id,
            'actor_id' => $user?->getAuthIdentifier(),
            'actor_guard' => $event->guard ?? 'web',
            'event_type' => AuditLog::EVENT_LOGIN_FAILED,
            'auditable_type' => $user ? get_class($user) : null,
            'auditable_id' => $user?->getKey(),
            'old_values' => null,
            'new_values' => [
                'attempted_identity' => (string) $attemptedEmail,
            ],
            'reason' => 'Invalid credentials for ' . $attemptedEmail,
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent() ?? 'System / Console',
            'created_at' => now(),
        ]);
    }

    /**
     * Handle user logout event.
     */
    public function handleLogout(Logout $event): void
    {
        $user = $event->user;

        AuditLog::create([
            'workspace_id' => $user?->workspace_id,
            'actor_id' => $user?->getAuthIdentifier(),
            'actor_guard' => $event->guard ?? 'web',
            'event_type' => AuditLog::EVENT_LOGOUT,
            'auditable_type' => $user ? get_class($user) : null,
            'auditable_id' => $user?->getKey(),
            'old_values' => null,
            'new_values' => null,
            'reason' => 'User logged out',
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent() ?? 'System / Console',
            'created_at' => now(),
        ]);
    }

    /**
     * Handle password reset event.
     */
    public function handlePasswordReset(PasswordReset $event): void
    {
        $user = $event->user;

        AuditLog::create([
            'workspace_id' => $user?->workspace_id,
            'actor_id' => $user?->getAuthIdentifier(),
            'actor_guard' => Auth::getDefaultDriver() ?? 'web',
            'event_type' => AuditLog::EVENT_PASSWORD_RESET,
            'auditable_type' => $user ? get_class($user) : null,
            'auditable_id' => $user?->getKey(),
            'old_values' => null,
            'new_values' => null,
            'reason' => 'Password reset completed',
            'ip_address' => Request::ip() ?? '127.0.0.1',
            'user_agent' => Request::userAgent() ?? 'System / Console',
            'created_at' => now(),
        ]);
    }

    /**
     * Register event listeners with the event dispatcher.
     *
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Failed::class => 'handleFailed',
            Logout::class => 'handleLogout',
            PasswordReset::class => 'handlePasswordReset',
        ];
    }
}
