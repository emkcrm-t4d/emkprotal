<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Leads\Services\DocumentStorageService;
use App\Domain\Leads\Services\StoragePathResolver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class DocumentDownloadControllerTest extends TestCase
{
    use RefreshDatabase;

    protected DocumentStorageService $storageService;
    protected User $staffUser;
    protected User $crossWorkspaceUser;
    protected User $superAdminUser;
    protected Lead $lead;
    protected LeadAttachment $standardAttachment;
    protected LeadAttachment $youthAttachment;
    protected string $fileContent = 'Confidential identity document raw stream';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->storageService = new DocumentStorageService(new StoragePathResolver());

        // Configure test Gate permissions
        Gate::before(function (User $user, string $ability) {
            if ($user->isSuperAdmin()) {
                return true;
            }

            if (isset($user->testPermissions) && in_array($ability, $user->testPermissions, true)) {
                return true;
            }

            return null;
        });

        // 1. Seed space & workspaces
        DB::table('spaces')->insert([
            'id' => 1,
            'code' => 'EMK-DHK',
            'name' => 'EMK Center Dhaka',
            'category' => 'Center',
            'division' => 'Dhaka',
            'district' => 'Dhaka',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspaces')->insert([
            ['id' => 'ws-emk-dhaka', 'space_id' => 1, 'name' => 'EMK Center Dhaka', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'ws-corner-rajshahi', 'space_id' => 1, 'name' => 'American Corner Rajshahi', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 2. Seed users
        $this->staffUser = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Staff',
            'email' => 'staff@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staffUser->testPermissions = ['leads.view_documents'];

        $this->crossWorkspaceUser = User::create([
            'workspace_id' => 'ws-corner-rajshahi',
            'name' => 'Rajshahi Staff',
            'email' => 'rajshahi@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->crossWorkspaceUser->testPermissions = ['leads.view_documents', 'leads.view_youth_records'];

        $this->superAdminUser = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Super Administrator',
            'email' => 'superadmin@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->superAdminUser->assignRole('superadmin');

        // 3. Seed lead
        $this->lead = Lead::create([
            'uuid' => Uuid::uuid4()->toString(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'BKASH00001',
            'first_name' => 'Rahim',
            'last_name' => 'Ahmed',
            'email' => 'rahim@example.test',
            'contact_number' => '+8801700000000',
            'date_of_birth' => '2000-01-01',
            'age_at_submission' => 26,
            'sex' => 'male',
            'address_house_no' => '12',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'HSC',
            'area_of_subject' => 'Business',
            'submitted_at' => now(),
        ]);

        // 4. Store standard document
        $storedStandard = $this->storageService->store(
            file: $this->fileContent,
            leadUuid: $this->lead->uuid,
            workspaceId: 'ws-emk-dhaka',
            documentType: 'identity_document',
            originalFilename: 'passport_scan.pdf',
            mimeType: 'application/pdf',
            encryptAtRest: true
        );

        $this->standardAttachment = LeadAttachment::create([
            'lead_id' => $this->lead->id,
            'document_type' => 'identity_document',
            'storage_path' => $storedStandard['storage_path'],
            'original_filename' => $storedStandard['original_filename'],
            'mime_type' => $storedStandard['mime_type'],
            'size_bytes' => $storedStandard['size_bytes'],
            'file_hash' => $storedStandard['file_hash'],
            'youth_protection' => false,
        ]);

        // 5. Store youth document
        $storedYouth = $this->storageService->store(
            file: 'Parental Consent Form for Minor Applicant',
            leadUuid: $this->lead->uuid,
            workspaceId: 'ws-emk-dhaka',
            documentType: 'parental_consent',
            applicantAge: 16,
            originalFilename: 'parental_consent.pdf',
            mimeType: 'application/pdf',
            encryptAtRest: true
        );

        $this->youthAttachment = LeadAttachment::create([
            'lead_id' => $this->lead->id,
            'document_type' => 'parental_consent',
            'storage_path' => $storedYouth['storage_path'],
            'original_filename' => $storedYouth['original_filename'],
            'mime_type' => $storedYouth['mime_type'],
            'size_bytes' => $storedYouth['size_bytes'],
            'file_hash' => $storedYouth['file_hash'],
            'youth_protection' => true,
        ]);
    }

    public function test_authorized_staff_with_leads_view_documents_receives_200_streamed_response(): void
    {
        $response = $this->actingAs($this->staffUser)
            ->get("/workspaces/ws-emk-dhaka/documents/{$this->standardAttachment->id}");

        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('must-revalidate', (string) $response->headers->get('Cache-Control'));

        $this->assertSame($this->fileContent, $response->streamedContent());
    }

    public function test_cross_workspace_staff_receives_403_forbidden(): void
    {
        // Rajshahi staff attempts to download Dhaka lead document
        $response = $this->actingAs($this->crossWorkspaceUser)
            ->get("/workspaces/ws-emk-dhaka/documents/{$this->standardAttachment->id}");

        $response->assertForbidden();
        $this->assertStringContainsString('Cross-workspace', $response->getContent() ?: '');
    }

    public function test_staff_without_leads_view_youth_records_receives_403_when_accessing_youth_documents(): void
    {
        // Staff user has leads.view_documents, but lacks leads.view_youth_records
        $response = $this->actingAs($this->staffUser)
            ->get("/workspaces/ws-emk-dhaka/documents/{$this->youthAttachment->id}");

        $response->assertForbidden();
        $this->assertStringContainsString('youth applicant documents', $response->getContent() ?: '');
    }

    public function test_staff_with_leads_view_youth_records_can_access_youth_document(): void
    {
        $this->staffUser->testPermissions = ['leads.view_documents', 'leads.view_youth_records'];

        $response = $this->actingAs($this->staffUser)
            ->get("/workspaces/ws-emk-dhaka/documents/{$this->youthAttachment->id}");

        $response->assertOk();
        $this->assertSame('Parental Consent Form for Minor Applicant', $response->streamedContent());
    }

    public function test_workspace_manager_role_can_access_youth_documents(): void
    {
        $this->staffUser->testPermissions = [];
        $this->staffUser->assignRole('workspace_manager');

        $response = $this->actingAs($this->staffUser)
            ->get("/workspaces/ws-emk-dhaka/documents/{$this->youthAttachment->id}");

        $response->assertOk();
        $this->assertSame('Parental Consent Form for Minor Applicant', $response->streamedContent());
    }

    public function test_successful_download_records_document_accessed_event_in_audit_logs(): void
    {
        $response = $this->actingAs($this->staffUser)
            ->get("/workspaces/ws-emk-dhaka/documents/{$this->standardAttachment->id}");

        $response->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->staffUser->id,
            'actor_guard' => 'web',
            'event_type' => 'document_accessed',
            'auditable_type' => LeadAttachment::class,
            'auditable_id' => $this->standardAttachment->id,
            'reason' => 'Staff document verification review.',
        ]);
    }

    public function test_unauthenticated_request_is_aborted_with_401(): void
    {
        $response = $this->get("/workspaces/ws-emk-dhaka/documents/{$this->standardAttachment->id}");

        $response->assertStatus(401);
    }

    public function test_missing_file_on_private_disk_aborts_with_404(): void
    {
        // Delete the physical file from disk
        Storage::disk('private')->delete($this->standardAttachment->storage_path);

        $response = $this->actingAs($this->staffUser)
            ->get("/workspaces/ws-emk-dhaka/documents/{$this->standardAttachment->id}");

        $response->assertNotFound();
    }

    public function test_superadmin_can_access_documents_across_workspaces(): void
    {
        $response = $this->actingAs($this->superAdminUser)
            ->get("/workspaces/ws-emk-dhaka/documents/{$this->standardAttachment->id}");

        $response->assertOk();
        $this->assertSame($this->fileContent, $response->streamedContent());
    }
}
