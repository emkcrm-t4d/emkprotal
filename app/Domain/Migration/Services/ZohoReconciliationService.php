<?php

declare(strict_types=1);

namespace App\Domain\Migration\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Members\Models\Member;
use App\Domain\Migration\DTOs\ReconciliationReportDTO;
use App\Domain\Migration\Models\ZohoImportBatch;
use App\Domain\Migration\Models\ZohoStagingRecord;
use App\Domain\Workspaces\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ZohoReconciliationService
{
    public function __construct(
        protected ZohoFieldMappingService $mappingService
    ) {}

    /**
     * Run discrepancy detection and reconciliation for a single import batch.
     */
    public function reconcileBatch(ZohoImportBatch $batch, bool $logAudit = true): ReconciliationReportDTO
    {
        $workspaceId = (string) $batch->workspace_id;
        $stagingRecords = ZohoStagingRecord::where('batch_id', $batch->id)
            ->where('workspace_id', $workspaceId)
            ->get();

        $report = $this->evaluateRecords($stagingRecords, $workspaceId, (string) $batch->batch_uuid, $batch->source_type);

        if ($logAudit) {
            $this->logAuditEvent($workspaceId, $report, $batch);
        }

        return $report;
    }

    /**
     * Run holistic discrepancy detection and reconciliation across an entire workspace.
     */
    public function reconcileWorkspace(string $workspaceId, bool $logAudit = true): ReconciliationReportDTO
    {
        $stagingRecords = ZohoStagingRecord::where('workspace_id', $workspaceId)->get();

        $report = $this->evaluateRecords($stagingRecords, $workspaceId, 'workspace-' . $workspaceId, null);

        if ($logAudit) {
            $this->logAuditEvent($workspaceId, $report, null);
        }

        return $report;
    }

    /**
     * Internal evaluation engine cross-checking staging records against persisted entities.
     *
     * @param  Collection<int, ZohoStagingRecord>  $stagingRecords
     */
    protected function evaluateRecords(
        Collection $stagingRecords,
        string $workspaceId,
        string $batchUuid,
        ?string $defaultSourceType
    ): ReconciliationReportDTO {
        $sourceRecordCount = $stagingRecords->count();
        $importedRecordCount = 0;
        $discrepancies = [];

        $sourceStatusCounts = [];
        $importedStatusCounts = [];

        $emailToZohoIds = [];
        $allSourceEmails = [];
        $allImportedEmails = [];

        $dateMismatchCount = 0;
        $expectedAttachments = 0;
        $actualAttachments = 0;
        $missingAttachments = 0;

        foreach ($stagingRecords as $record) {
            $zohoId = (string) ($record->zoho_id ?: 'UNKNOWN_' . $record->id);
            $raw = is_array($record->raw_payload) ? $record->raw_payload : [];
            $mapped = is_array($record->mapped_payload) ? $record->mapped_payload : [];
            $sourceType = $record->source_type ?: ($defaultSourceType ?: 'contacts_members');

            // 1. Email collection & format validation
            $rawEmail = $mapped['email'] ?? $this->mappingService->getValue($raw, ['Email', 'Email Address', 'email', 'Primary Email']);
            if (!empty($rawEmail)) {
                $emailClean = strtolower(trim((string) $rawEmail));
                $allSourceEmails[$emailClean] = true;
                if (!empty($record->zoho_id)) {
                    $emailToZohoIds[$emailClean][] = (string) $record->zoho_id;
                }

                if (!filter_var($emailClean, FILTER_VALIDATE_EMAIL)) {
                    $discrepancies[] = [
                        'zoho_id' => $zohoId,
                        'entity' => $sourceType,
                        'field' => 'email',
                        'expected' => 'valid_email_format',
                        'actual' => $rawEmail,
                        'severity' => 'critical',
                        'message' => "Invalid email address format [{$rawEmail}] for Zoho ID [{$zohoId}].",
                    ];
                }
            }

            // 2. Target entity resolution
            $target = null;
            $entityName = 'record';

            if ($sourceType === 'contacts_members' || $record->imported_entity_type === Member::class) {
                $entityName = 'member';
                $target = Member::where('workspace_id', $workspaceId)
                    ->where(function ($q) use ($record, $zohoId) {
                        if (!empty($record->zoho_id)) {
                            $q->where('zoho_id', $zohoId);
                        }
                        if ($record->imported_entity_id) {
                            $q->orWhere('id', $record->imported_entity_id);
                        }
                    })->first();
            } elseif ($sourceType === 'leads' || $record->imported_entity_type === Lead::class) {
                $entityName = 'lead';
                $target = Lead::where('workspace_id', $workspaceId)
                    ->where(function ($q) use ($record, $zohoId) {
                        if (!empty($record->zoho_id)) {
                            $q->where('zoho_id', $zohoId);
                        }
                        if ($record->imported_entity_id) {
                            $q->orWhere('id', $record->imported_entity_id);
                        }
                    })->first();
            }

            // 3. Existence verification
            if (!$target) {
                $discrepancies[] = [
                    'zoho_id' => $zohoId,
                    'entity' => $entityName,
                    'field' => 'existence',
                    'expected' => 'present',
                    'actual' => 'missing',
                    'severity' => 'critical',
                    'message' => "Target {$entityName} record for Zoho ID [{$zohoId}] not found in database.",
                ];
                continue;
            }

            $importedRecordCount++;
            if (!empty($target->email)) {
                $allImportedEmails[strtolower(trim((string) $target->email))] = true;
            }

            // 4. Status distribution & mismatch check
            if ($target instanceof Member) {
                $expectedStatus = $mapped['status'] ?? $this->mappingService->normalizeMemberStatus($raw['Status'] ?? 'active');
                $actualStatus = (string) $target->status;

                $sourceStatusCounts[$expectedStatus] = ($sourceStatusCounts[$expectedStatus] ?? 0) + 1;
                $importedStatusCounts[$actualStatus] = ($importedStatusCounts[$actualStatus] ?? 0) + 1;

                if ($expectedStatus !== $actualStatus) {
                    $discrepancies[] = [
                        'zoho_id' => $zohoId,
                        'entity' => $entityName,
                        'field' => 'status',
                        'expected' => $expectedStatus,
                        'actual' => $actualStatus,
                        'severity' => 'warning',
                        'message' => "Status mismatch for Zoho ID [{$zohoId}]: expected [{$expectedStatus}], found [{$actualStatus}].",
                    ];
                }
            } elseif ($target instanceof Lead) {
                $expectedStatus = $mapped['status'] ?? $this->mappingService->normalizeLeadStatus($raw['Status'] ?? 'submitted');
                $actualStatus = (string) $target->status;

                $sourceStatusCounts[$expectedStatus] = ($sourceStatusCounts[$expectedStatus] ?? 0) + 1;
                $importedStatusCounts[$actualStatus] = ($importedStatusCounts[$actualStatus] ?? 0) + 1;

                if ($expectedStatus !== $actualStatus) {
                    $discrepancies[] = [
                        'zoho_id' => $zohoId,
                        'entity' => $entityName,
                        'field' => 'status',
                        'expected' => $expectedStatus,
                        'actual' => $actualStatus,
                        'severity' => 'warning',
                        'message' => "Status mismatch for Zoho ID [{$zohoId}]: expected [{$expectedStatus}], found [{$actualStatus}].",
                    ];
                }
            }

            // 5. Date fidelity verification
            if ($target instanceof Member) {
                $this->checkDateField('start_date', $mapped['start_date'] ?? null, $target->start_date, $zohoId, $entityName, $dateMismatchCount, $discrepancies);
                $this->checkDateField('expiry_date', $mapped['expiry_date'] ?? null, $target->expiry_date, $zohoId, $entityName, $dateMismatchCount, $discrepancies);
                $this->checkDateField('date_of_birth', $mapped['date_of_birth'] ?? null, $target->date_of_birth, $zohoId, $entityName, $dateMismatchCount, $discrepancies);
            } elseif ($target instanceof Lead) {
                $this->checkDateField('date_of_birth', $mapped['date_of_birth'] ?? null, $target->date_of_birth, $zohoId, $entityName, $dateMismatchCount, $discrepancies);
            }

            // 6. Attachment completeness verification
            $lead = $target instanceof Member ? Lead::find($target->lead_id) : ($target instanceof Lead ? $target : null);
            $hasExplicitExpected = !empty($raw['Attachment'])
                || !empty($raw['Photo'])
                || !empty($raw['attachments'])
                || !empty($mapped['attachments'])
                || !empty($raw['has_attachment'])
                || (isset($raw['expected_attachments']) && (int) $raw['expected_attachments'] > 0);

            $attachments = $lead ? LeadAttachment::where('lead_id', $lead->id)->get() : new Collection();

            if ($hasExplicitExpected && $attachments->isEmpty()) {
                $expectedAttachments++;
                $missingAttachments++;
                $discrepancies[] = [
                    'zoho_id' => $zohoId,
                    'entity' => $entityName,
                    'field' => 'attachment',
                    'expected' => 'attachment_record',
                    'actual' => 'none',
                    'severity' => 'critical',
                    'message' => "Expected attachment missing in database for Zoho ID [{$zohoId}].",
                ];
            } else {
                foreach ($attachments as $att) {
                    $expectedAttachments++;
                    $existsOnDisk = Storage::disk('private')->exists($att->storage_path);
                    if ($existsOnDisk) {
                        $actualAttachments++;
                    } else {
                        $missingAttachments++;
                        $discrepancies[] = [
                            'zoho_id' => $zohoId,
                            'entity' => $entityName,
                            'field' => 'attachment',
                            'expected' => 'file_on_disk',
                            'actual' => 'missing_file',
                            'severity' => 'critical',
                            'message' => "Attachment [{$att->original_filename}] missing from private storage for Zoho ID [{$zohoId}].",
                        ];
                    }
                }
            }
        }

        // 7. Check for source duplicate emails mapped across multiple Zoho IDs
        $duplicateEmails = [];
        foreach ($emailToZohoIds as $email => $ids) {
            $uniqueIds = array_values(array_unique($ids));
            if (count($uniqueIds) > 1) {
                $duplicateEmails[] = $email;
                $discrepancies[] = [
                    'zoho_id' => implode(', ', $uniqueIds),
                    'entity' => 'email_uniqueness',
                    'field' => 'email',
                    'expected' => 'unique',
                    'actual' => 'duplicate',
                    'severity' => 'critical',
                    'message' => "Duplicate source email [{$email}] mapped across multiple Zoho IDs: " . implode(', ', $uniqueIds) . ".",
                ];
            }
        }

        return new ReconciliationReportDTO(
            batchUuid: $batchUuid,
            workspaceId: $workspaceId,
            generatedAt: Carbon::now()->toIso8601String(),
            sourceRecordCount: $sourceRecordCount,
            importedRecordCount: $importedRecordCount,
            discrepancyCount: count($discrepancies),
            sourceStatusCounts: $sourceStatusCounts,
            importedStatusCounts: $importedStatusCounts,
            sourceUniqueEmails: count($allSourceEmails),
            importedUniqueEmails: count($allImportedEmails),
            duplicateEmails: $duplicateEmails,
            dateMismatchCount: $dateMismatchCount,
            expectedAttachments: $expectedAttachments,
            actualAttachments: $actualAttachments,
            missingAttachments: $missingAttachments,
            discrepancies: $discrepancies,
        );
    }

    /**
     * Check date equality and append discrepancy if mismatch detected.
     *
     * @param  array<int, array<string, mixed>>  $discrepancies
     */
    protected function checkDateField(
        string $field,
        ?string $expectedDate,
        mixed $actualDate,
        string $zohoId,
        string $entityName,
        int &$dateMismatchCount,
        array &$discrepancies
    ): void {
        if (empty($expectedDate) || empty($actualDate)) {
            return;
        }

        $expectedFormatted = substr(trim((string) $expectedDate), 0, 10);
        $actualFormatted = is_string($actualDate)
            ? substr(trim($actualDate), 0, 10)
            : ($actualDate instanceof \DateTimeInterface ? $actualDate->format('Y-m-d') : null);

        if ($expectedFormatted !== $actualFormatted) {
            $dateMismatchCount++;
            $discrepancies[] = [
                'zoho_id' => $zohoId,
                'entity' => $entityName,
                'field' => $field,
                'expected' => $expectedFormatted,
                'actual' => $actualFormatted,
                'severity' => 'warning',
                'message' => "Date mismatch on [{$field}] for Zoho ID [{$zohoId}]: expected [{$expectedFormatted}], found [{$actualFormatted}].",
            ];
        }
    }

    /**
     * Log an append-only immutable audit record per DEC-11.
     */
    protected function logAuditEvent(string $workspaceId, ReconciliationReportDTO $report, ?ZohoImportBatch $batch): void
    {
        AuditLog::create([
            'workspace_id' => $workspaceId,
            'actor_id' => Auth::id(),
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_ZOHO_RECONCILIATION_RUN,
            'auditable_type' => $batch ? ZohoImportBatch::class : Workspace::class,
            'auditable_id' => $batch?->id,
            'new_values' => [
                'batch_uuid' => $report->batchUuid,
                'workspace_id' => $workspaceId,
                'source_record_count' => $report->sourceRecordCount,
                'imported_record_count' => $report->importedRecordCount,
                'discrepancy_count' => $report->discrepancyCount,
                'match_percentage' => $report->matchPercentage(),
                'is_reconciled' => $report->isReconciled(),
                'date_mismatch_count' => $report->dateMismatchCount,
                'missing_attachments' => $report->missingAttachments,
            ],
            'reason' => "Executed Zoho reconciliation audit for workspace [{$workspaceId}]" . ($batch ? " batch [{$batch->batch_uuid}]" : '') . " resulting in {$report->discrepancyCount} discrepancies ({$report->matchPercentage()}% match).",
            'ip_address' => request()?->ip() ?? '127.0.0.1',
            'user_agent' => request()?->userAgent() ?? 'Artisan CLI / Reconciliation Engine',
        ]);
    }
}
