<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Members\Models\Member;
use App\Models\MemberUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal')]
class PortalLogin extends Component
{
    public string $identifier = '';
    public string $password = '';
    public bool $remember = false;

    public bool $showForgotPasswordModal = false;
    public string $forgotIdentifier = '';
    public ?string $forgotSuccessMessage = null;

    public function openForgotPasswordModal(): void
    {
        $this->forgotIdentifier = $this->identifier;
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
            'forgotIdentifier' => ['required', 'string'],
        ], [
            'forgotIdentifier.required' => 'Please enter your registered email address or Member ID.',
        ]);

        $input = trim($this->forgotIdentifier);
        $cleanEmail = null;
        $memberName = 'Member';

        if (str_contains($input, '@')) {
            $cleanEmail = Str::lower($input);
            $member = MemberUser::withoutGlobalScopes()->where('email', $cleanEmail)->first();
            if ($member) {
                $memberName = $member->name;
            }
        } else {
            $normalizedMemberId = Str::upper($input);
            $memberProfile = Member::withoutGlobalScopes()->where('member_id', $normalizedMemberId)->first();
            if ($memberProfile) {
                $cleanEmail = Str::lower($memberProfile->email);
                $memberName = $memberProfile->full_name;
            }
        }

        if ($cleanEmail) {
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
                    workspaceId: 'ws-emk-dhaka',
                    templateKey: \App\Domain\Mail\Models\EmailTemplate::MILESTONE_PASSWORD_RESET,
                    recipientEmail: $cleanEmail,
                    variables: [
                        'member_name' => $memberName,
                        'workspace_name' => 'EMK Center Dhaka',
                        'action_url' => $resetUrl,
                    ],
                    locale: 'en'
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Member password reset dispatch failed: ' . $e->getMessage());
            }
        }

        $this->forgotSuccessMessage = 'If an account matches this detail, we have dispatched a secure password reset link to the registered email address.';
    }

    protected function rules(): array
    {
        return [
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    protected function messages(): array
    {
        return [
            'identifier.required' => 'Please enter your registered email or Member ID.',
            'password.required' => 'Please enter your password.',
        ];
    }

    /**
     * Authenticate member user against 'member' guard using email or Member ID.
     */
    public function login(): mixed
    {
        $this->validate();

        $trimmedIdentifier = trim($this->identifier);
        $throttleKey = Str::lower($trimmedIdentifier) . '|' . request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('identifier', trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]));
            return null;
        }

        // Dual-Identifier Resolution: Email or Formatted Member ID
        if (str_contains($trimmedIdentifier, '@')) {
            $resolvedEmail = Str::lower($trimmedIdentifier);
        } else {
            $member = Member::withoutGlobalScopes()
                ->where('member_id', strtoupper($trimmedIdentifier))
                ->first();

            if (!$member || empty($member->email)) {
                RateLimiter::hit($throttleKey);
                $this->addError('identifier', trans('auth.failed'));
                return null;
            }

            $resolvedEmail = Str::lower($member->email);
        }

        // Account deactivation check (PORT-01 / T-09-01)
        $candidateUser = MemberUser::withoutGlobalScopes()
            ->where('guard', 'member')
            ->where('email', $resolvedEmail)
            ->first();

        if ($candidateUser && !$candidateUser->is_active) {
            RateLimiter::hit($throttleKey);
            $this->addError('identifier', 'This membership portal account has been deactivated. Please contact the front desk.');
            return null;
        }

        // Attempt authentication strictly under 'member' guard
        $authenticated = Auth::guard('member')->attempt([
            'email' => $resolvedEmail,
            'password' => $this->password,
            'is_active' => true,
        ], $this->remember);

        if (!$authenticated) {
            RateLimiter::hit($throttleKey);
            $this->addError('identifier', trans('auth.failed'));
            return null;
        }

        RateLimiter::clear($throttleKey);
        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        /** @var MemberUser $memberUser */
        $memberUser = Auth::guard('member')->user();

        // Audit login event per DEC-11 (D-11)
        AuditLog::create([
            'workspace_id' => $memberUser->workspace_id,
            'actor_id' => $memberUser->id,
            'actor_guard' => 'member',
            'event_type' => 'portal_member_login',
            'auditable_type' => MemberUser::class,
            'auditable_id' => $memberUser->id,
            'old_values' => null,
            'new_values' => [
                'identifier_input' => $this->identifier,
                'resolved_email' => $resolvedEmail,
                'identifier_type' => str_contains($trimmedIdentifier, '@') ? 'email' : 'member_id',
            ],
            'reason' => 'Successful portal member authentication',
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System / Console',
            'created_at' => now(),
        ]);

        return redirect()->intended(route('portal.profile'));
    }

    /**
     * Backward-compatible alias for login.
     */
    public function authenticate(): mixed
    {
        return $this->login();
    }

    public function render(): View
    {
        return view('livewire.portal.portal-login');
    }
}
