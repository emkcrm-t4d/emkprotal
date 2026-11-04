<?php

declare(strict_types=1);

namespace App\Domain\Forms\Services;

use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Leads\Services\DocumentStorageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeadIngestionService
{
    /**
     * Map of public american_space form inputs to target space codes and workspace IDs.
     * Per DEC-01 (D-01) and GAP-P1-01:
     * - emk_center -> ws-emk-dhaka (EMK-DHK)
     * - Other 5 American Spaces -> null workspace (Superadmin staging pool)
     */
    public const SPACE_MAPPING = [
        'emk_center' => [
            'space_code' => 'EMK-DHK',
            'workspace_id' => 'ws-emk-dhaka',
        ],
        'american_center' => [
            'space_code' => 'AC-DHK',
            'workspace_id' => null,
        ],
        'barishal_corner' => [
            'space_code' => 'CR-BAR',
            'workspace_id' => null,
        ],
        'chattogram_corner' => [
            'space_code' => 'CR-CTG',
            'workspace_id' => null,
        ],
        'rajshahi_corner' => [
            'space_code' => 'CR-RAJ',
            'workspace_id' => null,
        ],
        'sylhet_corner' => [
            'space_code' => 'CR-SYL',
            'workspace_id' => null,
        ],
    ];

    public function __construct(
        protected DuplicateSubmissionGuard $duplicateGuard,
        protected DocumentStorageService $documentStorage,
        protected FormValidationCompiler $validationCompiler
    ) {}

    /**
     * Ingest a public membership application submission into verified Lead and LeadAttachment entities.
     *
     * @param  Form  $form  The form being submitted
     * @param  array<string, mixed>  $validatedData  Validated payload containing 31 fields
     * @param  array<string, mixed>  $uploadedFiles  UploadedFile instances for identity, photo, and consent
     * @param  string|null  $ipAddress  Client IP address for audit and tracking
     * @param  string|null  $userAgent  Client browser user agent
     * @return Lead The created Lead entity with attachments
     *
     * @throws ValidationException|\Throwable
     */
    public function ingest(
        Form $form,
        array $validatedData,
        array $uploadedFiles,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): Lead {
        // 1. Enforce unique bKash TxID across leads and invoices per DEC-05 (D-05)
        $this->duplicateGuard->assertUniqueBkashTxid((string) ($validatedData['bkash_txid'] ?? ''));

        // 2. Enforce email uniqueness while allowing reapplication for rejected applicants per DEC-01 & LEAD-07
        $this->duplicateGuard->assertUniqueEmail((string) ($validatedData['email'] ?? ''));

        // 3. Resolve Tenancy and Space Mapping per DEC-01 (D-01) and GAP-P1-01
        $selectedSpace = (string) ($validatedData['american_space'] ?? 'emk_center');
        $mapping = self::SPACE_MAPPING[$selectedSpace] ?? [
            'space_code' => 'EMK-DHK',
            'workspace_id' => 'ws-emk-dhaka',
        ];

        $targetSpaceCode = $mapping['space_code'];
        $workspaceId = $mapping['workspace_id'];

        // 4. Calculate Age and Enforce Youth Consent per DEC-09 (D-09)
        $dob = Carbon::parse($validatedData['date_of_birth']);
        $age = $dob->age;

        // Resolve uploaded file instances
        $identityFiles = $uploadedFiles['identity_document_files']
            ?? $uploadedFiles['identity_documents']
            ?? [];
        if (!is_array($identityFiles)) {
            $identityFiles = [$identityFiles];
        }

        $photoFile = $uploadedFiles['photo_file']
            ?? $uploadedFiles['photo']
            ?? null;

        $consentFile = $uploadedFiles['parental_consent_file']
            ?? $uploadedFiles['parental_consent']
            ?? null;

        if ($age < 18 && empty($consentFile)) {
            throw ValidationException::withMessages([
                'parental_consent' => ['Parental/Guardian consent form is mandatory for applicants under 18 years of age.'],
            ]);
        }

        $leadUuid = Str::uuid()->toString();
        $storedFilePaths = [];
        $storedAttachments = [];

        try {
            // 5a. Persist Identity Documents to Private Storage
            foreach ($identityFiles as $file) {
                if ($file) {
                    $meta = $this->documentStorage->store(
                        $file,
                        $leadUuid,
                        $workspaceId,
                        'identity_document',
                        $age
                    );
                    $storedFilePaths[] = $meta['storage_path'];
                    $storedAttachments[] = [
                        'type' => 'identity_document',
                        'meta' => $meta,
                    ];
                }
            }

            // 5b. Persist Studio Photo to Private Storage
            if ($photoFile) {
                $meta = $this->documentStorage->store(
                    $photoFile,
                    $leadUuid,
                    $workspaceId,
                    'studio_photo',
                    $age
                );
                $storedFilePaths[] = $meta['storage_path'];
                $storedAttachments[] = [
                    'type' => 'studio_photo',
                    'meta' => $meta,
                ];
            }

            // 5c. Persist Parental Consent to Private Storage (if minor)
            if ($age < 18 && $consentFile) {
                $meta = $this->documentStorage->store(
                    $consentFile,
                    $leadUuid,
                    $workspaceId,
                    'parental_consent',
                    $age
                );
                $storedFilePaths[] = $meta['storage_path'];
                $storedAttachments[] = [
                    'type' => 'parental_consent',
                    'meta' => $meta,
                ];
            }

            // 5d. Atomically persist Lead, LeadAttachments, and FormSubmission
            $lead = DB::transaction(function () use (
                $leadUuid,
                $workspaceId,
                $targetSpaceCode,
                $age,
                $validatedData,
                $storedAttachments,
                $form,
                $ipAddress,
                $userAgent
            ): Lead {
                $lead = Lead::create([
                    'uuid' => $leadUuid,
                    'workspace_id' => $workspaceId,
                    'target_space_code' => $targetSpaceCode,
                    'status' => 'submitted',
                    'bkash_txid' => Str::upper(trim((string) $validatedData['bkash_txid'])),
                    'first_name' => (string) $validatedData['first_name'],
                    'middle_name' => !empty($validatedData['middle_name']) ? (string) $validatedData['middle_name'] : null,
                    'last_name' => (string) $validatedData['last_name'],
                    'email' => Str::lower(trim((string) $validatedData['email'])),
                    'contact_number' => (string) $validatedData['contact_number'],
                    'date_of_birth' => $validatedData['date_of_birth'],
                    'age_at_submission' => $age,
                    'sex' => (string) $validatedData['sex'],
                    'address_house_no' => (string) $validatedData['address_house_no'],
                    'address_road_no' => !empty($validatedData['address_road_no']) ? (string) $validatedData['address_road_no'] : null,
                    'address_area' => (string) $validatedData['address_area'],
                    'address_zip_code' => (string) $validatedData['address_zip_code'],
                    'address_division' => (string) $validatedData['address_division'],
                    'address_district' => (string) $validatedData['address_district'],
                    'website' => !empty($validatedData['website']) ? (string) $validatedData['website'] : null,
                    'occupation' => (string) $validatedData['occupation'],
                    'occupation_other' => !empty($validatedData['occupation_other']) ? (string) $validatedData['occupation_other'] : null,
                    'institution_study' => (string) $validatedData['institution_study'],
                    'highest_certification' => (string) $validatedData['highest_certification'],
                    'highest_certification_other' => !empty($validatedData['highest_certification_other']) ? (string) $validatedData['highest_certification_other'] : null,
                    'area_of_subject' => (string) $validatedData['area_of_subject'],
                    'has_us_experience' => in_array((string) ($validatedData['has_us_experience'] ?? '0'), ['1', 'true'], true),
                    'us_experience_location' => !empty($validatedData['us_experience_location']) ? (string) $validatedData['us_experience_location'] : null,
                    'is_american_alumni' => in_array((string) ($validatedData['is_american_alumni'] ?? '0'), ['1', 'true'], true),
                    'american_institution_name' => !empty($validatedData['american_institution_name']) ? (string) $validatedData['american_institution_name'] : null,
                    'employer_name' => !empty($validatedData['employer_name']) ? (string) $validatedData['employer_name'] : null,
                    'job_title' => !empty($validatedData['job_title']) ? (string) $validatedData['job_title'] : null,
                    'work_phone' => !empty($validatedData['work_phone']) ? (string) $validatedData['work_phone'] : null,
                    'has_parental_consent' => ($age < 18),
                    'terms_agreed' => true,
                    'payment_verification_status' => 'pending',
                    'document_verification_status' => 'pending',
                    'photo_verification_status' => 'pending',
                    'submitted_at' => now(),
                ]);

                foreach ($storedAttachments as $attachment) {
                    $meta = $attachment['meta'];
                    LeadAttachment::create([
                        'workspace_id' => $workspaceId,
                        'lead_id' => $lead->id,
                        'document_type' => $attachment['type'],
                        'storage_path' => $meta['storage_path'],
                        'original_filename' => $meta['original_filename'],
                        'mime_type' => $meta['mime_type'],
                        'size_bytes' => $meta['size_bytes'],
                        'file_hash' => $meta['file_hash'],
                        'youth_protection' => $meta['youth_protection'],
                    ]);
                }

                $existingSubmissionId = $validatedData['form_submission_id'] ?? null;
                $existingSubmissionUuid = $validatedData['form_submission_uuid'] ?? null;

                if ($existingSubmissionId || $existingSubmissionUuid) {
                    FormSubmission::where(function ($query) use ($existingSubmissionId, $existingSubmissionUuid) {
                        if ($existingSubmissionId) {
                            $query->where('id', $existingSubmissionId);
                        }
                        if ($existingSubmissionUuid) {
                            $query->orWhere('uuid', $existingSubmissionUuid);
                        }
                    })->update([
                        'lead_id' => $lead->id,
                        'status' => 'processed',
                    ]);
                } else {
                    FormSubmission::create([
                        'form_id' => $form->id,
                        'workspace_id' => $workspaceId,
                        'lead_id' => $lead->id,
                        'payload' => $validatedData,
                        'status' => 'processed',
                        'ip_address' => $ipAddress,
                        'user_agent' => $userAgent,
                        'submitted_at' => now(),
                    ]);
                }

                return $lead;
            });

            if (!empty($lead->workspace_id) && \Illuminate\Support\Facades\Schema::hasTable('workflow_rules')) {
                try {
                    app(\App\Domain\Mail\Services\WorkflowRuleEngine::class)->trigger(
                        (string) $lead->workspace_id,
                        \App\Domain\Mail\Models\WorkflowRule::TRIGGER_LEAD_SUBMITTED,
                        (string) $lead->email,
                        [
                            'applicant_name' => $lead->full_name,
                            'workspace_name' => (string) $lead->workspace_id,
                        ],
                        $lead->id,
                        null,
                        "lead_submitted:{$lead->id}"
                    );
                } catch (\Throwable) {
                    // Fault-isolated per T-08-14 so mail configuration never blocks ingestion
                }
            }

            return $lead->load('attachments');
        } catch (\Throwable $e) {
            // Rollback files on private disk if any exception occurred
            foreach ($storedFilePaths as $path) {
                $this->documentStorage->delete($path);
            }

            throw $e;
        }
    }
}
