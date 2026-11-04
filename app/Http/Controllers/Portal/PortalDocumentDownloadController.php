<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Leads\Services\DocumentStorageService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalDocumentDownloadController extends Controller
{
    public function __construct(
        protected ?DocumentStorageService $storageService = null
    ) {
        $this->storageService = $storageService ?? app(DocumentStorageService::class);
    }

    /**
     * Authenticated and IDOR-defended streaming of member documents and identity credentials.
     * Enforces strict ownership checks against the authenticated member account per ARCH-06 and DEC-09.
     *
     * @param Request $request
     * @param LeadAttachment|string|int $attachment
     * @return StreamedResponse
     */
    public function download(Request $request, LeadAttachment|string|int $attachment): StreamedResponse
    {
        // 1. Authenticated Member Session Assertion
        $user = Auth::guard('member')->user();
        if (!$user) {
            abort(401, 'Unauthenticated member portal access.');
        }

        $member = $user->member;
        if (!$member) {
            abort(403, 'Unauthorized: No active member record associated with this account.');
        }

        // 2. Resolve LeadAttachment without global tenant scopes
        if ($attachment instanceof LeadAttachment) {
            $attachmentModel = $attachment;
        } else {
            $attachmentModel = LeadAttachment::withoutGlobalScopes()
                ->where(function ($q) use ($attachment) {
                    if (is_numeric($attachment)) {
                        $q->where('id', (int) $attachment);
                    } elseif (Schema::hasColumn('lead_attachments', 'uuid')) {
                        $q->where('uuid', (string) $attachment);
                    } else {
                        $q->where('id', (string) $attachment);
                    }
                })
                ->first();
        }

        if (!$attachmentModel) {
            abort(404, 'Requested document attachment was not found.');
        }

        // 3. IDOR Defense Guard (PORT-02, ARCH-06, DEC-09):
        // Verify attachment strictly belongs to the authenticated member:
        // Either foreign key lead_id matches member->lead_id, OR storage path points to member/lead directory.
        $leadIdMatch = ($attachmentModel->lead_id !== null && (int) $attachmentModel->lead_id === (int) $member->lead_id);

        $leadUuid = $member->lead?->uuid;
        $memberUuid = $member->uuid;
        $workspaceId = $member->workspace_id;
        $storagePath = (string) ($attachmentModel->storage_path ?? $attachmentModel->file_path ?? '');

        $pathMatch = false;
        if (!empty($storagePath) && !empty($workspaceId)) {
            if (!empty($memberUuid) && str_contains($storagePath, "workspaces/{$workspaceId}/members/{$memberUuid}")) {
                $pathMatch = true;
            }
            if (!empty($leadUuid) && str_contains($storagePath, "workspaces/{$workspaceId}/leads/{$leadUuid}")) {
                $pathMatch = true;
            }
        }

        if (!$leadIdMatch && !$pathMatch) {
            abort(403, 'Unauthorized document access: You do not have permission to view or download this document.');
        }

        // 4. File Existence Assertion on Private Disk
        $disk = Storage::disk('private');
        $resolvedPath = !empty($attachmentModel->storage_path) ? $attachmentModel->storage_path : ($attachmentModel->file_path ?? '');

        if (empty($resolvedPath) || !$disk->exists($resolvedPath)) {
            abort(404, 'The requested document does not exist on the storage disk.');
        }

        // 5. Decrypt or Read Payload from Encrypted Storage
        try {
            $payload = $this->storageService->get($resolvedPath);
        } catch (\Throwable) {
            $payload = $disk->get($resolvedPath);
        }

        // 6. Record Immutable Audit Log (DEC-11 / D-11)
        AuditLog::create([
            'workspace_id' => $member->workspace_id,
            'actor_id' => $user->id,
            'actor_guard' => 'member',
            'event_type' => 'portal_document_accessed',
            'auditable_type' => LeadAttachment::class,
            'auditable_id' => $attachmentModel->id,
            'old_values' => null,
            'new_values' => [
                'attachment_id' => $attachmentModel->id,
                'original_filename' => $attachmentModel->original_filename,
                'mime_type' => $attachmentModel->mime_type,
                'size_bytes' => $attachmentModel->size_bytes,
                'document_type' => $attachmentModel->document_type ?? null,
            ],
            'reason' => 'Member portal secure document download.',
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'user_agent' => $request->userAgent() ?? 'Member Portal / Browser',
            'created_at' => now(),
        ]);

        // 7. Secure Streamed Response with Security Headers
        return response()->stream(
            function () use ($payload) {
                echo $payload;
            },
            200,
            [
                'Content-Type' => $attachmentModel->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="' . addslashes($attachmentModel->original_filename) . '"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
            ]
        );
    }
}
