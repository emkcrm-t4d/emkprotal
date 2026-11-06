<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Domain\Audit\Models\AuditLog;
use App\Models\MemberUser;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.auth')]
class ResetPassword extends Component
{
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $isSuccess = false;
    public ?string $statusMessage = null;

    protected function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function mount(string $token = ''): void
    {
        $this->token = $token;
        $this->email = (string) request()->query('email', '');
    }

    public function resetPassword(): mixed
    {
        $this->validate();

        $cleanEmail = strtolower(trim($this->email));

        $record = DB::table('password_reset_tokens')
            ->where('email', $cleanEmail)
            ->first();

        if (! $record) {
            $this->addError('email', 'Invalid or expired password reset link. Please request a new one.');
            return null;
        }

        // Validate token (supporting either plaintext random token or bcrypt hashed token)
        $tokenValid = Hash::check($this->token, $record->token) || hash_equals($record->token, $this->token);

        if (! $tokenValid) {
            $this->addError('email', 'Invalid password reset token.');
            return null;
        }

        // Validate expiry (60 minutes standard)
        $createdAt = Carbon::parse($record->created_at);
        if ($createdAt->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $cleanEmail)->delete();
            $this->addError('email', 'This password reset link has expired. Please request a new one.');
            return null;
        }

        $newHashedPassword = Hash::make($this->password);
        $updatedAny = false;

        // Update staff User if exists
        $user = User::withoutGlobalScopes()->where('email', $cleanEmail)->first();
        if ($user) {
            $user->update([
                'password' => $newHashedPassword,
                'must_reset_password' => false,
            ]);

            AuditLog::create([
                'workspace_id' => $user->workspace_id ?? 'ws-emk-dhaka',
                'actor_id' => $user->id,
                'actor_guard' => 'web',
                'event_type' => 'auth.password_reset_completed',
                'auditable_type' => User::class,
                'auditable_id' => $user->id,
                'old_values' => null,
                'new_values' => ['email' => $cleanEmail, 'action' => 'password_reset'],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'Password Reset',
                'created_at' => now(),
            ]);

            $updatedAny = true;
        }

        // Update member user if exists
        $member = MemberUser::withoutGlobalScopes()->where('email', $cleanEmail)->first();
        if ($member) {
            $member->update([
                'password' => $newHashedPassword,
                'must_reset_password' => false,
            ]);

            AuditLog::create([
                'workspace_id' => $member->workspace_id ?? 'ws-emk-dhaka',
                'actor_id' => $member->id,
                'actor_guard' => 'member',
                'event_type' => 'auth.member_password_reset_completed',
                'auditable_type' => MemberUser::class,
                'auditable_id' => $member->id,
                'old_values' => null,
                'new_values' => ['email' => $cleanEmail, 'action' => 'password_reset'],
                'ip_address' => request()->ip() ?? '127.0.0.1',
                'user_agent' => request()->userAgent() ?? 'Member Password Reset',
                'created_at' => now(),
            ]);

            $updatedAny = true;
        }

        // Delete used token
        DB::table('password_reset_tokens')->where('email', $cleanEmail)->delete();

        $this->isSuccess = true;
        $this->statusMessage = 'Your password has been successfully reset! You can now sign in with your new credentials.';

        return null;
    }

    public function render(): View
    {
        return view('livewire.auth.reset-password');
    }
}
