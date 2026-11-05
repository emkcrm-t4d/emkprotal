<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Reports\Services\MetricsAggregationService;
use App\Domain\Workspaces\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceDataExportService
{
    /**
     * Authorize that the current authenticated user has access to export the requested workspace.
     */
    protected function authorizeWorkspace(string $workspaceId): void
    {
        $user = Auth::user();
        if (!$user) {
            App::abort(401, 'Unauthenticated.');
        }

        $isSuperadmin = method_exists($user, 'hasRole') && $user->hasRole(['superadmin', 'global_admin']);

        // Non-superadmin staff must belong to the requested workspace
        if (!$isSuperadmin && (string) $user->workspace_id !== $workspaceId) {
            App::abort(403, 'Unauthorized workspace access.');
        }
    }

    /**
     * Record an immutable audit log entry for this export operation per DEC-11 (D-11).
     *
     * @param  string  $workspaceId  Target workspace identifier
     * @param  string  $eventType  AuditLog event constant
     * @param  string  $auditableType  Class string of exported domain model
     * @param  int  $rowCount  Count of exported records
     * @param  array<string, mixed>  $filters  Filters applied to query
     * @param  string  $format  File format
     * @param  string  $filename  Generated export filename
     */
    protected function recordExportAudit(
        string $workspaceId,
        string $eventType,
        string $auditableType,
        int $rowCount,
        array $filters,
        string $format,
        string $filename
    ): void {
        $user = Auth::user();

        AuditLog::create([
            'workspace_id' => $workspaceId,
            'actor_id' => $user?->id,
            'actor_guard' => 'web',
            'event_type' => $eventType,
            'auditable_type' => $auditableType,
            'auditable_id' => null,
            'old_values' => null,
            'new_values' => [
                'filters' => $filters,
                'exported_rows' => $rowCount,
                'format' => $format,
                'filename' => $filename,
            ],
            'reason' => "Exported {$rowCount} records from workspace {$workspaceId}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Export filtered workspace members as a memory-protected streaming CSV.
     *
     * @param  string  $workspaceId  Target workspace identifier
     * @param  array<string, mixed>  $filters  Query filters (status, search, date_from, date_to)
     * @param  string  $format  Export format ('csv')
     */
    public function exportMembers(string $workspaceId, array $filters = [], string $format = 'csv'): StreamedResponse
    {
        $this->authorizeWorkspace($workspaceId);

        $query = Member::query()->where('workspace_id', $workspaceId);

        $status = $filters['status'] ?? $filters['statusFilter'] ?? null;
        if (!empty($status) && $status !== 'all') {
            $query->where('status', (string) $status);
        }

        $parsedFrom = $this->parseDateSafely($filters['date_from'] ?? $filters['dateFrom'] ?? null);
        if ($parsedFrom !== null) {
            $query->whereDate('created_at', '>=', $parsedFrom);
        }

        $parsedTo = $this->parseDateSafely($filters['date_to'] ?? $filters['dateTo'] ?? null);
        if ($parsedTo !== null) {
            $query->whereDate('created_at', '<=', $parsedTo);
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('member_id', 'like', $term)
                    ->orWhere('contact_number', 'like', $term);
            });
        }

        $query->orderBy('id', 'asc');

        $rowCount = (clone $query)->count();
        $filename = "members-export-{$workspaceId}-" . now()->format('Ymd-His') . ".csv";

        // Record immutable audit log per DEC-11 (D-11)
        $this->recordExportAudit(
            $workspaceId,
            AuditLog::EVENT_MEMBERS_EXPORTED,
            Member::class,
            $rowCount,
            $filters,
            $format,
            $filename
        );

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 Byte Order Mark for Microsoft Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");

            // CSV Column Headers per DASH-03
            fputcsv($handle, [
                'Member ID',
                'Status',
                'First Name',
                'Last Name',
                'Email',
                'Contact Number',
                'Date of Birth',
                'Gender',
                'Division',
                'District',
                'Occupation',
                'Institution',
                'Start Date',
                'Expiry Date',
                'Created At',
            ]);

            // Stream rows in 200-record chunks to prevent memory exhaustion (T-10-05)
            $query->chunk(200, function ($members) use ($handle) {
                foreach ($members as $member) {
                    $row = [
                        $this->sanitizeCsvCell($member->member_id),
                        $this->sanitizeCsvCell($member->status),
                        $this->sanitizeCsvCell($member->first_name),
                        $this->sanitizeCsvCell($member->last_name),
                        $this->sanitizeCsvCell($member->email),
                        $this->sanitizeCsvCell($member->contact_number),
                        $this->sanitizeCsvCell($member->date_of_birth ? $member->date_of_birth->format('Y-m-d') : ''),
                        $this->sanitizeCsvCell($member->sex),
                        $this->sanitizeCsvCell($member->address_division),
                        $this->sanitizeCsvCell($member->address_district),
                        $this->sanitizeCsvCell($member->occupation),
                        $this->sanitizeCsvCell($member->institution_study),
                        $this->sanitizeCsvCell($member->start_date ? $member->start_date->format('Y-m-d') : ''),
                        $this->sanitizeCsvCell($member->expiry_date ? $member->expiry_date->format('Y-m-d') : ''),
                        $this->sanitizeCsvCell($member->created_at ? $member->created_at->format('Y-m-d H:i:s') : ''),
                    ];

                    fputcsv($handle, $row);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export filtered workspace leads as a memory-protected streaming CSV.
     *
     * @param  string  $workspaceId  Target workspace identifier
     * @param  array<string, mixed>  $filters  Query filters (status, search, date_from, date_to)
     * @param  string  $format  Export format ('csv')
     */
    public function exportLeads(string $workspaceId, array $filters = [], string $format = 'csv'): StreamedResponse
    {
        $this->authorizeWorkspace($workspaceId);

        $query = Lead::query()->where('workspace_id', $workspaceId);

        $status = $filters['status'] ?? $filters['statusFilter'] ?? null;
        if (!empty($status) && $status !== 'all') {
            $query->where('status', (string) $status);
        }

        $parsedFrom = $this->parseDateSafely($filters['date_from'] ?? $filters['dateFrom'] ?? null);
        if ($parsedFrom !== null) {
            $query->whereDate('submitted_at', '>=', $parsedFrom);
        }

        $parsedTo = $this->parseDateSafely($filters['date_to'] ?? $filters['dateTo'] ?? null);
        if ($parsedTo !== null) {
            $query->whereDate('submitted_at', '<=', $parsedTo);
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('first_name', 'like', $term)
                    ->orWhere('last_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('bkash_txid', 'like', $term)
                    ->orWhere('contact_number', 'like', $term);
            });
        }

        $query->orderBy('id', 'asc');

        $rowCount = (clone $query)->count();
        $filename = "leads-export-{$workspaceId}-" . now()->format('Ymd-His') . ".csv";

        // Record immutable audit log per DEC-11 (D-11)
        $this->recordExportAudit(
            $workspaceId,
            AuditLog::EVENT_LEADS_EXPORTED,
            Lead::class,
            $rowCount,
            $filters,
            $format,
            $filename
        );

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 Byte Order Mark
            fputs($handle, "\xEF\xBB\xBF");

            // CSV Column Headers per DASH-03
            fputcsv($handle, [
                'Lead ID',
                'Status',
                'First Name',
                'Last Name',
                'Email',
                'Contact Number',
                'Target Space',
                'bKash TxID',
                'Submitted At',
            ]);

            // Stream rows in 200-record chunks (T-10-05)
            $query->chunk(200, function ($leads) use ($handle) {
                foreach ($leads as $lead) {
                    $row = [
                        $this->sanitizeCsvCell($lead->uuid ?? (string) $lead->id),
                        $this->sanitizeCsvCell($lead->status),
                        $this->sanitizeCsvCell($lead->first_name),
                        $this->sanitizeCsvCell($lead->last_name),
                        $this->sanitizeCsvCell($lead->email),
                        $this->sanitizeCsvCell($lead->contact_number),
                        $this->sanitizeCsvCell($lead->target_space_code),
                        $this->sanitizeCsvCell($lead->bkash_txid),
                        $this->sanitizeCsvCell($lead->submitted_at ? $lead->submitted_at->format('Y-m-d H:i:s') : ''),
                    ];

                    fputcsv($handle, $row);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export filtered workspace invoices as a memory-protected streaming CSV.
     *
     * @param  string  $workspaceId  Target workspace identifier
     * @param  array<string, mixed>  $filters  Query filters (status, search, date_from, date_to)
     * @param  string  $format  Export format ('csv')
     */
    public function exportInvoices(string $workspaceId, array $filters = [], string $format = 'csv'): StreamedResponse
    {
        $this->authorizeWorkspace($workspaceId);

        $query = Invoice::query()->with('member')->where('workspace_id', $workspaceId);

        $status = $filters['status'] ?? $filters['statusFilter'] ?? null;
        if (!empty($status) && $status !== 'all') {
            $query->where('status', (string) $status);
        }

        $parsedFrom = $this->parseDateSafely($filters['date_from'] ?? $filters['dateFrom'] ?? null);
        if ($parsedFrom !== null) {
            $query->whereDate('created_at', '>=', $parsedFrom);
        }

        $parsedTo = $this->parseDateSafely($filters['date_to'] ?? $filters['dateTo'] ?? null);
        if ($parsedTo !== null) {
            $query->whereDate('created_at', '<=', $parsedTo);
        }

        if (!empty($filters['search'])) {
            $term = '%' . trim((string) $filters['search']) . '%';
            $query->where(function (Builder $q) use ($term) {
                $q->where('invoice_number', 'like', $term)
                    ->orWhere('payment_reference', 'like', $term)
                    ->orWhereHas('member', function (Builder $mq) use ($term) {
                        $mq->where('member_id', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    });
            });
        }

        $query->orderBy('id', 'asc');

        $rowCount = (clone $query)->count();
        $filename = "invoices-export-{$workspaceId}-" . now()->format('Ymd-His') . ".csv";

        // Record immutable audit log per DEC-11 (D-11)
        $this->recordExportAudit(
            $workspaceId,
            AuditLog::EVENT_INVOICES_EXPORTED,
            Invoice::class,
            $rowCount,
            $filters,
            $format,
            $filename
        );

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 Byte Order Mark
            fputs($handle, "\xEF\xBB\xBF");

            // CSV Column Headers per DASH-03
            fputcsv($handle, [
                'Invoice Number',
                'Member ID',
                'Amount (BDT)',
                'Payment Method',
                'Payment Reference',
                'Status',
                'Paid At',
                'Created At',
            ]);

            // Stream rows in 200-record chunks (T-10-05)
            $query->chunk(200, function ($invoices) use ($handle) {
                foreach ($invoices as $invoice) {
                    $row = [
                        $this->sanitizeCsvCell($invoice->invoice_number),
                        $this->sanitizeCsvCell($invoice->member?->member_id ?? (string) $invoice->member_id),
                        $this->sanitizeCsvCell($invoice->amount_bdt),
                        $this->sanitizeCsvCell($invoice->payment_method),
                        $this->sanitizeCsvCell($invoice->payment_reference),
                        $this->sanitizeCsvCell($invoice->status),
                        $this->sanitizeCsvCell($invoice->paid_at ? $invoice->paid_at->format('Y-m-d H:i:s') : ''),
                        $this->sanitizeCsvCell($invoice->created_at ? $invoice->created_at->format('Y-m-d H:i:s') : ''),
                    ];

                    fputcsv($handle, $row);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export workspace operational metrics as a streaming CSV report.
     *
     * @param  string  $workspaceId  Target workspace identifier
     * @param  array<string, mixed>  $filters  Query filters (date_from, date_to)
     */
    public function exportMetrics(string $workspaceId, array $filters = []): StreamedResponse
    {
        $this->authorizeWorkspace($workspaceId);

        $dateFrom = $filters['date_from'] ?? $filters['dateFrom'] ?? null;
        $dateTo = $filters['date_to'] ?? $filters['dateTo'] ?? null;

        /** @var MetricsAggregationService $metricsService */
        $metricsService = App::make(MetricsAggregationService::class);
        $metrics = $metricsService->getMetrics(
            $workspaceId,
            $dateFrom ? (string) $dateFrom : null,
            $dateTo ? (string) $dateTo : null,
            false
        );

        $filename = "metrics-export-{$workspaceId}-" . now()->format('Ymd-His') . ".csv";

        $metricRows = [
            ['Overview', 'Total Members', (string) $metrics->totalMembers],
            ['Overview', 'Total Leads', (string) $metrics->totalLeads],
            ['Overview', 'Conversion Rate (%)', (string) $metrics->conversionRate],
            ['Overview', 'Total ID Cards', (string) $metrics->totalIdCards],
            ['Overview', 'Total Revenue (BDT)', number_format($metrics->totalRevenueBdt, 2, '.', '')],
            ['Overview', 'Paid Invoices Count', (string) $metrics->paidInvoicesCount],
        ];

        foreach ($metrics->memberStatusBreakdown as $status => $count) {
            $metricRows[] = ['Member Status', ucfirst((string) $status), (string) $count];
        }

        foreach ($metrics->leadPipelineBreakdown as $stage => $count) {
            $metricRows[] = ['Lead Pipeline', ucfirst(str_replace('_', ' ', (string) $stage)), (string) $count];
        }

        foreach ($metrics->idCardStatusBreakdown as $cardStatus => $count) {
            $metricRows[] = ['ID Card Status', ucfirst(str_replace('_', ' ', (string) $cardStatus)), (string) $count];
        }

        // Record immutable audit log per DEC-11 (D-11)
        $this->recordExportAudit(
            $workspaceId,
            AuditLog::EVENT_METRICS_EXPORTED,
            Workspace::class,
            count($metricRows),
            $filters,
            'csv',
            $filename
        );

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($metricRows, $workspaceId) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 Byte Order Mark
            fputs($handle, "\xEF\xBB\xBF");

            // CSV Column Headers
            fputcsv($handle, [
                'Metric Category',
                'Metric Name',
                'Value',
                'Workspace ID',
                'Generated At',
            ]);

            $nowStr = now()->toIso8601String();

            foreach ($metricRows as $row) {
                fputcsv($handle, [
                    $this->sanitizeCsvCell($row[0]),
                    $this->sanitizeCsvCell($row[1]),
                    $this->sanitizeCsvCell($row[2]),
                    $this->sanitizeCsvCell($workspaceId),
                    $this->sanitizeCsvCell($nowStr),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Neutralize CSV formula injection risks by prepending a single quote to cells beginning with =, +, -, or @.
     *
     * @param  mixed  $value  Raw cell value
     * @return string Sanitized string value
     */
    public function sanitizeCsvCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $str = is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value;

        // Check if string contains leading whitespace/tab/CR followed by formula triggers
        $ltrimmed = ltrim($str, " \t\r\n");
        if (strlen($ltrimmed) > 0 && in_array($ltrimmed[0], ['=', '+', '-', '@'], true)) {
            return "'" . $ltrimmed;
        }

        // Direct leading trigger check (including tab and CR)
        if (strlen($str) > 0 && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $str;
        }

        return trim($str);
    }

    /**
     * Safely parse a date string into Y-m-d format, returning null on invalid input.
     */
    protected function parseDateSafely(mixed $date): ?string
    {
        if (empty($date) || (!is_string($date) && !is_numeric($date))) {
            return null;
        }

        try {
            return Carbon::parse((string) $date)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}

