<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Domain\Members\Models\Member;
use App\Domain\Members\Services\MemberStatusService;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class MemberStatusModal extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';
    public Member $member;
    public bool $isOpen = false;

    public string $targetStatus = 'restricted';
    public string $directiveRef = '';
    public string $rationale = '';

    public function mount(string $workspace, Member $member): void
    {
        $this->workspace = $workspace;
        $this->member = $member;
    }

    public function hydrate(): void
    {
        $user = Auth::user();
        if ($user && method_exists($user, 'hasRole') && !$user->hasRole(['superadmin', 'global_admin'])) {
            if (!empty($this->workspace) && (string) $user->workspace_id !== (string) $this->workspace) {
                App::abort(403, 'Unauthorized workspace entity.');
            }
        }
    }

    protected function authorizeAccess(): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $isElevated = $user->hasRole(['superadmin', 'global_admin']);

        if (!$isElevated && (string) $user->workspace_id !== (string) $this->workspace) {
            App::abort(403, 'Unauthorized workspace access.');
        }

        if (!$isElevated && !$user->can('members.restrict') && !$user->can('members.revoke')) {
            App::abort(403, 'Unauthorized: members.restrict or members.revoke permission required.');
        }
    }

    #[On('openMemberStatusModal')]
    public function openModal(): void
    {
        $this->authorizeAccess();
        $this->reset(['directiveRef', 'rationale']);
        $this->targetStatus = $this->member->status === 'restricted' ? 'active' : 'restricted';
        $this->resetValidation();
        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->resetValidation();
    }

    public function submit(MemberStatusService $service): void
    {
        $this->authorizeAccess();

        $this->validate([
            'targetStatus' => ['required', 'in:restricted,revoked,active'],
            'directiveRef' => ['required_if:targetStatus,restricted,revoked', 'nullable', 'string', 'max:100'],
            'rationale' => ['required_if:targetStatus,restricted,revoked', 'nullable', 'string', 'min:20', 'max:2000'],
        ]);

        try {
            $service->transitionStatus(
                $this->member,
                $this->targetStatus,
                $this->directiveRef,
                $this->rationale,
                Auth::user()
            );
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            App::abort(403, $e->getMessage());
        } catch (\LogicException $e) {
            $this->addError('targetStatus', $e->getMessage());

            return;
        }

        $this->member->refresh();
        $this->isOpen = false;
        $this->dispatch('member-status-updated');
        session()->flash('status', 'Member status updated to ' . $this->targetStatus . '.');
    }

    public function render(): View
    {
        return view('livewire.members.member-status-modal');
    }
}
