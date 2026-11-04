<?php

declare(strict_types=1);

namespace App\Domain\Leads\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Leads\Exceptions\UnauthorizedVerificationException;
use App\Domain\Leads\Mail\LeadRejectionMail;
use App\Domain\Leads\Mail\MissingItemsNotificationMail;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

class LeadCommunicationService
{
    /**
     * Standardized rejection reason taxonomy per VERIFICATION-DICTIONARY.md Section 7.
     */
    public const REJECTION_TAXONOMY = [
        'INVALID_TXID' => [
            'stream' => 'payment',
            'description' => 'TxID does not exist in bKash network',
            'applicant_message' => 'The provided bKash Transaction ID could not be found in our merchant portal records.',
        ],
        'AMOUNT_MISMATCH' => [
            'stream' => 'payment',
            'description' => 'Paid amount does not match term fee',
            'applicant_message' => 'The deposited amount does not match the required membership fee.',
        ],
        'DUPLICATE_TXID' => [
            'stream' => 'payment',
            'description' => 'TxID already used on another lead/invoice',
            'applicant_message' => 'This bKash Transaction ID has already been recorded for another membership application.',
        ],
        'TRANSACTION_NOT_FOUND' => [
            'stream' => 'payment',
            'description' => 'Merchant record not located within 72 hrs',
            'applicant_message' => 'We could not verify the transaction with bKash within the review period.',
        ],
        'DOCUMENT_UNREADABLE' => [
            'stream' => 'document',
            'description' => 'File blurry, cropped, or illegible',
            'applicant_message' => 'The uploaded identity document is unreadable or blurry. Please provide a clear scan.',
        ],
        'EXPIRED_ID' => [
            'stream' => 'document',
            'description' => 'Identity credential has expired',
            'applicant_message' => 'The submitted identity document has expired. Please provide a valid, current ID.',
        ],
        'NAME_MISMATCH' => [
            'stream' => 'document',
            'description' => 'Name on ID differs from application form',
            'applicant_message' => 'The name on the identity document does not match the name entered in the application form.',
        ],
        'MISSING_GUARDIAN_CONSENT' => [
            'stream' => 'document',
            'description' => 'Minor applicant missing signed consent',
            'applicant_message' => 'Applicants under 18 years of age must upload a signed Parental/Guardian Consent Form.',
        ],
        'NON_COMPLIANT_BACKGROUND' => [
            'stream' => 'photo',
            'description' => 'Background is coloured, patterned, or textured',
            'applicant_message' => 'Photo background must be plain, solid white.',
        ],
        'SELFIE_DETECTED' => [
            'stream' => 'photo',
            'description' => 'Casual selfie, tilted angle, or filter',
            'applicant_message' => 'Selfies and filtered photos cannot be accepted. Please provide a formal studio passport photo.',
        ],
        'GLASSES_OBSCURING_FACE' => [
            'stream' => 'photo',
            'description' => 'Eyeglasses or sunglasses present',
            'applicant_message' => 'Eyeglasses and sunglasses must be removed for membership card photos.',
        ],
        'EARS_COVERED' => [
            'stream' => 'photo',
            'description' => 'Ears obscured by hair or headwear',
            'applicant_message' => 'Both ears must be clearly visible for identity verification.',
        ],
        'LOW_RESOLUTION' => [
            'stream' => 'photo',
            'description' => 'Image resolution below 300x300 or pixelated',
            'applicant_message' => 'The photo resolution is too low for badge printing. Please upload a high-resolution image.',
        ],
    ];

    /**
     * Get all standardized rejection reasons.
     *
     * @return array<string, array{stream: string, description: string, applicant_message: string}>
     */
    public static function getRejectionTaxonomy(): array
    {
        return self::REJECTION_TAXONOMY;
    }

    /**
     * Send official missing items notification email to applicant (LEAD-04).
     *
     * @param array<int, string> $missingItems
     */
    public function sendMissingItemsNotification(
        Lead $lead,
        User $sender,
        array $missingItems,
        ?string $notes = null
    ): void {
        $this->assertHasPermission($sender, 'leads.communicate');

        // 1. Dispatch Queued Mailable
        Mail::to($lead->email)->send(new MissingItemsNotificationMail(
            $lead,
            $missingItems,
            $notes
        ));

        // 2. Transition lead status to missing_items
        $oldStatus = $lead->status;
        $lead->status = 'missing_items';

        // Update relevant stream statuses if unverified
        $itemsJoined = strtolower(implode(' ', $missingItems));
        if ($lead->document_verification_status !== 'verified' && (
            str_contains($itemsJoined, 'document') ||
            str_contains($itemsJoined, 'id') ||
            str_contains($itemsJoined, 'passport') ||
            str_contains($itemsJoined, 'consent') ||
            str_contains($itemsJoined, 'parental')
        )) {
            $lead->document_verification_status = 'missing_items';
        }

        if ($lead->photo_verification_status !== 'verified' && (
            str_contains($itemsJoined, 'photo') ||
            str_contains($itemsJoined, 'background') ||
            str_contains($itemsJoined, 'glasses') ||
            str_contains($itemsJoined, 'ear') ||
            str_contains($itemsJoined, 'selfie')
        )) {
            $lead->photo_verification_status = 'reupload_requested';
        }

        $lead->save();

        // 3. Log immutable audit event
        DB::table('audit_logs')->insert([
            'workspace_id' => $lead->workspace_id,
            'actor_id' => $sender->id,
            'actor_guard' => $sender->guard ?? 'web',
            'event_type' => AuditLog::EVENT_LEAD_MISSING_ITEMS_SENT,
            'auditable_type' => Lead::class,
            'auditable_id' => $lead->id,
            'old_values' => json_encode(['status' => $oldStatus]),
            'new_values' => json_encode([
                'status' => 'missing_items',
                'missing_items' => $missingItems,
                'notes' => $notes,
            ]),
            'reason' => 'Official missing items notification sent to applicant.',
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System',
            'created_at' => now(),
        ]);
    }

