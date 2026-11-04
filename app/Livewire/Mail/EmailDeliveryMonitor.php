<?php

declare(strict_types=1);

namespace App\Livewire\Mail;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Mail\Models\EmailMessage;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use App\Jobs\DispatchOfficialEmailJob;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
class EmailDeliveryMonitor extends Component
{
    use ValidatesWorkspaceOnHydration;
    use WithPagination;

    public string $workspace = '';

    public string $statusFilter = '';

    public string $search = '';

    public function mount(string $workspace): void
    {
        $this->workspace = $workspace;
        $this->authorizeAccess();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Assert managerial authorization and workspace tenancy access per T-08-09.
     */
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

    /**
     * Manually retry a failed or queued email message via Horizon while preventing duplicate sends on already-sent messages.
     */
    public function retryMessage(int $messageId): void
    {
        $this->authorizeAccess();

        $message = EmailMessage::query()
            ->where('workspace_id', $this->workspace)
            ->findOrFail($messageId);

        if ($message->status === 'sent' || $message->status === 'received') {
            session()->flash('status', 'Message was already sent; duplicate retry skipped.');

            return;
        }

        $previousStatus = $message->status;
        $previousFailureReason = $message->failure_reason;

        $message->update([
            'status' => 'queued',
            'failure_reason' => null,
        ]);

        $user = Auth::user();

        AuditLog::create([
            'workspace_id' => $this->workspace,
            'actor_id' => $user?->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_OFFICIAL_EMAIL_RETRIED,
            'auditable_type' => EmailMessage::class,
            'auditable_id' => $message->id,
            'old_values' => [
                'status' => $previousStatus,
                'failure_reason' => $previousFailureReason,
            ],
            'new_values' => [
                'status' => 'queued',
                'to_email' => $message->to_email,
                'template_key' => $message->template_key,
                'deduplication_key' => $message->deduplication_key,
            ],
            'reason' => "Manually re-queued failed official email [{$message->id}] for delivery via Horizon.",
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'Livewire/EmailDeliveryMonitor',
            'created_at' => now(),
        ]);

        DispatchOfficialEmailJob::dispatch($message->id);

        session()->flash('status', 'Failed email re-queued for delivery via Horizon.');
    }

    public function render(): View
    {
        $baseQuery = EmailMessage::query()->where('workspace_id', $this->workspace);

        $sentCount = (clone $baseQuery)->where('status', 'sent')->count();
        $queuedCount = (clone $baseQuery)->where('status', 'queued')->count();
        $failedCount = (clone $baseQuery)->where('status', 'failed')->count();

        $listQuery = EmailMessage::query()->where('workspace_id', $this->workspace);

        if (in_array($this->statusFilter, ['queued', 'sent', 'failed', 'received'], true)) {
            $listQuery->where('status', $this->statusFilter);
        }

        $trimmedSearch = trim($this->search);
        if ($trimmedSearch !== '') {
            $listQuery->where(function ($q) use ($trimmedSearch): void {
                $q->where('to_email', 'like', '%' . $trimmedSearch . '%')
                    ->orWhere('subject', 'like', '%' . $trimmedSearch . '%')
                    ->orWhere('template_key', 'like', '%' . $trimmedSearch . '%');
            });
        }

        $messages = $listQuery
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.mail.email-delivery-monitor', [
            'sentCount' => $sentCount,
            'queuedCount' => $queuedCount,
            'failedCount' => $failedCount,
            'messages' => $messages,
        ]);
    }
}
