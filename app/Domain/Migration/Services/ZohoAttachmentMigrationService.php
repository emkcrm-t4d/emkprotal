<?php

declare(strict_types=1);

namespace App\Domain\Migration\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Leads\Services\DocumentStorageService;
use App\Domain\Members\Models\Member;
use App\Domain\Migration\Exceptions\SecurityException;
use Carbon\Carbon;
use finfo;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ZohoAttachmentMigrationService
{
    /**
     * Whitelisted extensions for legacy Zoho attachments.
     *
     * @var list<string>
     */
    protected const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf'];

    /**
     * Whitelisted MIME types for legacy Zoho attachments.
     *
     * @var list<string>
     */
    protected const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'application/pdf',
    ];

    /**
     * Explicitly prohibited execution-capable file extensions.
     *
     * @var list<string>
     */
    protected const PROHIBITED_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar',
        'exe', 'sh', 'bash', 'bat', 'cmd', 'ps1', 'cgi', 'pl', 'py', 'js',
    ];

    /**
     * Whitelisted document type enum values for LeadAttachment.
     *
     * @var list<string>
     */
    protected const ALLOWED_DOCUMENT_TYPES = [
        'identity_document',
        'studio_photo',
        'parental_consent',
    ];

    public function __construct(
        protected DocumentStorageService $documentStorageService
    ) {}

    /**
     * Sanitize relative path and protect against path traversal and null byte injection per T-11-04.
     *
     * @throws SecurityException
     */
    public function sanitizeRelativePath(string $baseDir, string $relativePath): string
    {
        // 1. Check for null bytes (raw or url-encoded)
        if (str_contains($relativePath, "\0") || str_contains($relativePath, '%00') || str_contains(urldecode($relativePath), "\0")) {
            throw new SecurityException("Null byte injection detected in path: [{$relativePath}].");
        }

        // 2. Reject path traversal tokens
        $decoded = urldecode($relativePath);
        if (
            str_contains($relativePath, '..') || str_contains($decoded, '..') ||
            str_starts_with($relativePath, './') || str_starts_with($relativePath, '.\\') ||
            str_starts_with($relativePath, '/') || str_starts_with($relativePath, '\\') ||
            preg_match('/^[a-zA-Z]:[\\\\\/]/', $relativePath)
        ) {
            throw new SecurityException("Path traversal sequence detected in relative path: [{$relativePath}].");
        }

        $realBase = realpath($baseDir);
        if ($realBase === false || !is_dir($realBase)) {
            throw new SecurityException("Base directory [{$baseDir}] does not exist or is not a directory.");
        }

        // 3. Construct canonical candidate path
        $normalizedBase = rtrim($realBase, DIRECTORY_SEPARATOR . '/');
        $normalizedRelative = ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath), DIRECTORY_SEPARATOR);
        $fullPath = $normalizedBase . DIRECTORY_SEPARATOR . $normalizedRelative;

        if (file_exists($fullPath)) {
            $realPath = realpath($fullPath);
            if ($realPath === false) {
                throw new SecurityException("Unable to resolve real path for [{$fullPath}].");
            }

            // Verify realpath starts strictly within normalizedBase directory boundary
            $basePrefix = $normalizedBase . DIRECTORY_SEPARATOR;
            if (!str_starts_with($realPath, $basePrefix) && $realPath !== $normalizedBase) {
                throw new SecurityException("Resolved path [{$realPath}] escapes base directory boundary [{$normalizedBase}].");
            }

            return $realPath;
        }

        // For non-existent target files, check parent directory resolution
        $parentDir = dirname($fullPath);
        $realParent = realpath($parentDir);
        if ($realParent !== false) {
            $basePrefix = $normalizedBase . DIRECTORY_SEPARATOR;
            if (!str_starts_with($realParent, $basePrefix) && $realParent !== $normalizedBase) {
                throw new SecurityException("Resolved directory [{$realParent}] escapes base directory boundary [{$normalizedBase}].");
            }
        }

        return $fullPath;
    }

    /**
     * Validate file security: whitelist extensions, MIME types, and magic bytes per T-11-05.
     *
     * @return array{extension: string, mime_type: string, raw_content: string}
     *
     * @throws SecurityException|InvalidArgumentException
     */
    public function validateFileSecurity(string $sourceFilePath): array
    {
        if (!file_exists($sourceFilePath) || !is_readable($sourceFilePath)) {
            throw new InvalidArgumentException("Source file [{$sourceFilePath}] does not exist or is not readable.");
        }

        $filename = basename($sourceFilePath);
        $lowerFilename = strtolower($filename);

        // Check for compound dangerous extensions like .blade.php
        if (str_ends_with($lowerFilename, '.blade.php')) {
            throw new SecurityException("Prohibited executable file extension detected: [{$filename}].");
        }

        $extension = strtolower(pathinfo($sourceFilePath, PATHINFO_EXTENSION));

        // Check prohibited dangerous extensions
        if (in_array($extension, self::PROHIBITED_EXTENSIONS, true)) {
            throw new SecurityException("Prohibited executable file extension [{$extension}] detected: [{$filename}].");
        }

        // Check whitelist
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new SecurityException("Unsupported file extension [{$extension}]. Allowed extensions: " . implode(', ', self::ALLOWED_EXTENSIONS));
        }

        $rawContent = file_get_contents($sourceFilePath);
        if ($rawContent === false) {
            throw new InvalidArgumentException("Failed to read contents of source file [{$sourceFilePath}].");
        }

        // Validate MIME type via fileinfo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->buffer($rawContent);

        if ($detectedMime === false || !in_array($detectedMime, self::ALLOWED_MIME_TYPES, true)) {
            throw new SecurityException("Invalid MIME type [{$detectedMime}]. Allowed MIME types: " . implode(', ', self::ALLOWED_MIME_TYPES));
        }

        // Validate magic bytes against MIME type and extension
        $this->validateMagicBytes($rawContent, $detectedMime, $extension);

        return [
            'extension' => $extension,
            'mime_type' => $detectedMime,
            'raw_content' => $rawContent,
        ];
    }

    /**
     * Verify header magic bytes match file format.
     *
     * @throws SecurityException
     */
    public function validateMagicBytes(string $content, string $mimeType, string $extension): void
    {
        if ($mimeType === 'image/jpeg' || in_array($extension, ['jpg', 'jpeg'], true)) {
            // JPEG starts with FF D8 FF
            if (strlen($content) < 3 || substr($content, 0, 3) !== "\xFF\xD8\xFF") {
                throw new SecurityException("Magic bytes mismatch for JPEG image.");
            }
        } elseif ($mimeType === 'image/png' || $extension === 'png') {
            // PNG starts with 89 50 4E 47 0D 0A 1A 0A
            if (strlen($content) < 8 || substr($content, 0, 8) !== "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A") {
                throw new SecurityException("Magic bytes mismatch for PNG image.");
            }
        } elseif ($mimeType === 'application/pdf' || $extension === 'pdf') {
            // PDF starts with %PDF- (25 50 44 46 2D)
            if (strlen($content) < 5 || substr($content, 0, 5) !== '%PDF-') {
                throw new SecurityException("Magic bytes mismatch for PDF document.");
            }
        } else {
            throw new SecurityException("Unsupported file format for magic bytes verification.");
        }
    }

    /**
     * Strip EXIF metadata from studio photos and JPEG scans to eliminate GPS coordinates and device identifiers.
     */
    public function stripExifMetadata(string $content, string $mimeType): string
    {
        if ($mimeType !== 'image/jpeg') {
            return $content;
        }

        if (function_exists('imagecreatefromstring')) {
            $image = @imagecreatefromstring($content);
            if ($image !== false) {
                ob_start();
                imagejpeg($image, null, 95);
                $cleanContent = ob_get_clean();
                imagedestroy($image);

                if ($cleanContent !== false && strlen($cleanContent) > 0) {
                    return $cleanContent;
                }
            }
        }

        return $content;
    }

    /**
     * Migrate an individual legacy attachment, encrypt on private disk, and associate with target Member/Lead.
     *
     * @param  string  $sourceFilePath  Path to source file on disk
     * @param  string  $zohoId  Zoho Record ID (matches Member or Lead)
     * @param  string  $documentType  Type: 'identity_document', 'studio_photo', or 'parental_consent'
     * @param  string  $workspaceId  Target workspace identifier
     * @return LeadAttachment
     *
     * @throws SecurityException|InvalidArgumentException
     */
    public function migrateAttachment(
        string $sourceFilePath,
        string $zohoId,
        string $documentType,
        string $workspaceId = 'ws-emk-dhaka'
    ): LeadAttachment {
        // 1. Validate document type enum
        if (!in_array($documentType, self::ALLOWED_DOCUMENT_TYPES, true)) {
            throw new InvalidArgumentException("Invalid document type [{$documentType}]. Allowed types: " . implode(', ', self::ALLOWED_DOCUMENT_TYPES));
        }

        // 2. Security validation: extension, MIME type, and magic bytes
        $securityMeta = $this->validateFileSecurity($sourceFilePath);

        // 3. Strip EXIF metadata for JPEGs
        $processedContent = $this->stripExifMetadata($securityMeta['raw_content'], $securityMeta['mime_type']);

        // 4. Lookup target entity: check members first, then leads
        $member = Member::where('zoho_id', $zohoId)->first();
        $lead = null;
        $applicantAge = null;

        if ($member) {
            $lead = Lead::find($member->lead_id);
            if ($member->date_of_birth) {
                $applicantAge = Carbon::parse($member->date_of_birth)->age;
            } elseif ($lead && $lead->age_at_submission !== null) {
                $applicantAge = (int) $lead->age_at_submission;
            } elseif ($lead && $lead->date_of_birth) {
                $applicantAge = Carbon::parse($lead->date_of_birth)->age;
            }
            $targetWorkspace = $workspaceId ?: ($member->workspace_id ?: ($lead?->workspace_id ?? 'ws-emk-dhaka'));
        } else {
            $lead = Lead::where('zoho_id', $zohoId)->first();
            if ($lead) {
                if ($lead->date_of_birth) {
                    $applicantAge = Carbon::parse($lead->date_of_birth)->age;
                } elseif ($lead->age_at_submission !== null) {
                    $applicantAge = (int) $lead->age_at_submission;
                }
                $targetWorkspace = $workspaceId ?: ($lead->workspace_id ?? 'ws-emk-dhaka');
            }
        }

        if (!$lead) {
            throw new InvalidArgumentException("No member or lead record found for Zoho ID [{$zohoId}].");
        }

        // 5. Encrypt at rest via DocumentStorageService on private disk
        $originalFilename = basename($sourceFilePath);
        $storedMeta = $this->documentStorageService->store(
            file: $processedContent,
            leadUuid: $lead->uuid,
            workspaceId: $targetWorkspace,
            documentType: $documentType,
            applicantAge: $applicantAge,
            originalFilename: $originalFilename,
            mimeType: $securityMeta['mime_type'],
            encryptAtRest: true
        );

        // 6. Create LeadAttachment database record
        $attachment = LeadAttachment::create([
            'workspace_id' => $targetWorkspace,
            'lead_id' => $lead->id,
            'document_type' => $documentType,
            'storage_path' => $storedMeta['storage_path'],
            'original_filename' => $storedMeta['original_filename'],
            'mime_type' => $storedMeta['mime_type'],
            'size_bytes' => $storedMeta['size_bytes'],
            'file_hash' => $storedMeta['file_hash'],
            'youth_protection' => $storedMeta['youth_protection'],
        ]);

        // 7. Write immutable audit log entry per DEC-11
        AuditLog::create([
            'workspace_id' => $targetWorkspace,
            'actor_id' => Auth::id(),
            'actor_guard' => 'system',
            'event_type' => AuditLog::EVENT_ZOHO_ATTACHMENT_MIGRATED,
            'auditable_type' => LeadAttachment::class,
            'auditable_id' => $attachment->id,
            'new_values' => [
                'attachment_id' => $attachment->id,
                'zoho_id' => $zohoId,
                'lead_id' => $lead->id,
                'document_type' => $documentType,
                'storage_path' => $storedMeta['storage_path'],
                'original_filename' => $storedMeta['original_filename'],
                'file_hash' => $storedMeta['file_hash'],
                'youth_protection' => $storedMeta['youth_protection'],
            ],
            'reason' => "Migrated legacy Zoho attachment for record [{$zohoId}] (document type: {$documentType}).",
            'ip_address' => request()?->ip() ?? '127.0.0.1',
            'user_agent' => request()?->userAgent() ?? 'Artisan CLI / Attachment Migration Service',
            'created_at' => Carbon::now(),
        ]);

        return $attachment;
    }
}
