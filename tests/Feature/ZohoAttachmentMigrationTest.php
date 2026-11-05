<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Leads\Services\DocumentStorageService;
use App\Domain\Members\Models\Member;
use App\Domain\Migration\Exceptions\SecurityException;
use App\Domain\Migration\Services\ZohoAttachmentMigrationService;
use App\Domain\Workspaces\Models\Workspace;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class ZohoAttachmentMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected ZohoAttachmentMigrationService $migrationService;
    protected DocumentStorageService $documentStorageService;
    protected string $workspaceId = 'ws-emk-dhaka';
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->documentStorageService = app(DocumentStorageService::class);
        $this->migrationService = app(ZohoAttachmentMigrationService::class);

        Storage::fake('private');

        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'zoho_attach_test_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    /**
     * Create a realistic minimal 1x1 JPEG image file.
     */
    protected function createSampleJpeg(string $filename, string $markerText = ''): string
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . $filename;
        $im = imagecreatetruecolor(10, 10);
        $bg = imagecolorallocate($im, 255, 0, 0);
        imagefilledrectangle($im, 0, 0, 9, 9, $bg);
        imagejpeg($im, $path, 90);
        imagedestroy($im);

        return $path;
    }

    /**
     * Create a realistic minimal PNG image file.
     */
    protected function createSamplePng(string $filename): string
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . $filename;
        $im = imagecreatetruecolor(10, 10);
        $bg = imagecolorallocate($im, 0, 255, 0);
        imagefilledrectangle($im, 0, 0, 9, 9, $bg);
        imagepng($im, $path);
        imagedestroy($im);

        return $path;
    }

    /**
     * Create a realistic minimal PDF file.
     */
    protected function createSamplePdf(string $filename, string $content = 'Sample PDF Document'): string
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . $filename;
        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n" .
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n" .
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n" .
            "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream\nendobj\n" .
            "xref\n0 5\n0000000000 65535 f \ntrailer\n<< /Root 1 0 R /Size 5 >>\nstartxref\n180\n%%EOF";
        file_put_contents($path, $pdfContent);

        return $path;
    }

    protected function createLeadWithZohoId(string $zohoId, int $age = 25, string $workspaceId = 'ws-emk-dhaka'): Lead
    {
        return Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'target_space_code' => 'EMK',
            'status' => 'submitted',
            'bkash_txid' => 'TXID' . rand(100000, 999999),
            'first_name' => 'Applicant',
            'last_name' => 'Test',
            'email' => 'lead.' . uniqid() . '@example.com',
            'contact_number' => '+8801700000000',
            'date_of_birth' => now()->subYears($age)->format('Y-m-d'),
            'age_at_submission' => $age,
            'sex' => 'male',
            'address_house_no' => '12',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'student',
            'institution_study' => 'University of Dhaka',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Computer Science',
            'submitted_at' => now(),
            'zoho_id' => $zohoId,
        ]);
    }

    protected function createMemberWithZohoId(string $zohoId, int $age = 22, string $workspaceId = 'ws-emk-dhaka'): Member
    {
        $lead = $this->createLeadWithZohoId('L-' . uniqid(), $age, $workspaceId);
        $user = User::create([
            'workspace_id' => $workspaceId,
            'name' => 'Member User',
            'email' => 'user.' . uniqid() . '@example.com',
            'password' => bcrypt('secret123'),
            'guard' => 'member',
            'is_active' => true,
        ]);
        $term = MembershipTerm::first() ?? MembershipTerm::create([
            'workspace_id' => $workspaceId,
            'code' => 'TERM-1YR',
            'name' => '1 Year',
            'duration_months' => 12,
            'fee_bdt' => 500,
            'is_active' => true,
        ]);

        return Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => 'EMK-2024-' . rand(1000, 9999),
            'workspace_id' => $workspaceId,
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'membership_term_id' => $term->id,
            'status' => 'active',
            'first_name' => 'Member',
            'last_name' => 'Test',
            'email' => 'member.' . uniqid() . '@example.com',
            'contact_number' => '+8801711111111',
            'date_of_birth' => now()->subYears($age)->format('Y-m-d'),
            'sex' => 'female',
            'address_house_no' => '42',
            'address_area' => 'Banani',
            'address_zip_code' => '1213',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'professional',
            'institution_study' => 'BUET',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Engineering',
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'zoho_id' => $zohoId,
        ]);
    }

    public function test_valid_attachment_migration_for_lead_and_member(): void
    {
        $lead = $this->createLeadWithZohoId('ZOHO-LEAD-001', 25);
        $member = $this->createMemberWithZohoId('ZOHO-MEM-002', 30);

        $pdfPath = $this->createSamplePdf('nid_scan.pdf', 'Official NID Document Payload');
        $jpgPath = $this->createSampleJpeg('studio_photo.jpg');

        // 1. Migrate PDF for lead
        $leadAttach = $this->migrationService->migrateAttachment(
            $pdfPath,
            'ZOHO-LEAD-001',
            'identity_document',
            $this->workspaceId
        );

        $this->assertInstanceOf(LeadAttachment::class, $leadAttach);
        $this->assertEquals($lead->id, $leadAttach->lead_id);
        $this->assertEquals('identity_document', $leadAttach->document_type);
        $this->assertEquals('nid_scan.pdf', $leadAttach->original_filename);
        $this->assertEquals('application/pdf', $leadAttach->mime_type);
        $this->assertFalse($leadAttach->youth_protection);

        // 2. Migrate studio photo for member (links to member's lead_id)
        $memberAttach = $this->migrationService->migrateAttachment(
            $jpgPath,
            'ZOHO-MEM-002',
            'studio_photo',
            $this->workspaceId
        );

        $this->assertInstanceOf(LeadAttachment::class, $memberAttach);
        $this->assertEquals($member->lead_id, $memberAttach->lead_id);
        $this->assertEquals('studio_photo', $memberAttach->document_type);
        $this->assertEquals('studio_photo.jpg', $memberAttach->original_filename);
        $this->assertEquals('image/jpeg', $memberAttach->mime_type);
        $this->assertFalse($memberAttach->youth_protection);

        // 3. Verify audit log entry
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditLog::EVENT_ZOHO_ATTACHMENT_MIGRATED,
            'auditable_type' => LeadAttachment::class,
            'auditable_id' => $leadAttach->id,
        ]);
    }

    public function test_files_are_stored_encrypted_on_private_disk_with_uuid_filenames(): void
    {
        $lead = $this->createLeadWithZohoId('ZOHO-ENC-001', 28);
        $secretText = 'Confidential Passport Identification Data 12345678';
        $pdfPath = $this->createSamplePdf('passport.pdf', $secretText);

        $attachment = $this->migrationService->migrateAttachment(
            $pdfPath,
            'ZOHO-ENC-001',
            'identity_document',
            $this->workspaceId
        );

        $disk = Storage::disk('private');

        // File exists on private disk
        $this->assertTrue($disk->exists($attachment->storage_path));

        // Storage path contains UUID v4, NOT the original filename
        $basename = basename($attachment->storage_path);
        $this->assertStringNotContainsString('passport', $basename);
        $uuidPart = pathinfo($basename, PATHINFO_FILENAME);
        $this->assertTrue(Str::isUuid($uuidPart), "Filename [{$basename}] is not a UUID.");

        // Read raw content directly from disk: must be encrypted ciphertext
        $rawDiskPayload = $disk->get($attachment->storage_path);
        $this->assertStringNotContainsString($secretText, $rawDiskPayload, 'Secret text was stored as unencrypted plain text on disk!');

        // Decryption using Crypt::decryptString succeeds and matches original content
        $decrypted = Crypt::decryptString($rawDiskPayload);
        $this->assertStringContainsString($secretText, $decrypted);

        // DocumentStorageService::get decrypts correctly
        $retrieved = $this->documentStorageService->get($attachment->storage_path);
        $this->assertStringContainsString($secretText, $retrieved);
    }

    public function test_path_traversal_attacks_are_trapped_and_rejected(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Path traversal sequence detected');

        $this->migrationService->sanitizeRelativePath($this->tempDir, '../../etc/passwd');
    }

    public function test_windows_path_traversal_attacks_are_trapped_and_rejected(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Path traversal sequence detected');

        $this->migrationService->sanitizeRelativePath($this->tempDir, '..\\windows\\win.ini');
    }

    public function test_null_byte_injection_is_trapped_and_rejected(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Null byte injection detected');

        $this->migrationService->sanitizeRelativePath($this->tempDir, "sample.pdf\0.php");
    }

    public function test_url_encoded_null_byte_is_trapped_and_rejected(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Null byte injection detected');

        $this->migrationService->sanitizeRelativePath($this->tempDir, "sample.pdf%00.exe");
    }

    public function test_absolute_path_traversal_is_rejected(): void
    {
        $this->expectException(SecurityException::class);

        $this->migrationService->sanitizeRelativePath($this->tempDir, '/etc/shadow');
    }

    public function test_executable_file_uploads_are_rejected(): void
    {
        $phpFile = $this->tempDir . DIRECTORY_SEPARATOR . 'webshell.php';
        file_put_contents($phpFile, '<?php phpinfo(); ?>');

        $lead = $this->createLeadWithZohoId('ZOHO-EXE-001');

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Prohibited executable file extension');

        $this->migrationService->migrateAttachment($phpFile, 'ZOHO-EXE-001', 'identity_document');
    }

    public function test_spoofed_file_extension_fails_magic_bytes_check(): void
    {
        // File named .jpg but contains PHP code without JPEG magic bytes
        $spoofedFile = $this->tempDir . DIRECTORY_SEPARATOR . 'fake_image.jpg';
        file_put_contents($spoofedFile, '<?php echo "Hacked"; ?>');

        $lead = $this->createLeadWithZohoId('ZOHO-SPOOF-001');

        $this->expectException(SecurityException::class);

        $this->migrationService->migrateAttachment($spoofedFile, 'ZOHO-SPOOF-001', 'identity_document');
    }

    public function test_youth_protection_flag_is_accurately_assigned_to_minors(): void
    {
        // 1. Applicant age 16 (< 18)
        $minorLead = $this->createLeadWithZohoId('ZOHO-MINOR-01', 16);
        $pdfPath = $this->createSamplePdf('minor_consent.pdf');

        $minorAttachment = $this->migrationService->migrateAttachment(
            $pdfPath,
            'ZOHO-MINOR-01',
            'identity_document',
            $this->workspaceId
        );

        $this->assertTrue($minorAttachment->youth_protection, 'Minor applicant attachment must have youth_protection = true');

        // 2. Parental consent document type automatically triggers youth_protection regardless of age
        $adultLead = $this->createLeadWithZohoId('ZOHO-ADULT-01', 25);
        $consentPdf = $this->createSamplePdf('parental_consent.pdf');

        $consentAttachment = $this->migrationService->migrateAttachment(
            $consentPdf,
            'ZOHO-ADULT-01',
            'parental_consent',
            $this->workspaceId
        );

        $this->assertTrue($consentAttachment->youth_protection, 'Parental consent document must have youth_protection = true');
    }

    public function test_unmapped_zoho_id_throws_invalid_argument_exception(): void
    {
        $pdfPath = $this->createSamplePdf('orphan.pdf');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No member or lead record found for Zoho ID [ZOHO-ORPHAN-999]');

        $this->migrationService->migrateAttachment($pdfPath, 'ZOHO-ORPHAN-999', 'identity_document');
    }
}
