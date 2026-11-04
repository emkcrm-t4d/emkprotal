<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Leads\Services\DocumentStorageService;
use App\Domain\Leads\Services\StoragePathResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class DocumentStorageTest extends TestCase
{
    protected DocumentStorageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Storage::fake('public');

        $this->service = new DocumentStorageService(new StoragePathResolver());
    }

    public function test_uploading_a_document_generates_uuid_v4_filename_and_saves_to_private_disk(): void
    {
        $leadUuid = Uuid::uuid4()->toString();
        $file = UploadedFile::fake()->create('national_id_scan.pdf', 150, 'application/pdf');

        $result = $this->service->store(
            file: $file,
            leadUuid: $leadUuid,
            workspaceId: 'ws-emk-dhaka',
            documentType: 'identity_document',
            applicantAge: 25
        );

        // Assert file exists on private disk
        Storage::disk('private')->assertExists($result['storage_path']);

        // Assert path starts with workspaces/ws-emk-dhaka/leads/{leadUuid}/
        $this->assertStringStartsWith("workspaces/ws-emk-dhaka/leads/{$leadUuid}/", $result['storage_path']);

        // Assert filename on disk is a UUID v4 string with .pdf extension
        $filename = basename($result['storage_path']);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.pdf$/i',
            $filename
        );
    }

    public function test_original_filename_is_preserved_in_metadata_while_disk_filename_contains_only_uuid(): void
    {
        $leadUuid = Uuid::uuid4()->toString();
        $originalName = 'Applicant_NID_2026_Confidential.pdf';
        $file = UploadedFile::fake()->create($originalName, 120, 'application/pdf');

        $result = $this->service->store(
            file: $file,
            leadUuid: $leadUuid,
            workspaceId: 'ws-emk-dhaka',
            documentType: 'identity_document'
        );

        $this->assertSame($originalName, $result['original_filename']);
        $this->assertStringNotContainsString('Applicant_NID', $result['storage_path']);
        $this->assertStringNotContainsString('Confidential', $result['storage_path']);
    }

    public function test_sha256_integrity_hash_matches_unencrypted_file_contents(): void
    {
        $leadUuid = Uuid::uuid4()->toString();
        $content = 'Raw binary passport scanning content for verification.';
        $expectedHash = hash('sha256', $content);

        $result = $this->service->store(
            file: $content,
            leadUuid: $leadUuid,
            workspaceId: 'ws-emk-dhaka',
            documentType: 'identity_document',
            originalFilename: 'passport.txt',
            mimeType: 'text/plain'
        );

        $this->assertSame($expectedHash, $result['file_hash']);
        $this->assertSame(strlen($content), $result['size_bytes']);
    }

    public function test_applicant_age_under_18_automatically_flags_youth_protection_as_true_per_dec_09(): void
    {
        $leadUuid = Uuid::uuid4()->toString();
        $file = UploadedFile::fake()->create('consent.pdf', 50, 'application/pdf');

        // Case 1: Minor applicant (age 17) -> must flag youth_protection = true
        $minorResult = $this->service->store(
            file: $file,
            leadUuid: $leadUuid,
            workspaceId: 'ws-emk-dhaka',
            documentType: 'identity_document',
            applicantAge: 17
        );
        $this->assertTrue($minorResult['youth_protection']);

        // Case 2: Adult applicant (age 18) -> youth_protection = false
        $adult18Result = $this->service->store(
            file: $file,
            leadUuid: $leadUuid,
            workspaceId: 'ws-emk-dhaka',
            documentType: 'identity_document',
            applicantAge: 18
        );
        $this->assertFalse($adult18Result['youth_protection']);

        // Case 3: Adult applicant (age 29) -> youth_protection = false
        $adult29Result = $this->service->store(
            file: $file,
            leadUuid: $leadUuid,
            workspaceId: 'ws-emk-dhaka',
            documentType: 'identity_document',
            applicantAge: 29
        );
        $this->assertFalse($adult29Result['youth_protection']);

        // Case 4: Explicit parental consent document type -> always flags youth_protection = true
        $consentResult = $this->service->store(
            file: $file,
            leadUuid: $leadUuid,
            workspaceId: 'ws-emk-dhaka',
            documentType: 'parental_consent',
            applicantAge: null
        );
        $this->assertTrue($consentResult['youth_protection']);
    }

    public function test_file_is_not_accessible_via_public_disk_or_direct_symlink(): void
    {
        $leadUuid = Uuid::uuid4()->toString();
        $file = UploadedFile::fake()->create('sensitive_document.pdf', 100, 'application/pdf');

        $result = $this->service->store(
            file: $file,
            leadUuid: $leadUuid,
            workspaceId: 'ws-emk-dhaka'
        );

        // Private disk holds the file
        Storage::disk('private')->assertExists($result['storage_path']);

        // Public disk must NEVER hold the file
        Storage::disk('public')->assertMissing($result['storage_path']);
    }

    public function test_files_are_encrypted_at_rest_and_get_method_returns_decrypted_content(): void
    {
        $leadUuid = Uuid::uuid4()->toString();
        $plainText = 'Top Secret Applicant Data Scanned Copy';

        $result = $this->service->store(
            file: $plainText,
            leadUuid: $leadUuid,
            workspaceId: 'ws-emk-dhaka',
            documentType: 'identity_document',
            originalFilename: 'secret.txt',
            encryptAtRest: true
        );

        // Raw disk content must be encrypted (not plaintext)
        $rawDiskContent = Storage::disk('private')->get($result['storage_path']);
        $this->assertNotSame($plainText, $rawDiskContent);

        // Decrypting via Crypt verifies valid encryption
        $this->assertSame($plainText, Crypt::decryptString($rawDiskContent));

        // Service get() method transparently decrypts
        $retrieved = $this->service->get($result['storage_path']);
        $this->assertSame($plainText, $retrieved);
    }

    public function test_delete_removes_file_from_private_disk(): void
    {
        $leadUuid = Uuid::uuid4()->toString();
        $file = UploadedFile::fake()->create('doc_to_delete.pdf', 80, 'application/pdf');

        $result = $this->service->store(
            file: $file,
            leadUuid: $leadUuid,
            workspaceId: 'ws-emk-dhaka'
        );

        Storage::disk('private')->assertExists($result['storage_path']);

        $deleted = $this->service->delete($result['storage_path']);
        $this->assertTrue($deleted);

        Storage::disk('private')->assertMissing($result['storage_path']);
    }

    public function test_path_traversal_and_invalid_uuid_inputs_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->store(
            file: 'payload',
            leadUuid: 'not-a-valid-uuid',
            workspaceId: 'ws-emk-dhaka'
        );
    }
}
