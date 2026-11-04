<?php

declare(strict_types=1);

namespace App\Domain\Leads\Services;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

class StoragePathResolver
{
    /**
     * Resolve private storage path for a lead attachment.
     *
     * Standard layout: workspaces/{workspaceId}/leads/{leadUuid}/{fileUuid}.{extension}
     *
     * @throws InvalidArgumentException
     */
    public function resolveLeadAttachmentPath(
        string $workspaceId,
        string $leadUuid,
        string $fileUuid,
        string $extension
    ): string {
        $this->validateWorkspaceId($workspaceId);
        $this->validateUuid($leadUuid, 'leadUuid');
        $this->validateUuid($fileUuid, 'fileUuid');
        $cleanExt = $this->sanitizeExtension($extension);

        return "workspaces/{$workspaceId}/leads/{$leadUuid}/{$fileUuid}.{$cleanExt}";
    }

    /**
     * Resolve private storage path for an unassigned staging pool lead attachment.
     *
     * Standard layout: workspaces/pool/leads/{leadUuid}/{fileUuid}.{extension}
     *
     * @throws InvalidArgumentException
     */
    public function resolvePoolAttachmentPath(
        string $leadUuid,
        string $fileUuid,
        string $extension
    ): string {
        $this->validateUuid($leadUuid, 'leadUuid');
        $this->validateUuid($fileUuid, 'fileUuid');
        $cleanExt = $this->sanitizeExtension($extension);

        return "workspaces/pool/leads/{$leadUuid}/{$fileUuid}.{$cleanExt}";
    }

    /**
     * Resolve private storage path for a member attachment.
     *
     * Standard layout: workspaces/{workspaceId}/members/{memberUuid}/{fileUuid}.{extension}
     *
     * @throws InvalidArgumentException
     */
    public function resolveMemberAttachmentPath(
        string $workspaceId,
        string $memberUuid,
        string $fileUuid,
        string $extension
    ): string {
        $this->validateWorkspaceId($workspaceId);
        $this->validateUuid($memberUuid, 'memberUuid');
        $this->validateUuid($fileUuid, 'fileUuid');
        $cleanExt = $this->sanitizeExtension($extension);

        return "workspaces/{$workspaceId}/members/{$memberUuid}/{$fileUuid}.{$cleanExt}";
    }

    /**
     * Assert valid workspace identifier with zero path traversal tokens.
     *
     * @throws InvalidArgumentException
     */
    protected function validateWorkspaceId(string $workspaceId): void
    {
        $this->assertNoTraversal($workspaceId, 'workspaceId');

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $workspaceId)) {
            throw new InvalidArgumentException("Invalid workspace identifier [{$workspaceId}]. Must match alphanumeric, underscore, or hyphen characters.");
        }
    }

    /**
     * Assert valid UUID format with zero path traversal tokens.
     *
     * @throws InvalidArgumentException
     */
    protected function validateUuid(string $uuid, string $paramName): void
    {
        $this->assertNoTraversal($uuid, $paramName);

        if (!Uuid::isValid($uuid)) {
            throw new InvalidArgumentException("Invalid UUID format provided for [{$paramName}]: '{$uuid}'.");
        }
    }

    /**
     * Sanitize and validate file extension with zero path traversal tokens.
     *
     * @throws InvalidArgumentException
     */
    protected function sanitizeExtension(string $extension): string
    {
        $this->assertNoTraversal($extension, 'extension');

        $clean = ltrim(trim($extension), '.');

        if ($clean === '' || !preg_match('/^[a-zA-Z0-9]+$/', $clean)) {
            throw new InvalidArgumentException("Invalid file extension [{$extension}]. Must be non-empty alphanumeric.");
        }

        return strtolower($clean);
    }

    /**
     * Enforce strict rejection of path traversal tokens and null bytes.
     *
     * @throws InvalidArgumentException
     */
    protected function assertNoTraversal(string $value, string $paramName): void
    {
        if (str_contains($value, "\0")) {
            throw new InvalidArgumentException("Null byte injection detected in parameter [{$paramName}].");
        }

        if (str_contains($value, '..') || str_contains($value, '/') || str_contains($value, '\\')) {
            throw new InvalidArgumentException("Path traversal sequence detected in parameter [{$paramName}].");
        }
    }
}
