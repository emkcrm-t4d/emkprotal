<?php

declare(strict_types=1);

namespace App\Domain\Leads\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Throwable;

class DocumentStorageService
{
    public function __construct(
        protected StoragePathResolver $pathResolver
    ) {}

    /**
     * Store an uploaded document or raw payload on the encrypted private filesystem disk.
     *
     * Invariants enforced:
     * - Physical filename masked with cryptographic UUID v4 per ARCH-06 and DEC-09.
     * - Client filename preserved strictly in metadata, never on disk.
     * - SHA-256 integrity hash computed over unencrypted file payload.
     * - Encrypted at rest via AES-256 (Crypt::encryptString) on private disk.
     * - Automatically flags youth_protection = true for applicants < 18 years per DEC-09.
     *
     * @param  UploadedFile|string  $file  Uploaded file instance or raw binary content
     * @param  string  $leadUuid  Valid UUID v4 for the associated lead
     * @param  string|null  $workspaceId  Tenant workspace identifier ('ws-emk-dhaka') or null for pool
     * @param  string  $documentType  Type: 'identity_document', 'studio_photo', or 'parental_consent'
     * @param  int|null  $applicantAge  Applicant age in years
     * @param  string|null  $originalFilename  Original filename (mandatory if raw string passed)
     * @param  string|null  $mimeType  MIME type (optional, auto-detected when possible)
     * @param  bool  $encryptAtRest  Whether to apply AES-256 encryption at rest (default: true)
     * @return array{
     *     storage_path: string,
     *     original_filename: string,
     *     mime_type: string,
     *     size_bytes: int,
     *     file_hash: string,
     *     youth_protection: bool
     * }
     *
     * @throws InvalidArgumentException|Throwable
     */
    public function store(
        UploadedFile|string $file,
        string $leadUuid,
        ?string $workspaceId = 'ws-emk-dhaka',
        string $documentType = 'identity_document',
        ?int $applicantAge = null,
        ?string $originalFilename = null,
        ?string $mimeType = null,
        bool $encryptAtRest = true
    ): array {
        if (!Uuid::isValid($leadUuid)) {
            throw new InvalidArgumentException("Invalid lead UUID format: '{$leadUuid}'.");
        }

        if ($file instanceof UploadedFile) {
            $rawContents = $file->getContent();
            $resolvedOriginal = $originalFilename ?? $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'bin');
            $resolvedMime = $mimeType ?? ($file->getClientMimeType() ?: 'application/octet-stream');
            $sizeBytes = $file->getSize() ?: strlen($rawContents);
        } else {
            $rawContents = $file;
            $resolvedOriginal = $originalFilename ?? 'document.bin';
            $extension = pathinfo($resolvedOriginal, PATHINFO_EXTENSION) ?: 'bin';
            $resolvedMime = $mimeType ?? 'application/octet-stream';
            $sizeBytes = strlen($rawContents);
        }

        // 1. Mask physical filename with cryptographic UUID v4
        $fileUuid = Str::uuid()->toString();

        // 2. Resolve safe namespaced path outside web root
        if ($workspaceId !== null && $workspaceId !== '' && $workspaceId !== 'pool') {
            $storagePath = $this->pathResolver->resolveLeadAttachmentPath(
                $workspaceId,
                $leadUuid,
                $fileUuid,
                $extension
            );
        } else {
            $storagePath = $this->pathResolver->resolvePoolAttachmentPath(
                $leadUuid,
                $fileUuid,
                $extension
            );
        }

        // 3. Compute SHA-256 file integrity hash
        $fileHash = hash('sha256', $rawContents);

        // 4. Determine youth protection flag (DEC-09)
        $youthProtection = ($applicantAge !== null && $applicantAge < 18)
            || $documentType === 'parental_consent';

        // 5. Encrypt at rest and write atomically to private disk
        $disk = Storage::disk('private');
        $payloadToWrite = $encryptAtRest ? Crypt::encryptString($rawContents) : $rawContents;

        try {
            $disk->put($storagePath, $payloadToWrite);
        } catch (Throwable $e) {
            if ($disk->exists($storagePath)) {
                $disk->delete($storagePath);
            }
            throw $e;
        }

        return [
            'storage_path' => $storagePath,
            'original_filename' => $resolvedOriginal,
            'mime_type' => $resolvedMime,
            'size_bytes' => $sizeBytes,
            'file_hash' => $fileHash,
            'youth_protection' => $youthProtection,
        ];
    }

    /**
     * Retrieve and decrypt a file from the private disk.
     *
     * @throws InvalidArgumentException
     */
    public function get(string $path): string
    {
        $disk = Storage::disk('private');

        if (!$disk->exists($path)) {
            throw new InvalidArgumentException("File not found on private storage disk at: [{$path}].");
        }

        $payload = $disk->get($path);

        try {
            return Crypt::decryptString($payload);
        } catch (DecryptException) {
            // Payload was stored without encryption
            return $payload;
        }
    }

    /**
     * Delete a file from the private disk.
     */
    public function delete(string $path): bool
    {
        $disk = Storage::disk('private');

        if (!$disk->exists($path)) {
            return false;
        }

        return $disk->delete($path);
    }

    /**
     * Check if a file exists on the private disk.
     */
    public function exists(string $path): bool
    {
        return Storage::disk('private')->exists($path);
    }
}
