<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Domain\Audit\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.auth')]
class StaffLogin extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public bool $showForgotPasswordModal = false;
    public string $forgotEmail = '';
    public ?string $forgotSuccessMessage = null;

    public function openForgotPasswordModal(): void
    {
        $this->forgotEmail = $this->email;
        $this->forgotSuccessMessage = null;
        $this->resetErrorBag();
        $this->showForgotPasswordModal = true;
    }

    public function closeForgotPasswordModal(): void
    {
        $this->showForgotPasswordModal = false;
        $this->forgotSuccessMessage = null;
    }

    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'forgotEmail' => ['required', 'email'],
        ], [
            'forgotEmail.required' => 'Please enter your registered email address.',
            'forgotEmail.email' => 'Please enter a valid email address.',
        ]);

        $cleanEmail = Str::lower(trim($this->forgotEmail));

        // EMK-005: Rate limit password reset link dispatch to prevent automated spam
        $throttleKey = 'password-reset-link|' . $cleanEmail;
        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('forgotEmail', "Too many reset requests. Please wait {$seconds} seconds before trying again.");
            return;
        }
        RateLimiter::hit($throttleKey, 120);

        $user = User::withoutGlobalScopes()->where('email', $cleanEmail)->first();

        if ($user && $user->is_active) {
            $plainToken = Str::random(64);
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $cleanEmail],
                [
                    'token' => \Illuminate\Support\Facades\Hash::make($plainToken),
                    'created_at' => now(),
                ]
            );

            $resetUrl = route('password.reset', [
                'token' => $plainToken,
                'email' => $cleanEmail,
            ]);

            try {
                $dispatcher = app(\App\Domain\Mail\Services\QueuedEmailDispatcher::class);
                $dispatcher->dispatchTemplate(
                    workspaceId: $user->workspace_id ?: 'ws-emk-dhaka',
                    templateKey: \App\Domain\Mail\Models\EmailTemplate::MILESTONE_PASSWORD_RESET,
                    recipientEmail: $cleanEmail,
                    variables: [
                        'member_name' => $user->name,
                        'workspace_name' => 'EMK Center Dhaka',
                        'action_url' => $resetUrl,
                    ],
                    locale: 'en'
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Password reset dispatch failed: ' . $e->getMessage());
            }
        }

        $this->forgotSuccessMessage = 'If your email is registered, we have dispatched a secure password reset link to your inbox.';
    }

    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    protected function messages(): array
    {
        return [
            'email.required' => 'Please enter your staff or admin email address.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Please enter your password.',
        ];
    }

    public function fillCredentials(string $email, string $password): void
    {
        $this->email = $email;
        $this->password = $password;
        $this->resetErrorBag();
    }

    /**
     * Authenticate staff / administrator user against the 'web' guard.
     */
    public function login(): mixed
    {
        $this->validate();

        $cleanEmail = Str::lower(trim($this->email));
        $throttleKey = $cleanEmail . '|' . request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('email', trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]));
            return null;
        }

        $user = User::where('email', $cleanEmail)->first();

        if (! $user || ! Auth::guard('web')->attempt(['email' => $cleanEmail, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);

            AuditLog::create([
                'workspace_id' => $user?->workspace_id ?? 'ws-emk-dhaka',
                'actor_id' => null,
                'actor_guard' => 'web',
                'event_type' => 'auth.staff_login_failed',
                'auditable_type' => User::class,
                'auditable_id' => $user?->id,
                'old_values' => null,
                'new_values' => ['email' => $cleanEmail, 'guard' => 'web'],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'Staff Console',
                'created_at' => now(),
            ]);

            $this->addError('email', 'These credentials do not match our records.');
            return null;
        }

        if (! $user->is_active) {
            Auth::guard('web')->logout();
            $this->addError('email', 'This staff account has been deactivated. Please contact your Workspace Manager or Superadmin.');
            return null;
        }

        RateLimiter::clear($throttleKey);
        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        AuditLog::create([
            'workspace_id' => $user->workspace_id ?? 'ws-emk-dhaka',
            'actor_id' => $user->id,
            'actor_guard' => 'web',
            'event_type' => 'auth.staff_login_success',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => null,
            'new_values' => ['email' => $user->email, 'guard' => 'web'],
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'Staff Console',
            'created_at' => now(),
        ]);

        if ($user->must_reset_password) {
            return redirect()->route('password.reset-required');
        }

        $workspace = $user->workspace_id ?: 'ws-emk-dhaka';
        return redirect()->intended(route('crm.dashboard', $workspace));
    }

    public function render(): View
    {
        return view('livewire.auth.staff-login');
    }
}
