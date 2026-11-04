<?php

declare(strict_types=1);

namespace App\Livewire\Mail;

use App\Domain\Leads\Models\Lead;
use App\Domain\Mail\Models\EmailThread;
use App\Domain\Mail\Services\MailboxSyncService;
use App\Domain\Members\Models\Member;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

class EmailThreadPanel extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';
    public ?int $leadId = null;
    public ?int $memberId = null;
    public ?int $selectedThreadId = null;

    public string $replySubject = '';
    public string $replyBody = '';

    public string $newThreadSubject = '';
    public string $newThreadBody = '';
    public bool $showNewThreadForm = false;

    protected ?int $linkedLeadIdFromMember = null;
    public string $counterpartyEmail = '';
    public string $counterpartyName = '';

    /**
     * Mount the EmailThreadPanel component asserting workspace tenancy and permissions.
     */
    public function mount(string $workspace, ?int $leadId = null, ?int $memberId = null): void
    {
        $this->workspace = $workspace;
        $this->leadId = $leadId;
        $this->memberId = $memberId;

        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);

        if (!$isElevatedAdmin && (string) $user->workspace_id !== (string) $this->workspace) {
            App::abort(403, 'Unauthorized workspace access.');
        }

        $canView = $isElevatedAdmin
            || (method_exists($user, 'hasRole') && $user->hasRole(['workspace_manager']))
            || $user->can('leads.view')
            || $user->can('members.view');

        if (!$canView) {
            App::abort(403, 'Forbidden: leads.view or members.view permission required.');
        }

        if ($this->leadId !== null) {
            $lead = Lead::where('id', $this->leadId)->first();
            if ($lead === null || (string) $lead->workspace_id !== (string) $this->workspace) {
                App::abort(403, 'Lead does not belong to the requested workspace.');
            }
            $this->counterpartyEmail = (string) $lead->email;
            $this->counterpartyName = (string) $lead->full_name;
        }

        if ($this->memberId !== null) {
            $member = Member::where('id', $this->memberId)->first();
            if ($member === null || (string) $member->workspace_id !== (string) $this->workspace) {
                App::abort(403, 'Member does not belong to the requested workspace.');
            }
            $this->linkedLeadIdFromMember = $member->lead_id !== null ? (int) $member->lead_id : null;
            $this->counterpartyEmail = (string) $member->email;
            $this->counterpartyName = (string) $member->full_name;
        }

        $threads = $this->threads;
        if ($threads->isNotEmpty() && $this->selectedThreadId === null) {
            $this->selectedThreadId = (int) $threads->first()->id;
        }
    }

    /**
     * Hydration guard ensuring workspace context integrity across requests.
     */
    public function hydrate(): void
    {
        $user = Auth::user();
        if ($user && method_exists($user, 'hasRole') && !$user->hasRole(['superadmin', 'global_admin'])) {
            if (!empty($this->workspace) && (string) $user->workspace_id !== (string) $this->workspace) {
                App::abort(403, 'Unauthorized workspace entity.');
            }
        }
    }

    /**
     * Select an email thread to inspect messages and compose a reply.
     */
    public function selectThread(int $threadId): void
    {
        $thread = $this->threads->firstWhere('id', $threadId);
        if ($thread === null) {
            App::abort(403, 'Thread does not belong to this workspace or entity.');
        }

        $this->selectedThreadId = $threadId;
        $this->replySubject = '';
        $this->replyBody = '';
        $this->resetValidation();
    }

    /**
     * Toggle the New Official Email thread composer form.
     */
    public function toggleNewThreadForm(): void
    {
        $this->showNewThreadForm = !$this->showNewThreadForm;
        $this->resetValidation();
    }

    /**
     * Dispatch an official reply on the currently selected thread.
     */
    public function sendReply(MailboxSyncService $syncService): void
    {
        $this->authorizeCommunicationAction();

        $this->validate([
            'replyBody' => ['required', 'string', 'min:5', 'max:10000'],
            'replySubject' => ['nullable', 'string', 'max:255'],
        ]);

        if ($this->selectedThreadId === null) {
            App::abort(404, 'No email thread selected.');
        }

        $thread = EmailThread::where('workspace_id', $this->workspace)
            ->where('id', $this->selectedThreadId)
            ->first();

        if ($thread === null) {
            App::abort(403, 'Unauthorized or missing email thread.');
        }

        /** @var \App\Models\User $sender */
        $sender = Auth::user();

        $syncService->sendAndRecordReply(
            $thread,
            $sender,
            $this->replyBody,
            $this->replySubject !== '' ? $this->replySubject : null
        );

        $this->replyBody = '';
        $this->replySubject = '';
        unset($this->threads, $this->selectedThread);

        session()->flash('status', 'Official reply sent and recorded in thread.');
        $this->dispatch('email-reply-sent', threadId: $thread->id);
    }

    /**
     * Start a new official email thread for the current Lead or Member.
     */
    public function startNewThread(MailboxSyncService $syncService): void
    {
        $this->authorizeCommunicationAction();

        $this->validate([
            'newThreadSubject' => ['required', 'string', 'min:3', 'max:200'],
            'newThreadBody' => ['required', 'string', 'min:5', 'max:10000'],
        ]);

        $leadId = $this->leadId;
        $memberId = $this->memberId;
        $participantEmail = $this->counterpartyEmail;

        if ($memberId !== null && $leadId === null) {
            $member = Member::where('workspace_id', $this->workspace)->where('id', $memberId)->first();
            if ($member !== null) {
                $leadId = $member->lead_id;
                $participantEmail = (string) $member->email;
            }
        } elseif ($leadId !== null && $participantEmail === '') {
            $lead = Lead::where('workspace_id', $this->workspace)->where('id', $leadId)->first();
            if ($lead !== null) {
                $participantEmail = (string) $lead->email;
            }
        }

        if ($participantEmail === '') {
            App::abort(422, 'Recipient email address is unavailable.');
        }

        $thread = EmailThread::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspace,
            'lead_id' => $leadId,
            'member_id' => $memberId,
            'participant_email' => strtolower($participantEmail),
            'subject' => trim($this->newThreadSubject),
            'last_message_at' => now(),
            'message_count' => 0,
            'is_resolved' => false,
        ]);

        /** @var \App\Models\User $sender */
        $sender = Auth::user();

        $syncService->sendAndRecordReply(
            $thread,
            $sender,
            $this->newThreadBody,
            trim($this->newThreadSubject)
        );

        $this->selectedThreadId = (int) $thread->id;
        $this->newThreadSubject = '';
        $this->newThreadBody = '';
        $this->showNewThreadForm = false;
        unset($this->threads, $this->selectedThread);

        session()->flash('status', 'New official email thread started and dispatched.');
        $this->dispatch('email-thread-started', threadId: $thread->id);
    }

    /**
     * Trigger an immediate workspace mailbox synchronization.
     */
    public function syncNow(MailboxSyncService $syncService): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);
        $isManager = method_exists($user, 'hasRole') && $user->hasRole(['workspace_manager']);

        if (!$isElevatedAdmin && (string) $user->workspace_id !== (string) $this->workspace) {
            App::abort(403, 'Unauthorized workspace access.');
        }

        $canSync = $isElevatedAdmin
            || $isManager
            || $user->can('leads.communicate')
            || $user->can('platform_services.manage_settings');

        if (!$canSync) {
            App::abort(403, 'Forbidden: leads.communicate or platform_services.manage_settings permission required.');
        }

        $summary = $syncService->syncWorkspaceMailbox($this->workspace);
        unset($this->threads, $this->selectedThread);

        if ($this->selectedThreadId === null && $this->threads->isNotEmpty()) {
            $this->selectedThreadId = (int) $this->threads->first()->id;
        }

        session()->flash(
            'status',
            sprintf('Mailbox synced: %d imported, %d duplicate(s) skipped.', $summary['imported'], $summary['skipped_duplicates'])
        );
    }

    /**
     * Retrieve all EmailThread records belonging to the current Lead or Member (including linked Lead).
     *
     * @return Collection<int, EmailThread>
     */
    #[Computed]
    public function threads(): Collection
    {
        $linkedLeadId = $this->linkedLeadIdFromMember;
        if ($linkedLeadId === null && $this->memberId !== null) {
            $member = Member::where('workspace_id', $this->workspace)->where('id', $this->memberId)->first();
            $linkedLeadId = $member?->lead_id !== null ? (int) $member->lead_id : null;
        }

        return EmailThread::with(['messages.sender'])
            ->where('workspace_id', $this->workspace)
            ->where(function ($query) use ($linkedLeadId): void {
                if ($this->memberId !== null && $linkedLeadId !== null) {
                    $query->where('member_id', $this->memberId)
                        ->orWhere('lead_id', $linkedLeadId);
                } elseif ($this->memberId !== null) {
                    $query->where('member_id', $this->memberId);
                } elseif ($this->leadId !== null) {
                    $query->where('lead_id', $this->leadId);
                } else {
                    $query->whereRaw('1 = 0');
                }
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Resolve the currently selected EmailThread.
     */
    #[Computed]
    public function selectedThread(): ?EmailThread
    {
        if ($this->selectedThreadId === null) {
            return $this->threads->first();
        }

        return $this->threads->firstWhere('id', $this->selectedThreadId) ?? $this->threads->first();
    }

    /**
     * Assert that the authenticated staff user is authorized to send official communications.
     */
    protected function authorizeCommunicationAction(): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);
        $isManager = method_exists($user, 'hasRole') && $user->hasRole(['workspace_manager']);

        if (!$isElevatedAdmin && (string) $user->workspace_id !== (string) $this->workspace) {
            App::abort(403, 'Unauthorized workspace access.');
        }

        $canCommunicate = $isElevatedAdmin
            || $isManager
            || $user->can('leads.communicate')
            || $user->can('members.edit');

        if (!$canCommunicate) {
            App::abort(403, 'Forbidden: leads.communicate or members.edit permission required.');
        }
    }

    public function render(): View
    {
        return view('livewire.mail.email-thread-panel');
    }
}
