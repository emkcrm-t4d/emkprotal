<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Domain\Audit\Models\AuditLog;
use App\Models\MemberUser;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal')]
class PortalFirstPasswordReset extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $requiresCurrentPassword = true;

    public function mount(): mixed
    {
        $user = Auth::guard('member')->user();

        if (!$user) {
            return redirect()->route('portal.login');
        }

        if (!$user->must_reset_password) {
            return redirect()->route('portal.profile');
        }

        $this->requiresCurrentPassword = ($user->password !== null);

        return null;
    }

    /**
     * Submit permanent password replacement and clear must_reset_password flag.
     */
    public function updatePassword(): mixed
    {
        /** @var MemberUser|null $user */
        $user = Auth::guard('member')->user();

        if (!$user) {
            return redirect()->route('portal.login');
        }

        $rules = [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        if ($this->requiresCurrentPassword && $user->password !== null) {
            $rules['current_password'] = ['required', 'string'];
            $rules['password'][] = 'different:current_password';
        }

        $this->validate($rules, [
            'current_password.required' => 'Please enter your temporary/current password.',
            'password.required' => 'Please create a new permanent password.',
            'password.min' => 'The permanent password must be at least 8 characters.',
            'password.different' => 'The new password must be different from your temporary password.',
            'password.confirmed' => 'The password confirmation does not match.',
        ]);

        if ($this->requiresCurrentPassword && $user->password !== null) {
            if (!Hash::check($this->current_password, $user->password)) {
                $this->addError('current_password', 'The current password provided does not match our records.');
                return null;
            }
        }

        // Apply new permanent credentials and clear reset invariant
        $user->password = Hash::make($this->password);
        $user->must_reset_password = false;
        $user->save();

        // Immutable audit trail entry per DEC-11 (D-11)
        AuditLog::create([
            'workspace_id' => $user->workspace_id,
            'actor_id' => $user->id,
            'actor_guard' => 'member',
            'event_type' => 'portal_member_password_reset',
            'auditable_type' => MemberUser::class,
            'auditable_id' => $user->id,
            'old_values' => [
                'must_reset_password' => true,
            ],
            'new_values' => [
                'must_reset_password' => false,
            ],
            'reason' => 'First-login password reset completed',
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System / Console',
            'created_at' => now(),
        ]);

        session()->flash('status', 'Your password has been set successfully. Welcome to your EMK Center Member Portal.');

        return redirect()->route('portal.profile');
    }

    public function render(): View
    {
        return view('livewire.portal.portal-first-password-reset');
    }
}
