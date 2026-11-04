<?php

declare(strict_types=1);

namespace App\Domain\Forms\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormSubmissionExportService
{
    /**
     * Export form submissions to a streaming RFC 4180 CSV response with formula sanitization and audit logging.
     *
     * @param  Form  $form  The form to export submissions for
     * @param  string  $workspaceId  Active workspace identifier
     * @param  array<string, mixed>  $filters  Query filters (search, status, date_from, date_to)
     * @return StreamedResponse
     */
    public function export(Form $form, string $workspaceId, array $filters = []): StreamedResponse
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

        // Enforce forms.view_entries permission
        if (!$isSuperadmin && method_exists($user, 'can') && !$user->can('forms.view_entries')) {
            App::abort(403, 'Forbidden: forms.view_entries permission required.');
        }

        // Build submission query
        $query = FormSubmission::with(['lead', 'workspace'])
            ->where('form_id', $form->id);

        if (!$isSuperadmin) {
            $query->where('workspace_id', $workspaceId);
        } elseif (!empty($filters['workspace_id'])) {
            $query->where('workspace_id', (string) $filters['workspace_id']);
        }

        // Status filter
        if (!empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        // Date range filters
        if (!empty($filters['date_from'])) {
            $query->whereDate('submitted_at', '>=', (string) $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('submitted_at', '<=', (string) $filters['date_to']);
        }

        // Search text filter
        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim((string) $filters['search']) . '%';
            $query->where(function ($q) use ($searchTerm) {
                $q->whereHas('lead', function ($lq) use ($searchTerm) {
                    $lq->where('first_name', 'like', $searchTerm)
                        ->orWhere('last_name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm)
                        ->orWhere('contact_number', 'like', $searchTerm)
                        ->orWhere('bkash_txid', 'like', $searchTerm);
                })
                ->orWhere('payload->first_name', 'like', $searchTerm)
                ->orWhere('payload->last_name', 'like', $searchTerm)
                ->orWhere('payload->email', 'like', $searchTerm)
                ->orWhere('payload->contact_number', 'like', $searchTerm)
                ->orWhere('payload->bkash_txid', 'like', $searchTerm);
            });
        }

        // Order chronologically descending
        $query->orderByDesc('submitted_at');

        $exportedCount = (clone $query)->count();
        $filename = "form-submissions-{$form->slug}-" . now()->format('Ymd-His') . ".csv";

        // Record immutable audit log entry per DEC-11 (D-11)
        AuditLog::create([
            'workspace_id' => $workspaceId,
            'actor_id' => $user->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_FORM_SUBMISSIONS_EXPORTED,
            'auditable_type' => Form::class,
            'auditable_id' => $form->id,
            'old_values' => null,
            'new_values' => [
                'filters' => $filters,
                'exported_rows' => $exportedCount,
                'filename' => $filename,
            ],
            'reason' => "Exported {$exportedCount} form submissions for form: {$form->slug}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($query) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 Byte Order Mark for Excel compatibility
            fputs($handle, "\xEF\xBB\xBF");

            // CSV Headers per requirement
            fputcsv($handle, [
                'Submission UUID',
                'Submitted At',
                'Target Space',
                'Status',
                'First Name',
                'Last Name',
                'Email',
                'Contact Number',
                'Sex',
                'Date of Birth',
                'Age',
                'Has Youth Consent',
                'Division',
                'District',
                'Occupation',
                'Institution',
                'bKash TxID',
                'Terms Agreed',
            ]);

            // Stream rows in chunks
            $query->chunk(100, function ($submissions) use ($handle) {
                foreach ($submissions as $sub) {
                    $lead = $sub->lead;
                    $payload = $sub->payload ?? [];

                    $hasYouthConsent = $lead?->has_parental_consent
                        ?? (!empty($payload['has_parental_consent']) || !empty($payload['parental_consent_file']));

                    $termsAgreed = $lead?->terms_agreed
                        ?? (!empty($payload['terms_agreed']) && $payload['terms_agreed'] !== '0');

                    $row = [
                        $this->sanitizeCsvCell($sub->uuid),
                        $this->sanitizeCsvCell($sub->submitted_at ? $sub->submitted_at->toIso8601String() : ''),
                        $this->sanitizeCsvCell($lead?->target_space_code ?? ($payload['american_space'] ?? '')),
                        $this->sanitizeCsvCell($sub->status),
                        $this->sanitizeCsvCell($lead?->first_name ?? ($payload['first_name'] ?? '')),
                        $this->sanitizeCsvCell($lead?->last_name ?? ($payload['last_name'] ?? '')),
                        $this->sanitizeCsvCell($lead?->email ?? ($payload['email'] ?? '')),
                        $this->sanitizeCsvCell($lead?->contact_number ?? ($payload['contact_number'] ?? '')),
                        $this->sanitizeCsvCell($lead?->sex ?? ($payload['sex'] ?? '')),
                        $this->sanitizeCsvCell($lead?->date_of_birth ? $lead->date_of_birth->format('Y-m-d') : ($payload['date_of_birth'] ?? '')),
                        $this->sanitizeCsvCell($lead?->age_at_submission ?? ($payload['age'] ?? '')),
                        $this->sanitizeCsvCell($hasYouthConsent ? 'Yes' : 'No'),
                        $this->sanitizeCsvCell($lead?->address_division ?? ($payload['address_division'] ?? '')),
                        $this->sanitizeCsvCell($lead?->address_district ?? ($payload['address_district'] ?? '')),
                        $this->sanitizeCsvCell($lead?->occupation ?? ($payload['occupation'] ?? '')),
                        $this->sanitizeCsvCell($lead?->institution_study ?? ($payload['institution_study'] ?? '')),
                        $this->sanitizeCsvCell($lead?->bkash_txid ?? ($payload['bkash_txid'] ?? '')),
                        $this->sanitizeCsvCell($termsAgreed ? 'Yes' : 'No'),
                    ];

                    fputcsv($handle, $row);
                }
            });

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
        $str = trim($str);

        if (strlen($str) > 0 && in_array($str[0], ['=', '+', '-', '@'], true)) {
            return "'" . $str;
        }

        return $str;
    }
}
