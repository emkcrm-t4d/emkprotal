<?php

declare(strict_types=1);

namespace App\Domain\Leads\Services;

use App\Domain\Leads\Enums\VerificationStatus;
use App\Domain\Leads\Enums\VerificationStream;
use App\Domain\Leads\Exceptions\UnauthorizedVerificationException;
use App\Domain\Leads\Exceptions\UnmetConversionInvariantException;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadVerification;
use App\Domain\Payments\DTOs\PaymentVerificationRequest;
use App\Domain\Payments\Services\PaymentManager;
use App\Models\User;

class VerificationService
{
    public function __construct(
        protected PaymentManager $paymentManager
    ) {}

    /**
     * Verify payment stream for a lead (Managerial authority required).
     */
    public function verifyPayment(Lead $lead, User $reviewer, array $paymentData): LeadVerification
    {
        $this->assertHasPermission($reviewer, 'leads.verify_payment');

        $decision = $paymentData['decision'] ?? 'verified';
        $notes = $paymentData['notes'] ?? $paymentData['payment_verification_notes'] ?? null;
        $txid = (string) ($paymentData['bkash_txid'] ?? $paymentData['payment_reference'] ?? $lead->bkash_txid);
        $amount = (float) ($paymentData['amount_bdt'] ?? $paymentData['amount_received_bdt'] ?? 1000.0);

        if ($decision === 'rejected') {
            $reasonCode = $paymentData['rejection_reason_code'] ?? 'INVALID_TXID';
            $evidencePayload = [
                'stream' => VerificationStream::PAYMENT->value,
                'status' => VerificationStatus::REJECTED->value,
                'verified_by_user_id' => $reviewer->id,
                'verified_at' => now()->toIso8601String(),
                'bkash_txid' => $txid,
                'amount_received_bdt' => $amount,
                'expected_amount_bdt' => $amount,
                'payment_method' => 'bkash_manual',
                'merchant_statement_date' => now()->toDateString(),
                'payment_verification_notes' => $notes,
                'rejection_reason_code' => $reasonCode,
                'rejection_notes' => $notes,
            ];

            $verification = LeadVerification::create([
                'lead_id' => $lead->id,
                'stream' => VerificationStream::PAYMENT->value,
                'status' => VerificationStatus::REJECTED->value,
                'verified_by_user_id' => $reviewer->id,
                'verified_at' => now(),
                'evidence_payload' => $evidencePayload,
                'rejection_reason_code' => $reasonCode,
                'notes' => $notes,
            ]);

            $lead->payment_verification_status = VerificationStatus::REJECTED->value;
        } else {
            // Verify via Payment Adapter
            $adapter = $this->paymentManager->driver();
            $request = new PaymentVerificationRequest(
                leadId: $lead->id,
                paymentReference: $txid,
                amountBdt: $amount,
                reviewerUserId: $reviewer->id,
                notes: $notes
            );

            $result = $adapter->verifyPayment($request);

            if ($result->isVerified) {
                $verification = LeadVerification::create([
                    'lead_id' => $lead->id,
                    'stream' => VerificationStream::PAYMENT->value,
                    'status' => VerificationStatus::VERIFIED->value,
                    'verified_by_user_id' => $reviewer->id,
                    'verified_at' => now(),
                    'evidence_payload' => $result->evidencePayload,
                    'rejection_reason_code' => null,
                    'notes' => $notes,
                ]);

                $lead->bkash_txid = $result->reference;
                $lead->payment_verification_status = VerificationStatus::VERIFIED->value;
            } else {
                $verification = LeadVerification::create([
                    'lead_id' => $lead->id,
                    'stream' => VerificationStream::PAYMENT->value,
                    'status' => VerificationStatus::REJECTED->value,
                    'verified_by_user_id' => $reviewer->id,
                    'verified_at' => now(),
                    'evidence_payload' => $result->evidencePayload,
                    'rejection_reason_code' => $result->rejectionReasonCode ?? 'INVALID_TXID',
                    'notes' => $notes ?? $result->errorMessage,
                ]);

                $lead->payment_verification_status = VerificationStatus::REJECTED->value;
            }
        }

        // Transition from submitted to under_review if needed
        if ($lead->status === 'submitted') {
            $lead->status = 'under_review';
        }

        $lead->save();

        return $verification;
    }

