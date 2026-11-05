<?php

declare(strict_types=1);

namespace App\Domain\Migration\Services;

use App\Domain\Migration\DTOs\ReconciliationReportDTO;

class ReconciliationReportGenerator
{
    /**
     * Generate an RFC 4180 compliant CSV string with CSV formula injection defense.
     */
    public function toCsv(ReconciliationReportDTO $report): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        // CSV Headers per MIGR-04 specification
        fputcsv($handle, [
            'zoho_id',
            'entity',
            'field',
            'expected_value',
            'actual_value',
            'severity',
            'message',
        ]);

        foreach ($report->discrepancies as $d) {
            $expectedVal = $this->stringifyValue($d['expected'] ?? ($d['expected_value'] ?? ''));
            $actualVal = $this->stringifyValue($d['actual'] ?? ($d['actual_value'] ?? ''));

            $row = [
                $this->sanitizeCsvCell((string) ($d['zoho_id'] ?? '')),
                $this->sanitizeCsvCell((string) ($d['entity'] ?? '')),
                $this->sanitizeCsvCell((string) ($d['field'] ?? '')),
                $this->sanitizeCsvCell($expectedVal),
                $this->sanitizeCsvCell($actualVal),
                $this->sanitizeCsvCell((string) ($d['severity'] ?? '')),
                $this->sanitizeCsvCell((string) ($d['message'] ?? '')),
            ];

            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }

    /**
     * Defend against CSV formula injection (T-11-09) by prepending single quote to formula triggers.
     */
    public function sanitizeCsvCell(string $value): string
    {
        $trimmed = ltrim($value);
        if ($trimmed !== '' && in_array($trimmed[0], ['=', '+', '-', '@'], true)) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Generate a self-contained, XSS-safe HTML reconciliation report.
     */
    public function toHtml(ReconciliationReportDTO $report): string
    {
        $title = 'Reconciliation Audit Report - ' . $report->workspaceId;
        $batch = htmlspecialchars($report->batchUuid, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $workspace = htmlspecialchars($report->workspaceId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $timestamp = htmlspecialchars($report->generatedAt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $matchPct = number_format($report->matchPercentage(), 2);
        $statusBadgeColor = $report->isReconciled() ? '#10b981' : '#ef4444';
        $statusText = $report->isReconciled() ? '100% RECONCILED' : 'DISCREPANCIES DETECTED';

        $sourceCount = $report->sourceRecordCount;
        $importedCount = $report->importedRecordCount;
        $discrepancyCount = $report->discrepancyCount;
        $dateMismatches = $report->dateMismatchCount;
        $missingAtts = $report->missingAttachments;

        $rowsHtml = '';
        if (empty($report->discrepancies)) {
            $rowsHtml = '<tr><td colspan="7" style="text-align: center; padding: 24px; color: #10b981; font-weight: 600;">Zero discrepancies detected. Source and imported records match 100%.</td></tr>';
        } else {
            foreach ($report->discrepancies as $d) {
                $zohoId = htmlspecialchars((string) ($d['zoho_id'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $entity = htmlspecialchars((string) ($d['entity'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $field = htmlspecialchars((string) ($d['field'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $expected = htmlspecialchars($this->stringifyValue($d['expected'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $actual = htmlspecialchars($this->stringifyValue($d['actual'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $severity = htmlspecialchars((string) ($d['severity'] ?? 'info'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $message = htmlspecialchars((string) ($d['message'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                $badgeBg = match ($d['severity'] ?? 'info') {
                    'critical' => '#fee2e2; color: #b91c1c;',
                    'warning' => '#fef3c7; color: #b45309;',
                    default => '#e0e7ff; color: #4338ca;',
                };

                $rowsHtml .= "<tr>
                    <td style=\"font-family: monospace; font-size: 13px;\">{$zohoId}</td>
                    <td>{$entity}</td>
                    <td><strong>{$field}</strong></td>
                    <td style=\"color: #047857;\">{$expected}</td>
                    <td style=\"color: #b91c1c;\">{$actual}</td>
                    <td><span style=\"display: inline-block; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 700; text-transform: uppercase; background: {$badgeBg}\">{$severity}</span></td>
                    <td style=\"font-size: 13px;\">{$message}</td>
                </tr>";
            }
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 32px; }
        .container { max-width: 1200px; margin: 0 auto; background: #ffffff; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); padding: 32px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 20px; margin-bottom: 24px; }
        .badge { padding: 6px 16px; border-radius: 9999px; color: #ffffff; font-weight: 700; font-size: 14px; background-color: {$statusBadgeColor}; }
        .meta { color: #64748b; font-size: 14px; margin-top: 4px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 32px; }
        .card { background: #f1f5f9; border-radius: 6px; padding: 16px; text-align: center; }
        .card-val { font-size: 28px; font-weight: 700; color: #0f172a; margin-top: 4px; }
        .card-label { font-size: 12px; font-weight: 600; text-transform: uppercase; color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 14px; }
        th, td { text-align: left; padding: 12px 14px; border-bottom: 1px solid #e2e8f0; }
        th { background-color: #f8fafc; font-weight: 600; color: #475569; }
        tr:hover { background-color: #f8fafc; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1 style="margin: 0; font-size: 24px; color: #0f172a;">Zoho Migration Reconciliation Report</h1>
                <div class="meta">Workspace: <strong>{$workspace}</strong> | Batch: <strong>{$batch}</strong> | Generated: <strong>{$timestamp}</strong></div>
            </div>
            <div>
                <span class="badge">{$statusText} ({$matchPct}%)</span>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <div class="card-label">Match Percentage</div>
                <div class="card-val" style="color: {$statusBadgeColor};">{$matchPct}%</div>
            </div>
            <div class="card">
                <div class="card-label">Source Records</div>
                <div class="card-val">{$sourceCount}</div>
            </div>
            <div class="card">
                <div class="card-label">Imported Records</div>
                <div class="card-val">{$importedCount}</div>
            </div>
            <div class="card">
                <div class="card-label">Total Discrepancies</div>
                <div class="card-val" style="color: #b91c1c;">{$discrepancyCount}</div>
            </div>
            <div class="card">
                <div class="card-label">Date Divergences</div>
                <div class="card-val">{$dateMismatches}</div>
            </div>
            <div class="card">
                <div class="card-label">Missing Attachments</div>
                <div class="card-val" style="color: #b91c1c;">{$missingAtts}</div>
            </div>
        </div>

        <h2 style="font-size: 18px; margin-bottom: 8px; color: #0f172a;">Discrepancy Audit Ledger</h2>
        <table>
            <thead>
                <tr>
                    <th>Zoho ID</th>
                    <th>Entity</th>
                    <th>Field</th>
                    <th>Expected</th>
                    <th>Actual</th>
                    <th>Severity</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                {$rowsHtml}
            </tbody>
        </table>
    </div>
</body>
</html>
HTML;
    }

    protected function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '';
        }

        return (string) $value;
    }
}
