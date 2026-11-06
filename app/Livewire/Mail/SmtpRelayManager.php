<?php

declare(strict_types=1);

namespace App\Livewire\Mail;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Mail\Models\SmtpRelay;
use App\Domain\Mail\Services\SmtpTransportManager;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.admin')]
class SmtpRelayManager extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';

    // Modal state
    public bool $showModal = false;
    public bool $showTestModal = false;
    public bool $revealPassword = false;

    // Form fields (matching design specification)
    public ?int $editingRelayId = null;
    public string $name = '';
    public int $priority = 1;
    public string $sender_email = '';
    public string $sender_name = '';
    public string $host = '';
    public int $port = 587;
    public string $encryption = 'starttls';
    public string $username = '';
    public string $password = '';
    public int $throttle_seconds = 0;
    public ?string $reply_to = null;
    public bool $is_active = true;
    public bool $is_global = false;

    // Test connection modal state
    public ?int $testingRelayId = null;
    public string $testRecipientEmail = '';
    public bool $isTesting = false;
    public ?array $testResult = null;

    public function mount(string $workspace): void
    {
        $this->workspace = $workspace;
        $this->authorizeAccess();

        $user = Auth::user();
        if ($user && !empty($user->email)) {
            $this->testRecipientEmail = (string) $user->email;
        }
    }

    protected function authorizeAccess(): void
    {
        $user = Auth::user();
        if (! $user) {
            App::abort(401, 'Unauthenticated.');
        }

        $canManage = $user->can('platform_services.manage_settings')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager']));

        if (! $canManage) {
            App::abort(403, 'Unauthorized: platform_services.manage_settings permission required.');
        }

        if (! $user->hasRole(['superadmin', 'global_admin']) && (string) $this->workspace !== (string) $user->workspace_id) {
            App::abort(403, 'Unauthorized workspace access.');
        }
    }

    public function openCreateModal(): void
    {
        $this->authorizeAccess();
        $this->resetValidation();

        $this->editingRelayId = null;
        $this->name = '';
        $this->priority = $this->suggestNextPriority();
        $this->sender_email = 'noreply@emkcenter.org';
        $this->sender_name = 'EMK Center';
        $this->host = 'smtp-relay.brevo.com';
        $this->port = 587;
        $this->encryption = 'starttls';
        $this->username = '';
        $this->password = '';
        $this->throttle_seconds = 0;
        $this->reply_to = null;
        $this->is_active = true;
        $this->is_global = false;
        $this->revealPassword = false;

        $this->showModal = true;
    }

    public function editRelay(int $relayId): void
    {
        $this->authorizeAccess();
        $this->resetValidation();

        $relay = SmtpRelay::query()
            ->forWorkspace($this->workspace, true)
            ->findOrFail($relayId);

        $this->editingRelayId = $relay->id;
        $this->name = (string) $relay->name;
        $this->priority = (int) $relay->priority;
        $this->sender_email = (string) $relay->sender_email;
        $this->sender_name = (string) $relay->sender_name;
        $this->host = (string) $relay->host;
        $this->port = (int) $relay->port;
        $this->encryption = (string) $relay->encryption;
        $this->username = (string) $relay->username;
        $this->password = (string) $relay->password;
        $this->throttle_seconds = (int) $relay->throttle_seconds;
        $this->reply_to = $relay->reply_to;
        $this->is_active = (bool) $relay->is_active;
        $this->is_global = empty($relay->workspace_id);
        $this->revealPassword = false;

        $this->showModal = true;
    }

    public function saveRelay(SmtpTransportManager $transportManager): void
    {
        $this->authorizeAccess();

        $this->validate([
            'name' => 'required|string|max:120',
            'priority' => 'required|integer|min:1|max:999',
            'sender_email' => 'required|email|max:190',
            'sender_name' => 'required|string|max:120',
            'host' => 'required|string|max:190',
            'port' => 'required|integer|min:1|max:65535',
            'encryption' => 'required|in:starttls,ssl,none',
            'username' => 'required|string|max:190',
            'password' => $this->editingRelayId ? 'nullable|string|max:255' : 'required|string|max:255',
            'throttle_seconds' => 'required|integer|min:0|max:3600',
            'reply_to' => 'nullable|email|max:190',
            'is_active' => 'boolean',
        ]);

        $user = Auth::user();
        $targetWorkspaceId = ($this->is_global && method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']))
            ? null
            : $this->workspace;

        $data = [
            'workspace_id' => $targetWorkspaceId,
            'name' => $this->name,
            'priority' => $this->priority,
            'sender_email' => $this->sender_email,
            'sender_name' => $this->sender_name,
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'username' => $this->username,
            'throttle_seconds' => $this->throttle_seconds,
            'reply_to' => $this->reply_to ?: null,
            'is_active' => $this->is_active,
        ];

        if (!empty($this->password)) {
            $data['password'] = $this->password;
        }

        if ($this->editingRelayId) {
            $relay = SmtpRelay::query()->forWorkspace($this->workspace, true)->findOrFail($this->editingRelayId);
            $relay->update($data);
            $actionLabel = 'Updated';
        } else {
            $relay = SmtpRelay::create($data);
            $actionLabel = 'Created';
        }

        // Audit log
        AuditLog::create([
            'workspace_id' => $targetWorkspaceId,
            'actor_id' => $user?->id,
            'actor_guard' => 'web',
            'event_type' => 'smtp_relay.' . strtolower($actionLabel),
            'auditable_type' => SmtpRelay::class,
            'auditable_id' => $relay->id,
            'old_values' => null,
            'new_values' => [
                'name' => $relay->name,
                'host' => $relay->host,
                'port' => $relay->port,
                'priority' => $relay->priority,
                'is_active' => $relay->is_active,
            ],
            'reason' => "{$actionLabel} SMTP relay configuration '{$relay->name}'.",
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'Livewire/SmtpRelayManager',
        ]);

        // Run non-blocking connection verification check
        $testResult = $transportManager->testRelay($relay);

        $this->showModal = false;

        if ($testResult['success']) {
            session()->flash('status', "SMTP relay '{$relay->name}' {$actionLabel} and verified successfully ({$testResult['latency_ms']}ms).");
        } else {
            session()->flash('warning', "SMTP relay saved, but connection handshake failed: {$testResult['message']}");
        }
    }

    public function toggleActive(int $relayId): void
    {
        $this->authorizeAccess();

        $relay = SmtpRelay::query()->forWorkspace($this->workspace, true)->findOrFail($relayId);
        $relay->update([
            'is_active' => ! $relay->is_active,
        ]);

        session()->flash('status', "SMTP relay '{$relay->name}' is now " . ($relay->is_active ? 'Active' : 'Inactive') . '.');
    }

    public function deleteRelay(int $relayId): void
    {
        $this->authorizeAccess();

        $relay = SmtpRelay::query()->forWorkspace($this->workspace, true)->findOrFail($relayId);
        $name = $relay->name;
        $relay->delete();

        session()->flash('status', "SMTP relay '{$name}' was deleted successfully.");
    }

    public function openTestModal(int $relayId): void
    {
        $this->authorizeAccess();

        $relay = SmtpRelay::query()->forWorkspace($this->workspace, true)->findOrFail($relayId);
        $this->testingRelayId = $relay->id;
        $this->testResult = null;
        $this->isTesting = false;

        $user = Auth::user();
        if (empty($this->testRecipientEmail) && $user && !empty($user->email)) {
            $this->testRecipientEmail = (string) $user->email;
        }

        $this->showTestModal = true;
    }

    public function runTestConnection(SmtpTransportManager $transportManager): void
    {
        $this->authorizeAccess();

        if (!$this->testingRelayId) {
            return;
        }

        $this->validate([
            'testRecipientEmail' => 'nullable|email|max:190',
        ]);

        $relay = SmtpRelay::query()->forWorkspace($this->workspace, true)->findOrFail($this->testingRelayId);

        $this->isTesting = true;
        $this->testResult = $transportManager->testRelay($relay, $this->testRecipientEmail ?: null);
        $this->isTesting = false;
    }

    public function checkAllRelays(SmtpTransportManager $transportManager): void
    {
        $this->authorizeAccess();

        $relays = SmtpRelay::query()->forWorkspace($this->workspace, true)->active()->get();
        $testedCount = 0;
        $errorCount = 0;

        foreach ($relays as $relay) {
            $res = $transportManager->testRelay($relay);
            $testedCount++;
            if (!$res['success']) {
                $errorCount++;
            }
        }

        if ($errorCount > 0) {
            session()->flash('error', "Health check completed: {$errorCount} of {$testedCount} SMTP relays encountered connection errors.");
        } else {
            session()->flash('status', "All {$testedCount} active SMTP relays passed connection verification.");
        }
    }

    public function closeModal(): void
    {
        $this->showModal = false;
    }

    public function closeTestModal(): void
    {
        $this->showTestModal = false;
        $this->testResult = null;
    }

    protected function suggestNextPriority(): int
    {
        $max = SmtpRelay::query()->forWorkspace($this->workspace, true)->max('priority');
        return ($max !== null) ? ((int) $max + 1) : 1;
    }

    public function render(): View
    {
        $relays = SmtpRelay::query()
            ->forWorkspace($this->workspace, true)
            ->orderedByPriority()
            ->get();

        $brokenRelay = $relays->first(fn ($r) => $r->isBroken());
        $activeRelay = $relays->first(fn ($r) => $r->is_active && $r->isConnected());

        return view('livewire.mail.smtp-relay-manager', [
            'workspaceId' => $this->workspace,
            'relays' => $relays,
            'brokenRelay' => $brokenRelay,
            'activeRelay' => $activeRelay,
        ]);
    }
}

