<?php

declare(strict_types=1);

namespace App\Livewire\Portal;

use App\Domain\IdCards\Models\IdCard;
use App\Domain\Members\Models\Member;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.portal')]
class MemberPortalProfile extends Component
{
    public Member $member;

    public string $activeTab = 'profile';

    /**
     * Allowed active tabs for member portal profile.
     *
     * @var array<string>
     */
    protected array $allowedTabs = ['profile', 'documents', 'communications'];

    /**
     * Mount the member profile, asserting authenticated member session and strictly
     * isolating sensitive internal staff notes, audit logs, and conversion invoices.
     */
    public function mount(): void
    {
        $user = Auth::guard('member')->user();

        if (!$user || !$user->member) {
            abort(403, 'Unauthorized: Authenticated member account required.');
        }

        // Information Disclosure Guard (PORT-02, DEC-06, DEC-11):
        // Eager-load strictly safe relationships. NEVER load audit_logs, lead_verifications,
        // manager_notes, or post-conversion invoices.
        $this->member = $user->member->load([
            'term',
            'idCards' => function ($q) {
                $q->orderByDesc('id');
            },
            'lead.attachments',
        ]);

        // Explicitly purge any internal/sensitive relations if accidentally attached
        $this->member->unsetRelation('invoices');
        if ($this->member->lead) {
            $this->member->lead->unsetRelation('verifications');
            $this->member->lead->unsetRelation('auditLogs');
        }

        // Conceal internal third-party synchronization keys
        $this->member->makeHidden(['zoho_id']);
    }

    /**
     * Switch active profile tab.
     */
    public function setTab(string $tab): void
    {
        if (in_array($tab, $this->allowedTabs, true)) {
            $this->activeTab = $tab;
        }
    }

    /**
     * Accessor for latest physical ID card record.
     */
    public function getLatestIdCardProperty(): ?IdCard
    {
        return $this->member->idCards->first();
    }

    /**
     * Accessor for human-readable ID card status.
     */
    public function getIdCardStatusLabelProperty(): string
    {
        $card = $this->latestIdCard;
        if (!$card) {
            return 'Not Issued';
        }

        return match ($card->status) {
            'provided_for_printing' => 'Queued for Printing',
            'card_printed_ready_for_pickup' => 'Ready for Pickup at Front Desk',
            'delivered' => 'Card Delivered',
            'superseded_replaced' => 'Card Replaced',
            'voided_reversal' => 'Card Voided',
            default => ucfirst(str_replace('_', ' ', (string) $card->status)),
        };
    }

    /**
     * Accessor for ID card status color classes.
     */
    public function getIdCardStatusColorProperty(): string
    {
        $card = $this->latestIdCard;
        if (!$card) {
            return 'bg-slate-100 text-slate-700 border-slate-200';
        }

        return match ($card->status) {
            'provided_for_printing' => 'bg-amber-50 text-amber-800 border-amber-200',
            'card_printed_ready_for_pickup' => 'bg-sky-50 text-sky-800 border-sky-200',
            'delivered' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
            'superseded_replaced' => 'bg-slate-100 text-slate-700 border-slate-200',
            'voided_reversal' => 'bg-rose-50 text-rose-800 border-rose-200',
            default => 'bg-slate-100 text-slate-700 border-slate-200',
        };
    }

    public function render(): View
    {
        return view('livewire.portal.member-portal-profile');
    }
}
