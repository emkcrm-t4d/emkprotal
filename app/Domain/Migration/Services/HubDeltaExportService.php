<?php

declare(strict_types=1);

namespace App\Domain\Migration\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Leads\Models\Lead;
use App\Domain\Workspaces\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class HubDeltaExportService
{
    /**
     * CSV formula injection trigger characters per T-11-12 and RFC 4180.
     *
     * @var list<string>
     */
    protected const FORMULA_TRIGGERS = ['=', '+', '-', '@', "\t", "\r"];

    /**
     * Standard legacy Zoho Forms / CRM CSV headers for delta import.
     *
     * @var list<string>
     */
    protected const CSV_HEADERS = [
        'Lead UUID',
        'Zoho ID',
        'First Name',
        'Middle Name',
        'Last Name',
        'Email',
        'Mobile',
        'Date of Birth',
        'Gender',
        'House No',
        'Road No',
        'Area',
        'Postal Code',
        'Division',
        'District',
        'Occupation',
        'Institution',
        'Highest Certification',
        'Area of Subject',
        'bKash Transaction ID',
        'Status',
        'Submission Date',
        'Target Space',
    ];

    /**
     * Export applications submitted to the Hub after a given cutoff timestamp
     * into a Zoho-compliant CSV format with formula injection defenses (T-11-12).
     *
     * @param  Carbon  $cutoffTimestamp
     * @param  string  $workspaceId
     * @return string
     *
     * @throws InvalidArgumentException
     */
    public function exportDeltaSince(Carbon $cutoffTimestamp, string $workspaceId): string
    {
        $workspace = Workspace::find($workspaceId);
        if (!$workspace) {
            throw new InvalidArgumentException("Workspace [{$workspaceId}] not found.");
        }

        $leads = Lead::where('workspace_id', $workspaceId)
            ->where('submitted_at', '>=', $cutoffTimestamp)
            ->orderBy('submitted_at', 'asc')
            ->get();

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('Failed to allocate temporary stream memory for CSV generation.');
        }

        // 1. Write Header row
        fputcsv($handle, self::CSV_HEADERS);

        // 2. Format each lead into sanitized Zoho CSV row
        foreach ($leads as $lead) {
            $row = [
                $this->sanitizeCell((string) $lead->uuid),
                $this->sanitizeCell((string) ($lead->zoho_id ?: '')),
                $this->sanitizeCell((string) $lead->first_name),
                $this->sanitizeCell((string) ($lead->middle_name ?: '')),
                $this->sanitizeCell((string) $lead->last_name),
                $this->sanitizeCell((string) $lead->email),
                $this->sanitizeCell((string) $lead->contact_number),
                $this->sanitizeCell($lead->date_of_birth ? $lead->date_of_birth->format('Y-m-d') : ''),
                $this->sanitizeCell((string) ($lead->sex ?: 'na')),
                $this->sanitizeCell((string) $lead->address_house_no),
                $this->sanitizeCell((string) ($lead->address_road_no ?: '')),
                $this->sanitizeCell((string) $lead->address_area),
                $this->sanitizeCell((string) $lead->address_zip_code),
                $this->sanitizeCell((string) $lead->address_division),
                $this->sanitizeCell((string) $lead->address_district),
                $this->sanitizeCell((string) $lead->occupation),
                $this->sanitizeCell((string) $lead->institution_study),
                $this->sanitizeCell((string) $lead->highest_certification),
                $this->sanitizeCell((string) $lead->area_of_subject),
                $this->sanitizeCell((string) $lead->bkash_txid),
                $this->sanitizeCell((string) $lead->status),
                $this->sanitizeCell($lead->submitted_at ? $lead->submitted_at->format('Y-m-d H:i:s') : ''),
                $this->sanitizeCell((string) $lead->target_space_code),
            ];

            fputcsv($handle, $row);
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        if ($csvContent === false) {
            throw new \RuntimeException('Failed to read CSV stream output buffer.');
        }

        // 3. Emit immutable audit log per DEC-11 and GAP-P1-06
        AuditLog::create([
            'workspace_id' => $workspaceId,
            'actor_id' => Auth::id(),
            'actor_guard' => Auth::getDefaultDriver() ?: 'web',
            'event_type' => AuditLog::EVENT_HUB_DELTA_EXPORTED,
            'auditable_type' => Lead::class,
            'auditable_id' => null,
            'new_values' => [
                'workspace_id' => $workspaceId,
                'cutoff_timestamp' => $cutoffTimestamp->toIso8601String(),
                'exported_records_count' => $leads->count(),
            ],
            'reason' => "Exported {$leads->count()} delta lead submissions since [{$cutoffTimestamp->toDateTimeString()}] for emergency cutover reversal.",
            'ip_address' => request()?->ip() ?? '127.0.0.1',
            'user_agent' => request()?->userAgent() ?? 'Artisan CLI / Hub Delta Exporter',
        ]);

        return $csvContent;
    }

    /**
     * Sanitize CSV cell against formula injection (CSV injection) attacks per T-11-12.
     * Prepends single quote (') to cells starting with '=', '+', '-', '@', '\t', or '\r'.
     */
    public function sanitizeCell(string $value): string
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            return '';
        }

        $firstChar = $trimmed[0];
        if (in_array($firstChar, self::FORMULA_TRIGGERS, true)) {
            return "'" . $value;
        }

        return $value;
    }
}
