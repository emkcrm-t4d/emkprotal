<?php

declare(strict_types=1);

namespace App\Domain\Migration\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Services\MemberIdSequenceGenerator;
use App\Domain\Migration\Models\ZohoImportBatch;
use App\Domain\Migration\Models\ZohoStagingRecord;
use App\Models\User;
use Carbon\Carbon;
use Generator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ZohoDataIngestionService
{
    public function __construct(
        private readonly ZohoFieldMappingService $mappingService,
        private readonly MemberIdSequenceGenerator $sequenceGenerator
    ) {}

    /**
     * Ingest a Zoho CSV file through chunked streaming and staging persistence.
     *
     * @param  string  $filePath
     * @param  string  $workspaceId
     * @param  string  $sourceType  'contacts_members', 'leads', or 'form_submissions'
     * @param  int  $chunkSize
     * @param  bool  $dryRun
     * @param  callable|null  $progressCallback  function(int $processedCount, int $totalCount, ZohoImportBatch $batch): void
     * @return ZohoImportBatch
     *
     * @throws RuntimeException
     */
    public function ingestFile(
        string $filePath,
        string $workspaceId,
        string $sourceType,
        int $chunkSize = 100,
        bool $dryRun = false,
        ?callable $progressCallback = null
    ): ZohoImportBatch {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new RuntimeException("CSV file [{$filePath}] does not exist or is not readable.");
        }

        $totalRows = $this->countCsvRows($filePath);

        // 1. Create import batch tracking entity
        $batch = ZohoImportBatch::create([
            'batch_uuid' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'source_type' => $sourceType,
            'source_filename' => basename($filePath),
            'total_rows' => $totalRows,
            'processed_rows' => 0,
            'successful_rows' => 0,
            'failed_rows' => 0,
            'skipped_rows' => 0,
            'status' => 'processing',
            'started_at' => Carbon::now(),
        ]);

        $chunk = [];
        $processedSoFar = 0;

        try {
            foreach ($this->streamCsvRows($filePath) as $rowNum => $rawRow) {
                $chunk[] = ['rowNum' => $rowNum, 'data' => $rawRow];

                if (count($chunk) >= $chunkSize) {
                    $this->processChunk($batch, $chunk, $workspaceId, $sourceType, $dryRun);
                    $processedSoFar += count($chunk);

                    if ($progressCallback !== null) {
                        $progressCallback($processedSoFar, $totalRows, $batch);
                    }

                    $chunk = [];
                    // Enforce 64MB memory envelope per T-11-02
                    gc_collect_cycles();
                }
            }

            // Process any remaining rows in final chunk
            if (!empty($chunk)) {
                $this->processChunk($batch, $chunk, $workspaceId, $sourceType, $dryRun);
                $processedSoFar += count($chunk);

                if ($progressCallback !== null) {
                    $progressCallback($processedSoFar, $totalRows, $batch);
                }

                $chunk = [];
                gc_collect_cycles();
            }

            // Finalize batch status
            $batch->refresh();
            $batch->completed_at = Carbon::now();
            $batch->status = ($batch->failed_rows > 0 && $batch->successful_rows === 0 && $batch->skipped_rows === 0)
                ? 'failed'
                : 'completed';
            $batch->save();

            // Emit append-only immutable audit event per DEC-11
            AuditLog::create([
                'workspace_id' => $workspaceId,
                'actor_id' => Auth::id(),
                'actor_guard' => 'web',
                'event_type' => AuditLog::EVENT_ZOHO_BATCH_IMPORTED,
                'auditable_type' => ZohoImportBatch::class,
                'auditable_id' => $batch->id,
                'new_values' => [
                    'batch_uuid' => $batch->batch_uuid,
                    'source_type' => $batch->source_type,
                    'source_filename' => $batch->source_filename,
                    'total_rows' => $batch->total_rows,
                    'processed_rows' => $batch->processed_rows,
                    'successful_rows' => $batch->successful_rows,
                    'skipped_rows' => $batch->skipped_rows,
                    'failed_rows' => $batch->failed_rows,
                    'dry_run' => $dryRun,
                ],
                'reason' => "Completed Zoho import batch [{$batch->batch_uuid}] for source [{$sourceType}] with {$batch->successful_rows} successes, {$batch->skipped_rows} skipped, and {$batch->failed_rows} failures.",
                'ip_address' => request()?->ip() ?? '127.0.0.1',
                'user_agent' => request()?->userAgent() ?? 'Artisan CLI / Ingestion Engine',
            ]);

            return $batch;
        } catch (Throwable $e) {
            $batch->refresh();
            $batch->completed_at = Carbon::now();
            $batch->status = 'failed';
            $summary = $batch->error_summary ?? [];
            $summary[] = [
                'fatal_error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
            $batch->error_summary = $summary;
            $batch->save();

            throw $e;
        }
    }

    /**
     * Process a single chunk within a dedicated database transaction.
     *
     * @param  ZohoImportBatch  $batch
     * @param  list<array{rowNum: int, data: array<string, mixed>}>  $chunk
     * @param  string  $workspaceId
     * @param  string  $sourceType
     * @param  bool  $dryRun
     */
    protected function processChunk(
        ZohoImportBatch $batch,
        array $chunk,
        string $workspaceId,
        string $sourceType,
        bool $dryRun
    ): void {
        DB::transaction(function () use ($batch, $chunk, $workspaceId, $sourceType, $dryRun): void {
            $processedCount = 0;
            $successCount = 0;
            $skippedCount = 0;
            $failedCount = 0;

            foreach ($chunk as $item) {
                $rawRow = $item['data'];
                $processedCount++;

                try {
                    $mappingResult = $this->mappingService->mapRecord($sourceType, $rawRow, $workspaceId);
                    $mapped = $mappingResult['mapped'];
                    $validationStatus = $mappingResult['validation_status'];
                    $validationErrors = $mappingResult['validation_errors'];
                    $zohoId = $mapped['zoho_id'] ?? null;

                    // 1. Stage raw record
                    $stagingRecord = ZohoStagingRecord::create([
                        'batch_id' => $batch->id,
                        'workspace_id' => $workspaceId,
                        'zoho_id' => $zohoId,
                        'source_type' => $sourceType,
                        'raw_payload' => $rawRow,
                        'mapped_payload' => $mapped,
                        'validation_status' => $validationStatus,
                        'validation_errors' => !empty($validationErrors) ? $validationErrors : null,
                        'status' => 'staged',
                    ]);

                    // If validation marked as invalid, fail row without domain hydration
                    if ($validationStatus === 'invalid') {
                        $stagingRecord->update([
                            'status' => 'failed',
                            'error_message' => implode(' ', $validationErrors),
                        ]);
                        $failedCount++;
                        continue;
                    }

                    // If dry run, count success and stop at staging
                    if ($dryRun) {
                        $successCount++;
                        continue;
                    }

                    // 2. Perform duplicate check & core domain model hydration
                    if ($sourceType === 'contacts_members') {
                        $this->hydrateMember($stagingRecord, $mapped, $workspaceId, $zohoId, $successCount, $skippedCount, $failedCount);
                    } elseif ($sourceType === 'leads') {
                        $this->hydrateLead($stagingRecord, $mapped, $workspaceId, $zohoId, $successCount, $skippedCount, $failedCount);
                    } else {
                        // For generic form submissions, staging is the destination
                        $stagingRecord->update(['status' => 'imported']);
                        $successCount++;
                    }
                } catch (Throwable $e) {
                    $failedCount++;
                    if (isset($stagingRecord) && $stagingRecord instanceof ZohoStagingRecord) {
                        $stagingRecord->update([
                            'status' => 'failed',
                            'error_message' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Update batch counters atomically
            $batch->increment('processed_rows', $processedCount);
            if ($successCount > 0) {
                $batch->increment('successful_rows', $successCount);
            }
            if ($skippedCount > 0) {
                $batch->increment('skipped_rows', $skippedCount);
            }
            if ($failedCount > 0) {
                $batch->increment('failed_rows', $failedCount);
            }
        });
    }

    /**
     * Hydrate a Member domain entity while preserving zoho_id and checking duplicates.
     */
    protected function hydrateMember(
        ZohoStagingRecord $stagingRecord,
        array $mapped,
        string $workspaceId,
        ?string $zohoId,
        int &$successCount,
        int &$skippedCount,
        int &$failedCount
    ): void {
        // Check for duplicate zoho_id or email
        $duplicate = false;
        if (!empty($zohoId)) {
            $duplicate = Member::where('zoho_id', $zohoId)->exists();
        }
        if (!$duplicate && !empty($mapped['email'])) {
            $duplicate = Member::where('email', $mapped['email'])->exists();
        }

        if ($duplicate) {
            $stagingRecord->update([
                'status' => 'skipped',
                'error_message' => "Member with Zoho ID [{$zohoId}] or email [{$mapped['email']}] already exists.",
            ]);
            $skippedCount++;
            return;
        }

        // 1. Associate User credential entity if already existing; otherwise leave unprovisioned
        $user = !empty($mapped['email']) ? User::where('email', $mapped['email'])->first() : null;

        // 2. Provision or associate Lead
        $lead = null;
        if (!empty($zohoId)) {
            $lead = Lead::where('zoho_id', $zohoId)->first();
        }
        if (!$lead && !empty($mapped['email'])) {
            $lead = Lead::where('email', $mapped['email'])->where('workspace_id', $workspaceId)->first();
        }

        if (!$lead) {
            $dob = $mapped['date_of_birth'] ?? '1995-01-01';
            $age = 25;
            try {
                $age = Carbon::parse($dob)->age;
            } catch (\Throwable) {
                $age = 25;
            }

            $bkashTxid = $this->generateUniqueBkashTxid($mapped['bkash_txid'] ?? null, $zohoId);

            $lead = Lead::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $workspaceId,
                'target_space_code' => 'EMK-DHK',
                'status' => 'converted',
                'bkash_txid' => $bkashTxid,
                'first_name' => $mapped['first_name'],
                'middle_name' => $mapped['middle_name'] ?? null,
                'last_name' => $mapped['last_name'],
                'email' => $mapped['email'],
                'contact_number' => $mapped['contact_number'],
                'date_of_birth' => $dob,
                'age_at_submission' => $age,
                'sex' => $mapped['sex'] ?? 'na',
                'address_house_no' => $mapped['address_house_no'],
                'address_road_no' => $mapped['address_road_no'] ?? null,
                'address_area' => $mapped['address_area'],
                'address_zip_code' => $mapped['address_zip_code'],
                'address_division' => $mapped['address_division'],
                'address_district' => $mapped['address_district'],
                'occupation' => $mapped['occupation'],
                'institution_study' => $mapped['institution_study'],
                'highest_certification' => $mapped['highest_certification'],
                'area_of_subject' => $mapped['area_of_subject'],
                'has_us_experience' => false,
                'is_american_alumni' => false,
                'has_parental_consent' => ($age < 18),
                'terms_agreed' => true,
                'payment_verification_status' => 'verified',
                'document_verification_status' => 'verified',
                'photo_verification_status' => 'verified',
                'zoho_id' => $zohoId,
                'submitted_at' => $mapped['start_date'] ?? Carbon::now(),
            ]);
        }

        // If lead is already assigned to a member, skip to prevent foreign key violation
        if (Member::where('lead_id', $lead->id)->exists()) {
            $stagingRecord->update([
                'status' => 'skipped',
                'error_message' => "Lead [{$lead->id}] is already associated with an existing member.",
            ]);
            $skippedCount++;
            return;
        }

        // 3. Generate sequential Member ID
        $startYear = (int) Carbon::parse($mapped['start_date'])->year;
        $memberId = $this->sequenceGenerator->nextMemberId('EMK', $startYear > 2000 ? $startYear : (int) Carbon::now()->year);

        // 4. Create Member entity with preserved zoho_id
        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => $memberId,
            'workspace_id' => $workspaceId,
            'lead_id' => $lead->id,
            'user_id' => $user?->id,
            'membership_term_id' => $mapped['membership_term_id'],
            'status' => $mapped['status'] ?? 'active',
            'first_name' => $mapped['first_name'],
            'middle_name' => $mapped['middle_name'] ?? null,
            'last_name' => $mapped['last_name'],
            'email' => $mapped['email'],
            'contact_number' => $mapped['contact_number'],
            'date_of_birth' => $mapped['date_of_birth'],
            'sex' => $mapped['sex'],
            'address_house_no' => $mapped['address_house_no'],
            'address_road_no' => $mapped['address_road_no'] ?? null,
            'address_area' => $mapped['address_area'],
            'address_zip_code' => $mapped['address_zip_code'],
            'address_division' => $mapped['address_division'],
            'address_district' => $mapped['address_district'],
            'occupation' => $mapped['occupation'],
            'institution_study' => $mapped['institution_study'],
            'highest_certification' => $mapped['highest_certification'],
            'area_of_subject' => $mapped['area_of_subject'],
            'start_date' => $mapped['start_date'],
            'expiry_date' => $mapped['expiry_date'],
            'zoho_id' => $zohoId,
        ]);

        $lead->update([
            'converted_member_id' => $member->id,
            'status' => 'converted',
        ]);

        $stagingRecord->update([
            'imported_entity_type' => Member::class,
            'imported_entity_id' => $member->id,
            'status' => 'imported',
        ]);

        $successCount++;
    }

    /**
     * Hydrate a Lead domain entity while preserving zoho_id and checking duplicates.
     */
    protected function hydrateLead(
        ZohoStagingRecord $stagingRecord,
        array $mapped,
        string $workspaceId,
        ?string $zohoId,
        int &$successCount,
        int &$skippedCount,
        int &$failedCount
    ): void {
        // Check for duplicate zoho_id or email within workspace
        $duplicate = false;
        if (!empty($zohoId)) {
            $duplicate = Lead::where('zoho_id', $zohoId)->exists();
        }
        if (!$duplicate && !empty($mapped['email'])) {
            $duplicate = Lead::where('email', $mapped['email'])->where('workspace_id', $workspaceId)->exists();
        }

        if ($duplicate) {
            $stagingRecord->update([
                'status' => 'skipped',
                'error_message' => "Lead with Zoho ID [{$zohoId}] or email [{$mapped['email']}] already exists.",
            ]);
            $skippedCount++;
            return;
        }

        $bkashTxid = $this->generateUniqueBkashTxid($mapped['bkash_txid'] ?? null, $zohoId);

        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'target_space_code' => $mapped['target_space_code'] ?? 'EMK-DHK',
            'status' => $mapped['status'] ?? 'submitted',
            'bkash_txid' => $bkashTxid,
            'first_name' => $mapped['first_name'],
            'middle_name' => $mapped['middle_name'] ?? null,
            'last_name' => $mapped['last_name'],
            'email' => $mapped['email'],
            'contact_number' => $mapped['contact_number'],
            'date_of_birth' => $mapped['date_of_birth'],
            'age_at_submission' => $mapped['age_at_submission'] ?? 24,
            'sex' => $mapped['sex'] ?? 'na',
            'address_house_no' => $mapped['address_house_no'],
            'address_road_no' => $mapped['address_road_no'] ?? null,
            'address_area' => $mapped['address_area'],
            'address_zip_code' => $mapped['address_zip_code'],
            'address_division' => $mapped['address_division'],
            'address_district' => $mapped['address_district'],
            'occupation' => $mapped['occupation'],
            'institution_study' => $mapped['institution_study'],
            'highest_certification' => $mapped['highest_certification'],
            'area_of_subject' => $mapped['area_of_subject'],
            'has_us_experience' => false,
            'is_american_alumni' => false,
            'has_parental_consent' => ($mapped['age_at_submission'] < 18),
            'terms_agreed' => true,
            'payment_verification_status' => 'pending',
            'document_verification_status' => 'pending',
            'photo_verification_status' => 'pending',
            'zoho_id' => $zohoId,
            'submitted_at' => $mapped['submitted_at'] ?? Carbon::now(),
        ]);

        $stagingRecord->update([
            'imported_entity_type' => Lead::class,
            'imported_entity_id' => $lead->id,
            'status' => 'imported',
        ]);

        $successCount++;
    }

    /**
     * Generate or normalize unique 10-char bKash transaction ID per DEC-05.
     */
    protected function generateUniqueBkashTxid(?string $rawTxid, ?string $zohoId): string
    {
        if (!empty($rawTxid)) {
            $candidate = strtoupper(preg_replace('/[^A-Z0-9]/', '', $rawTxid));
            if (strlen($candidate) === 10 && !Lead::where('bkash_txid', $candidate)->exists()) {
                return $candidate;
            }
        }

        $base = !empty($zohoId) ? strtoupper(substr(md5((string) $zohoId), 0, 8)) : strtoupper(Str::random(8));
        $txid = 'ZH' . $base;

        while (Lead::where('bkash_txid', $txid)->exists()) {
            $txid = 'ZH' . strtoupper(Str::random(8));
        }

        return $txid;
    }

    /**
     * Stream CSV rows row-by-row using generator pattern (under 64MB memory envelope per T-11-02).
     *
     * @return Generator<int, array<string, mixed>>
     */
    protected function streamCsvRows(string $filePath): Generator
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new RuntimeException("Unable to open CSV file [{$filePath}].");
        }

        try {
            $headers = fgetcsv($handle);
            if ($headers === false) {
                return;
            }

            // Remove UTF-8 BOM from first header if present
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
            $headers = array_map(fn ($h) => trim((string) $h), $headers);

            $rowNum = 1;
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                // Skip completely empty lines
                if (empty(array_filter($row, fn ($v) => trim((string) $v) !== ''))) {
                    continue;
                }

                $rowData = [];
                foreach ($headers as $idx => $header) {
                    $rowData[$header] = $row[$idx] ?? null;
                }

                yield $rowNum => $rowData;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Count total non-empty rows in CSV file without loading entire file into memory.
     */
    public function countCsvRows(string $filePath): int
    {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            return 0;
        }

        $count = 0;
        try {
            // Read and skip headers
            fgetcsv($handle);

            while (($row = fgetcsv($handle)) !== false) {
                if (!empty(array_filter($row, fn ($v) => trim((string) $v) !== ''))) {
                    $count++;
                }
            }
        } finally {
            fclose($handle);
        }

        return $count;
    }
}