    /**
     * Verify document stream for a lead (DEC-09 minor consent enforcement).
     */
    public function verifyDocuments(Lead $lead, User $reviewer, array $docData): LeadVerification
    {
        $this->assertHasPermission($reviewer, 'leads.verify_documents');

        $isMinor = (int) $lead->age_at_submission < 18;
        $under18ConsentVerified = (bool) ($docData['under_18_consent_verified'] ?? false);
        $decision = $docData['decision'] ?? 'verified';
        $idDocType = $docData['id_document_type'] ?? 'national_id';
        $idDocNumber = $docData['id_document_number'] ?? '';
        $nameMatches = (bool) ($docData['name_matches_form'] ?? $docData['name_matches'] ?? false);
        $dobMatches = (bool) ($docData['dob_matches_form'] ?? $docData['dob_matches'] ?? false);
        $notes = $docData['notes'] ?? $docData['document_verification_notes'] ?? null;

        $missingItemsList = (array) ($docData['missing_items_list'] ?? []);

        // DEC-09: Minor consent invariant
        if ($isMinor && !$under18ConsentVerified) {
            $status = VerificationStatus::MISSING_ITEMS->value;
            $rejectionReasonCode = 'MISSING_GUARDIAN_CONSENT';
            if (!in_array('MISSING_GUARDIAN_CONSENT', $missingItemsList, true)) {
                $missingItemsList[] = 'MISSING_GUARDIAN_CONSENT';
            }
        } elseif ($decision === 'rejected') {
            $status = VerificationStatus::REJECTED->value;
            $rejectionReasonCode = $docData['rejection_reason_code'] ?? 'DOCUMENT_UNREADABLE';
        } elseif ($decision === 'missing_items') {
            $status = VerificationStatus::MISSING_ITEMS->value;
            $rejectionReasonCode = $docData['rejection_reason_code'] ?? 'DOCUMENT_UNREADABLE';
        } else {
            $status = VerificationStatus::VERIFIED->value;
            $rejectionReasonCode = null;
        }

        $evidencePayload = [
            'stream' => VerificationStream::DOCUMENT->value,
            'status' => $status,
            'verified_by_user_id' => $reviewer->id,
            'verified_at' => now()->toIso8601String(),
            'id_document_type' => $idDocType,
            'id_document_number' => $idDocNumber,
            'name_matches_form' => $nameMatches,
            'dob_matches_form' => $dobMatches,
            'under_18_consent_required' => $isMinor,
            'under_18_consent_verified' => $under18ConsentVerified,
            'guardian_name' => $docData['guardian_name'] ?? null,
            'guardian_contact_phone' => $docData['guardian_contact_phone'] ?? null,
            'document_verification_notes' => $notes,
            'missing_items_list' => $missingItemsList,
            'rejection_reason_code' => $rejectionReasonCode,
        ];

        $verification = LeadVerification::create([
            'lead_id' => $lead->id,
            'stream' => VerificationStream::DOCUMENT->value,
            'status' => $status,
            'verified_by_user_id' => $reviewer->id,
            'verified_at' => now(),
            'evidence_payload' => $evidencePayload,
            'rejection_reason_code' => $rejectionReasonCode,
            'notes' => $notes,
        ]);

        $lead->document_verification_status = $status;

        if ($status === VerificationStatus::MISSING_ITEMS->value) {
            $lead->status = 'missing_items';
        } elseif ($lead->status === 'submitted') {
            $lead->status = 'under_review';
        }

        $lead->save();

        return $verification;
    }

