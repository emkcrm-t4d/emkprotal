<?php

declare(strict_types=1);

namespace App\Livewire\Leads\Partials;

use App\Domain\Leads\Enums\VerificationStatus;
use App\Domain\Leads\Exceptions\UnauthorizedVerificationException;
use App\Domain\Leads\Exceptions\UnmetConversionInvariantException;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Services\VerificationService;
use App\Domain\Payments\Adapters\ManualBkashPaymentAdapter;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class VerificationPanel extends Component
{
    use ValidatesWorkspaceOnHydration;

    public string $workspace = '';
    public Lead $lead;

    // Active tab: 'payment', 'document', 'photo'
    public string $activeTab = 'payment';

    // Stream A: Payment tab state
    public string $bkashTxid = '';
    public float $amountReceived = 1000.0;
    public string $paymentNotes = '';
    public bool $duplicateDetected = false;
    public ?array $duplicateDetails = null;
    public string $paymentRejectionReason = 'INVALID_TXID';

    // Stream B: Document tab state
    public string $idDocumentType = 'national_id';
    public string $idDocumentNumber = '';
    public bool $nameMatches = false;
    public bool $dobMatches = false;
    public bool $under18ConsentVerified = false;
    public string $guardianName = '';
    public string $guardianContactPhone = '';
    public string $docNotes = '';
    public string $docRejectionReason = 'DOCUMENT_UNREADABLE';

    // Stream C: Photo tab state
    public bool $checkWhiteBackground = false;
    public bool $checkNoGlasses = false;
    public bool $checkEarsVisible = false;
    public bool $checkRegularAttire = false;
    public bool $checkNoSelfie = false;
    public string $photoNotes = '';
    public string $photoRejectionReason = 'NON_COMPLIANT_PHOTO';

    /**
     * Mount component and sync initial state.
     */
    public function mount(Lead|int|string $lead, string $workspace): void
    {
        $this->workspace = $workspace;

        if (!($lead instanceof Lead)) {
            $lead = Lead::findOrFail($lead);
        }

        $lead->loadMissing(['attachments', 'verifications.verifiedBy']);
        $this->lead = $lead;

        $this->syncState();
    }

    /**
     * Synchronize tab states from lead model and verifications.
     */
    public function syncState(): void
    {
        $this->bkashTxid = (string) $this->lead->bkash_txid;

        // Auto-initialize document details if available
        if ($this->lead->age_at_submission < 18) {
            $this->under18ConsentVerified = (bool) $this->lead->has_parental_consent;
        }

        // Evaluate latest photo verification checklist if available
        $latestPhoto = $this->lead->verifications()
            ->where('stream', 'photo')
            ->latest()
            ->first();

        if ($latestPhoto && !empty($latestPhoto->evidence_payload['compliance_checklist'])) {
            $cl = $latestPhoto->evidence_payload['compliance_checklist'];
            $this->checkWhiteBackground = (bool) ($cl['white_background'] ?? false);
            $this->checkNoGlasses = (bool) ($cl['no_glasses'] ?? false);
            $this->checkEarsVisible = (bool) ($cl['ears_visible'] ?? false);
            $this->checkRegularAttire = (bool) ($cl['regular_attire'] ?? false);
            $this->checkNoSelfie = (bool) ($cl['no_selfie'] ?? false);
        }

        $this->checkPaymentDuplicate();
    }

    /**
     * Check if entered TxID collides with another lead or invoice.
     */
    public function updatedBkashTxid(): void
    {
        $this->checkPaymentDuplicate();
    }

    /**
     * Run duplicate TxID check using ManualBkashPaymentAdapter.
     */
    public function checkPaymentDuplicate(): void
    {
        if (empty(trim($this->bkashTxid))) {
            $this->duplicateDetected = false;
            $this->duplicateDetails = null;
            return;
        }

        $adapter = app(ManualBkashPaymentAdapter::class);
        $collision = $adapter->findDuplicate($this->bkashTxid, $this->lead->id);

        $this->duplicateDetected = ($collision !== null);
        $this->duplicateDetails = $collision;
    }

    /**
     * Switch current verification stream tab.
     */
    public function setActiveTab(string $tab): void
    {
        if (in_array($tab, ['payment', 'document', 'photo'], true)) {
            $this->activeTab = $tab;
        }
    }

    /**
     * Submit Payment Verification review (Workspace Manager authority required).
     */
    public function submitPaymentVerification(string $decision, VerificationService $service): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $canVerify = $user->can('leads.verify_payment')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager']));

        if (!$canVerify) {
            App::abort(403, 'Unauthorized: Workspace Manager authority (leads.verify_payment) required.');
        }

        $this->validate([
            'bkashTxid' => ['required', 'string', 'min:8', 'max:12'],
            'amountReceived' => ['required', 'numeric', 'min:0'],
            'paymentNotes' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->verifyPayment($this->lead, $user, [
                'decision' => $decision,
                'bkash_txid' => $this->bkashTxid,
                'amount_bdt' => $this->amountReceived,
                'rejection_reason_code' => $this->paymentRejectionReason,
                'notes' => $this->paymentNotes,
            ]);

            $this->lead->refresh();
            $this->checkPaymentDuplicate();
            $this->dispatch('lead-updated');

            session()->flash('status', "Payment stream marked as {$decision}.");
        } catch (UnauthorizedVerificationException $e) {
            App::abort(403, $e->getMessage());
        }
    }

    /**
     * Submit Document & Minor Consent verification review.
     */
    public function submitDocumentVerification(string $decision, VerificationService $service): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $canVerify = $user->can('leads.verify_documents')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager', 'delegated_staff']));

        if (!$canVerify) {
            App::abort(403, 'Unauthorized: leads.verify_documents permission required.');
        }

        try {
            $service->verifyDocuments($this->lead, $user, [
                'decision' => $decision,
                'id_document_type' => $this->idDocumentType,
                'id_document_number' => $this->idDocumentNumber,
                'name_matches_form' => $this->nameMatches,
                'dob_matches_form' => $this->dobMatches,
                'under_18_consent_verified' => $this->under18ConsentVerified,
                'guardian_name' => $this->guardianName,
                'guardian_contact_phone' => $this->guardianContactPhone,
                'rejection_reason_code' => $this->docRejectionReason,
                'notes' => $this->docNotes,
            ]);

            $this->lead->refresh();
            $this->dispatch('lead-updated');

            session()->flash('status', "Document stream marked as {$this->lead->document_verification_status}.");
        } catch (UnauthorizedVerificationException $e) {
            App::abort(403, $e->getMessage());
        }
    }

    /**
     * Submit Studio Photo review based on 5-point checklist.
     */
    public function submitPhotoVerification(string $decision, VerificationService $service): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        $canVerify = $user->can('leads.verify_photo')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager', 'delegated_staff']));

        if (!$canVerify) {
            App::abort(403, 'Unauthorized: leads.verify_photo permission required.');
        }

        try {
            $service->verifyPhoto($this->lead, $user, [
                'decision' => $decision,
                'white_background' => $this->checkWhiteBackground,
                'no_glasses' => $this->checkNoGlasses,
                'ears_visible' => $this->checkEarsVisible,
                'regular_attire' => $this->checkRegularAttire,
                'no_selfie' => $this->checkNoSelfie,
                'rejection_reason_code' => $this->photoRejectionReason,
                'notes' => $this->photoNotes,
            ]);

            $this->lead->refresh();
            $this->dispatch('lead-updated');

            session()->flash('status', "Photo stream marked as {$this->lead->photo_verification_status}.");
        } catch (UnauthorizedVerificationException $e) {
            App::abort(403, $e->getMessage());
        }
    }

    /**
     * Formally approve lead for induction (Managerial authority required).
     */
    public function approveLead(VerificationService $service): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated');
        }

        try {
            $service->approveLead($this->lead, $user);

            $this->lead->refresh();
            $this->dispatch('lead-updated');

            session()->flash('status', 'Lead successfully approved for induction! Tri-State verifications verified.');
        } catch (UnauthorizedVerificationException $e) {
            App::abort(403, $e->getMessage());
        } catch (UnmetConversionInvariantException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * Check if all 5 photo checklist items are satisfied.
     */
    public function getAreAllPhotoCriteriaMetProperty(): bool
    {
        return $this->checkWhiteBackground
            && $this->checkNoGlasses
            && $this->checkEarsVisible
            && $this->checkRegularAttire
            && $this->checkNoSelfie;
    }

    /**
     * Determine if current user can verify payment.
     */
    public function getCanVerifyPaymentProperty(): bool
    {
        $user = Auth::user();
        if (!$user) return false;
        return $user->can('leads.verify_payment')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager']));
    }

    /**
     * Determine if current user can approve lead.
     */
    public function getCanApproveLeadProperty(): bool
    {
        $user = Auth::user();
        if (!$user) return false;

        $hasRoleOrPerm = $user->can('leads.approve')
            || (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin', 'workspace_manager']));

        return $hasRoleOrPerm && app(VerificationService::class)->canApproveLead($this->lead);
    }

    /**
     * Render the verification panel view.
     */
    public function render(): View
    {
        $identityAttachment = $this->lead->attachments->firstWhere('document_type', 'identity_document');
        $photoAttachment = $this->lead->attachments->firstWhere('document_type', 'studio_photo');
        $consentAttachment = $this->lead->attachments->firstWhere('document_type', 'parental_consent');

        return view('livewire.leads.partials.verification-panel', [
            'lead' => $this->lead,
            'identityAttachment' => $identityAttachment,
            'photoAttachment' => $photoAttachment,
            'consentAttachment' => $consentAttachment,
            'canApprove' => $this->canApproveLead,
            'canVerifyPayment' => $this->canVerifyPayment,
            'allPhotoCriteriaMet' => $this->areAllPhotoCriteriaMet,
        ]);
    }
}
