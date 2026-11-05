<?php

declare(strict_types=1);

namespace App\Domain\Migration\DTOs;

class ReconciliationReportDTO
{
    /**
     * @param  array<string, int>  $sourceStatusCounts
     * @param  array<string, int>  $importedStatusCounts
     * @param  array<string>  $duplicateEmails
     * @param  array<int, array{zoho_id: string, entity: string, field: string, expected: mixed, actual: mixed, severity: string, message: string}>  $discrepancies
     */
    public function __construct(
        public readonly string $batchUuid,
        public readonly string $workspaceId,
        public readonly string $generatedAt,
        public readonly int $sourceRecordCount,
        public readonly int $importedRecordCount,
        public readonly int $discrepancyCount,
        public readonly array $sourceStatusCounts,
        public readonly array $importedStatusCounts,
        public readonly int $sourceUniqueEmails,
        public readonly int $importedUniqueEmails,
        public readonly array $duplicateEmails,
        public readonly int $dateMismatchCount,
        public readonly int $expectedAttachments,
        public readonly int $actualAttachments,
        public readonly int $missingAttachments,
        public readonly array $discrepancies = [],
    ) {}

    /**
     * Determines whether the dataset is 100% reconciled with zero discrepancies.
     */
    public function isReconciled(): bool
    {
        return $this->discrepancyCount === 0;
    }

    /**
     * Returns the match percentage formatted as a float with up to two decimals.
     */
    public function matchPercentage(): float
    {
        if ($this->sourceRecordCount === 0) {
            return 100.0;
        }

        // Calculate matched records based on discrepancy-free ratio
        $matched = max(0, $this->sourceRecordCount - $this->discrepancyCount);
        $percentage = ($matched / $this->sourceRecordCount) * 100;

        return round(max(0.0, min(100.0, $percentage)), 2);
    }

    /**
     * Export DTO as array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'batch_uuid' => $this->batchUuid,
            'workspace_id' => $this->workspaceId,
            'generated_at' => $this->generatedAt,
            'source_record_count' => $this->sourceRecordCount,
            'imported_record_count' => $this->importedRecordCount,
            'discrepancy_count' => $this->discrepancyCount,
            'source_status_counts' => $this->sourceStatusCounts,
            'imported_status_counts' => $this->importedStatusCounts,
            'source_unique_emails' => $this->sourceUniqueEmails,
            'imported_unique_emails' => $this->importedUniqueEmails,
            'duplicate_emails' => $this->duplicateEmails,
            'date_mismatch_count' => $this->dateMismatchCount,
            'expected_attachments' => $this->expectedAttachments,
            'actual_attachments' => $this->actualAttachments,
            'missing_attachments' => $this->missingAttachments,
            'is_reconciled' => $this->isReconciled(),
            'match_percentage' => $this->matchPercentage(),
            'discrepancies' => $this->discrepancies,
        ];
    }
}

