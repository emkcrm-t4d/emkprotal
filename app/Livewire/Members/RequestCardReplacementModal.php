<?php

declare(strict_types=1);

namespace App\Livewire\Members;

use App\Domain\IdCards\Services\IdCardLifecycleService;
use App\Domain\Invoices\Exceptions\DuplicatePaymentReferenceException;
use App\Domain\Members\Models\Member;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class RequestCardReplacementModal extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';
    public Member $member;
    public bool $isOpen = false;

    public string $replacementReason = '';
    public bool $requiresFee = false;
    public string $bkashTxId = '';

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

        if (!$isElevated && !$user->can('members.manage_cards')) {
            App::abort(403, 'Unauthorized: members.manage_cards permission required.');
        }
    }

    #[On('openCardReplacementModal')]
    public function openModal(): void
    {
        $this->authorizeAccess();
        $this->reset(['replacementReason', 'requiresFee', 'bkashTxId']);
        $this->resetValidation();
        $this->isOpen = true;
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->resetValidation();
    }

    public function updatedBkashTxId(string $value): void
    {
        $this->bkashTxId = \App\Domain\Payments\Adapters\ManualBkashPaymentAdapter::extractTxid($value);
    }

    public function submit(IdCardLifecycleService $service): void
    {
        $this->authorizeAccess();

        $this->bkashTxId = \App\Domain\Payments\Adapters\ManualBkashPaymentAdapter::extractTxid($this->bkashTxId);

        $this->validate([
            'replacementReason' => ['required', 'in:' . implode(',', IdCardLifecycleService::REPLACEMENT_REASONS)],
            'bkashTxId' => $this->requiresFee
                ? ['required', 'regex:/^[A-Z0-9]{10}$/']
                : ['nullable'],
        ], [
            'bkashTxId.regex' => 'bKash Transaction ID must be exactly 10 uppercase alphanumeric characters.',
        ]);

        try {
            $service->replaceCard(
                $this->member,
                $this->replacementReason,
                $this->requiresFee ? $this->bkashTxId : null,
                Auth::user()
            );
        } catch (DuplicatePaymentReferenceException $e) {
            $this->addError('bkashTxId', $e->getMessage());

            return;
        } catch (\InvalidArgumentException $e) {
            $this->addError('replacementReason', $e->getMessage());

            return;
        }

        $this->isOpen = false;
        $this->dispatch('id-card-replaced');
        session()->flash('status', 'Replacement ID card enqueued for PVC printing.');
    }

    public function render(): View
    {
        return view('livewire.members.request-card-replacement-modal');
    }
}
