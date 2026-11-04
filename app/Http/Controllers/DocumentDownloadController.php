<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Leads\Services\DocumentStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    public function __construct(
        protected DocumentStorageService $storageService
    ) {}

    /**
     * Authenticated and tenant-isolated streaming of private applicant attachments.
     *
     * 7-Step Defense-in-Depth Pipeline:
     * 1. Authentication assertion (abort 401 if unauthenticated)
     * 2. Tenancy isolation check (match auth user workspace and route param to lead workspace)
     * 3. RBAC authorization (leads.view_youth_records / workspace_manager for minors, leads.view_documents for standard)
     * 4. Private disk file existence assertion (abort 404 if missing)
     * 5. AES-256 payload decryption via DocumentStorageService
     * 6. Audit log recording in audit_logs ('document_accessed' event) per DEC-11
     * 7. Stream response with security headers (X-Content-Type-Options, private Cache-Control)
     *
     * @param  Request  $request
     * @param  string  $workspace
     * @param  LeadAttachment|string|int  $attachment
     * @return StreamedResponse
     */
    public function download(Request $request, string $workspace, LeadAttachment|string|int $attachment): StreamedResponse
    {
        // 1. Guard & Authentication Assertion
        $user = auth()->user();
        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        // Resolve model if raw ID passed
        if (!$attachment instanceof LeadAttachment) {
            $attachment = LeadAttachment::with('lead')->findOrFail($attachment);
        } else {
            $attachment->loadMissing('lead');
        }

        // 2. Tenancy Isolation Barrier
        $lead = $attachment->lead;
        $isElevatedAdmin = (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin())
            || (method_exists($user, 'isGlobalAdmin') && $user->isGlobalAdmin());

        if (!$isElevatedAdmin) {
            if ($lead && $lead->workspace_id !== $user->workspace_id) {
                abort(403, 'Unauthorized: Cross-workspace document access is forbidden.');
            }

            if ($lead && $workspace !== $lead->workspace_id) {
                abort(403, 'Unauthorized: Workspace route parameter mismatch.');
            }
        }

        // 3. RBAC Permission Assertion
        if ($attachment->youth_protection) {
            // DEC-09: Minor records require elevated compliance permission or manager role
            $hasYouthPermission = $user->can('leads.view_youth_records')
                || (method_exists($user, 'hasRole') && $user->hasRole('workspace_manager'));

            if (!$hasYouthPermission) {
                abort(403, 'Unauthorized: Access to youth applicant documents requires leads.view_youth_records permission or workspace_manager role.');
            }
        } else {
            if (!$user->can('leads.view_documents')) {
                abort(403, 'Unauthorized: Insufficient document viewing permissions. Required: leads.view_documents.');
            }
        }

        // 4. File Existence Assertion on Private Disk
        $disk = Storage::disk('private');
        if (!$disk->exists($attachment->storage_path)) {
            abort(404, 'Requested document attachment does not exist on private disk.');
        }

        // 5. Decrypt File Payload
        $decryptedPayload = $this->storageService->get($attachment->storage_path);

        // 6. Audit Download Event (DEC-11 / D-11)
        DB::table('audit_logs')->insert([
            'workspace_id' => $lead?->workspace_id ?? $user->workspace_id,
            'actor_id' => $user->id,
            'actor_guard' => $user->guard ?? 'web',
            'event_type' => 'document_accessed',
            'auditable_type' => LeadAttachment::class,
            'auditable_id' => $attachment->id,
            'old_values' => null,
            'new_values' => json_encode([
                'original_filename' => $attachment->original_filename,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
                'file_hash' => $attachment->file_hash,
                'youth_protection' => $attachment->youth_protection,
            ]),
            'reason' => 'Staff document verification review.',
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent() ?? 'System / HTTP',
            'created_at' => now(),
        ]);

        // 7. Authenticated Secure Streamed Response
        return response()->stream(
            function () use ($decryptedPayload) {
                echo $decryptedPayload;
            },
            200,
            [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => 'inline; filename="' . addslashes($attachment->original_filename) . '"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
            ]
        );
    }
}
