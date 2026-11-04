<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Domain\Members\Models\Member;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

#[Layout('layouts.admin')]
class MemberProfile extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';
    public Member $member;

    // Active Tab state: 'overview', 'invoices', 'activity_timeline'
    public string $activeTab = 'overview';

    /**
     * Mount component asserting authentication, tenancy isolation, and Spatie permission.
     */
    public function mount(string $workspace, Member|string $member): void
    {
        $this->workspace = $workspace;

        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $isElevatedAdmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);

        // Assert staff belongs to the route workspace
        if (!$isElevatedAdmin && (string) $user->workspace_id !== (string) $this->workspace) {
            App::abort(403, 'Unauthorized workspace access.');
        }

        // Assert members.view permission
        if (!$isElevatedAdmin && !$user->can('members.view')) {
            App::abort(403, 'Forbidden: members.view permission required.');
        }

        // Resolve member instance if UUID string passed
        if (!($member instanceof Member)) {
            $member = Member::where('uuid', $member)->firstOrFail();
        }

        // Tenancy check: reject cross-workspace member
        if (!$isElevatedAdmin && (string) $member->workspace_id !== (string) $user->workspace_id) {
            App::abort(403, 'Unauthorized workspace entity.');
        }

        if ((string) $member->workspace_id !== (string) $this->workspace) {
            App::abort(403, 'Member does not belong to the requested workspace.');
        }

        // Eager load relations
        $member->loadMissing(['lead.attachments', 'term', 'invoices', 'idCards', 'user']);

        $this->member = $member;
    }

    /**
     * Hydration guard ensuring workspace context integrity.
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
     * Switch active profile tab.
     */
    public function setTab(string $tab): void
    {
        $validTabs = ['overview', 'invoices', 'activity_timeline'];
        if (in_array($tab, $validTabs, true)) {
            $this->activeTab = $tab;
        }
    }

    /**
     * Refresh member entity upon contact updates.
     */
    #[On('member-contact-updated')]
    #[On('memberContactUpdated')]
    #[On('member-status-updated')]
    public function refreshMember(): void
    {
        $this->member->refresh();
        $this->member->loadMissing(['lead.attachments', 'term', 'invoices', 'idCards', 'user']);
    }

    /**
     * Open inline contact editing modal.
     */
    public function openEditContactModal(): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $canEdit = $user->can('members.edit')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager']));

        if (!$canEdit) {
            App::abort(403, 'Unauthorized: members.edit permission required.');
        }

        $this->dispatch('openEditMemberContactModal');
    }

    /**
     * Whether the current user may change member status (restrict/revoke authority).
     */
    public function canChangeStatus(): bool
    {
        $user = Auth::user();

        return $user && ($user->can('members.restrict') || $user->can('members.revoke')
            || $user->hasRole(['superadmin', 'global_admin']));
    }

    /**
     * Open member status governance modal.
     */
    public function openStatusModal(): void
    {
        if (!$this->canChangeStatus()) {
            App::abort(403, 'Unauthorized: members.restrict or members.revoke permission required.');
        }

        $this->dispatch('openMemberStatusModal');
    }

    /**
     * Render the member profile view.
     */
    public function render(): View
    {
        // Query audit trail events
        $auditLogs = DB::table('audit_logs')
            ->where(function ($q) {
                $q->where(function ($sq) {
                    $sq->where('auditable_type', Member::class)
                        ->where('auditable_id', $this->member->id);
                });
                if ($this->member->lead_id) {
                    $q->orWhere(function ($sq) {
                        $sq->where('auditable_type', \App\Domain\Leads\Models\Lead::class)
                            ->where('auditable_id', $this->member->lead_id);
                    });
                }
            })
            ->orderByDesc('created_at')
            ->get();

        return view('livewire.members.member-profile', [
            'member' => $this->member,
            'auditLogs' => $auditLogs,
            'activeModule' => 'members',
        ])->layout('layouts.admin', [
            'activeModule' => 'members',
            'title' => 'Member Profile - ' . $this->member->full_name,
        ]);
    }
}