    /**
     * Verify photo stream for a lead (5-point studio checklist evaluation).
     */
    public function verifyPhoto(Lead $lead, User $reviewer, array $photoData): LeadVerification
    {
        $this->assertHasPermission($reviewer, 'leads.verify_photo');

        $checklist = [
            'white_background' => (bool) ($photoData['white_background'] ?? $photoData['check_white_background'] ?? false),
            'no_glasses'       => (bool) ($photoData['no_glasses'] ?? $photoData['check_no_glasses'] ?? false),
            'ears_visible'     => (bool) ($photoData['ears_visible'] ?? $photoData['check_ears_visible'] ?? false),
            'regular_attire'   => (bool) ($photoData['regular_attire'] ?? $photoData['check_regular_attire'] ?? false),
            'no_selfie'        => (bool) ($photoData['no_selfie'] ?? $photoData['check_no_selfie'] ?? false),
        ];

        $allPassed = !in_array(false, $checklist, true);
        $decision = $photoData['decision'] ?? 'verified';
        $notes = $photoData['notes'] ?? $photoData['photo_verification_notes'] ?? null;

        if ($decision === 'rejected') {
            $status = VerificationStatus::REJECTED->value;
            $rejectionReasonCode = $photoData['rejection_reason_code'] ?? 'NON_COMPLIANT_PHOTO';
        } elseif ($allPassed && $decision === 'verified') {
            $status = VerificationStatus::VERIFIED->value;
            $rejectionReasonCode = null;
        } else {
            $status = VerificationStatus::REUPLOAD_REQUESTED->value;
            $rejectionReasonCode = $photoData['rejection_reason_code'] ?? 'NON_COMPLIANT_PHOTO';
        }

        $evidencePayload = [
            'stream' => VerificationStream::PHOTO->value,
            'status' => $status,
            'verified_by_user_id' => $reviewer->id,
            'verified_at' => now()->toIso8601String(),
            'compliance_checklist' => $checklist,
            'dimensions' => $photoData['dimensions'] ?? ['width_px' => 600, 'height_px' => 600],
            'photo_verification_notes' => $notes,
            'reupload_reason' => $status === VerificationStatus::REUPLOAD_REQUESTED->value
                ? ($notes ?? 'Photo does not meet mandatory 5-point studio compliance.')
                : null,
            'rejection_reason_code' => $rejectionReasonCode,
        ];

        $verification = LeadVerification::create([
            'lead_id' => $lead->id,
            'stream' => VerificationStream::PHOTO->value,
            'status' => $status,
            'verified_by_user_id' => $reviewer->id,
            'verified_at' => now(),
            'evidence_payload' => $evidencePayload,
            'rejection_reason_code' => $rejectionReasonCode,
            'notes' => $notes,
        ]);

        $lead->photo_verification_status = $status;

        if ($lead->status === 'submitted') {
            $lead->status = 'under_review';
        }

        $lead->save();

        return $verification;
    }

    /**
     * Check if lead is ready for Manager approval.
     * All three streams must be 'verified'.
     */
    public function canApproveLead(Lead $lead): bool
    {
        return $lead->payment_verification_status === VerificationStatus::VERIFIED->value
            && $lead->document_verification_status === VerificationStatus::VERIFIED->value
            && $lead->photo_verification_status === VerificationStatus::VERIFIED->value;
    }

    /**
     * Approve lead for induction (Managerial authority required).
     */
    public function approveLead(Lead $lead, User $manager, ?string $notes = null): Lead
    {
        $this->assertHasPermission($manager, 'leads.approve');

        if (!$this->canApproveLead($lead)) {
            throw new UnmetConversionInvariantException(
                "Cannot approve Lead #{$lead->id}: All three verification streams (Payment, Document, Photo) must be verified first."
            );
        }

        $lead->status = 'approved';
        $lead->save();

        return $lead;
    }

    /**
     * Assert user has required permission or elevated role.
     */
    protected function assertHasPermission(User $user, string $permission): void
    {
        if (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin'])) {
            return;
        }

        if ($user->can($permission)) {
            return;
        }

        throw new UnauthorizedVerificationException(
            "User lacks required authority: {$permission}."
        );
    }
}