    /**
     * Formally reject a lead application using standardized taxonomy (LEAD-07).
     * Requires managerial authority (leads.reject).
     */
    public function rejectLead(
        Lead $lead,
        User $manager,
        string $rejectionReasonCode,
        ?string $internalNotes = null
    ): void {
        $this->assertHasPermission($manager, 'leads.reject');

        if (!isset(self::REJECTION_TAXONOMY[$rejectionReasonCode])) {
            throw new InvalidArgumentException("Invalid rejection reason code: {$rejectionReasonCode}");
        }

        $taxonomyEntry = self::REJECTION_TAXONOMY[$rejectionReasonCode];
        $applicantFacingMessage = $taxonomyEntry['applicant_message'];
        $stream = $taxonomyEntry['stream'];

        // 1. Dispatch Rejection Mailable
        Mail::to($lead->email)->send(new LeadRejectionMail(
            $lead,
            $rejectionReasonCode,
            $applicantFacingMessage
        ));

        // 2. Transition lead status to rejected
        $oldStatus = $lead->status;
        $lead->status = 'rejected';

        // Update corresponding stream status
        if ($stream === 'payment') {
            $lead->payment_verification_status = 'rejected';
        } elseif ($stream === 'document') {
            $lead->document_verification_status = 'rejected';
        } elseif ($stream === 'photo') {
            $lead->photo_verification_status = 'rejected';
        }

        $lead->save();

        // 3. Record verification record in lead_verifications
        LeadVerification::create([
            'lead_id' => $lead->id,
            'stream' => $stream,
            'status' => 'rejected',
            'verified_by_user_id' => $manager->id,
            'verified_at' => now(),
            'evidence_payload' => [
                'stream' => $stream,
                'status' => 'rejected',
                'rejection_reason_code' => $rejectionReasonCode,
                'applicant_message' => $applicantFacingMessage,
                'internal_notes' => $internalNotes,
                'verified_by_user_id' => $manager->id,
                'verified_at' => now()->toIso8601String(),
            ],
            'rejection_reason_code' => $rejectionReasonCode,
            'notes' => $internalNotes,
        ]);

        // 4. Log immutable audit event
        DB::table('audit_logs')->insert([
            'workspace_id' => $lead->workspace_id,
            'actor_id' => $manager->id,
            'actor_guard' => $manager->guard ?? 'web',
            'event_type' => AuditLog::EVENT_LEAD_REJECTED,
            'auditable_type' => Lead::class,
            'auditable_id' => $lead->id,
            'old_values' => json_encode(['status' => $oldStatus]),
            'new_values' => json_encode([
                'status' => 'rejected',
                'rejection_reason_code' => $rejectionReasonCode,
                'internal_notes' => $internalNotes,
            ]),
            'reason' => $internalNotes ?? "Application rejected with code: {$rejectionReasonCode}.",
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System',
            'created_at' => now(),
        ]);
    }

    /**
     * Block an abusive applicant email preventing future submissions (LEAD-07).
     * Requires managerial authority (leads.reject).
     */
    public function blockApplicantEmail(
        Lead $lead,
        User $manager,
        string $justification
    ): void {
        $this->assertHasPermission($manager, 'leads.reject');

        $oldStatus = $lead->status;
        $lead->status = 'blocked';
        $lead->save();

        // Log immutable audit event
        DB::table('audit_logs')->insert([
            'workspace_id' => $lead->workspace_id,
            'actor_id' => $manager->id,
            'actor_guard' => $manager->guard ?? 'web',
            'event_type' => AuditLog::EVENT_LEAD_BLOCKED,
            'auditable_type' => Lead::class,
            'auditable_id' => $lead->id,
            'old_values' => json_encode(['status' => $oldStatus]),
            'new_values' => json_encode([
                'status' => 'blocked',
                'email' => $lead->email,
                'justification' => $justification,
            ]),
            'reason' => $justification,
            'ip_address' => request()->ip() ?? '127.0.0.1',
            'user_agent' => request()->userAgent() ?? 'System',
            'created_at' => now(),
        ]);
    }

    /**
     * Assert user has required permission or elevated role.
     *
     * @throws UnauthorizedVerificationException
     */
    protected function assertHasPermission(User $user, string $permission): void
    {
        if (method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin'])) {
            return;
        }

        if ($permission === 'leads.reject' && method_exists($user, 'hasRole') && $user->hasRole('workspace_manager')) {
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
