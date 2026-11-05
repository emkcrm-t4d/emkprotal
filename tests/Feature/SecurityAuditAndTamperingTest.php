<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Leads\Services\DocumentStorageService;
use App\Domain\Leads\Services\StoragePathResolver;
use App\Domain\Members\Models\Member;
use App\Domain\Tenancy\Traits\ValidatesWorkspaceOnHydration;
use App\Domain\Workspaces\Models\Workspace;
use App\Livewire\Leads\LeadProfile;
use App\Livewire\Members\MemberProfile;
use App\Models\MemberUser;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Domain\Reports\Services\CsvExportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

class SecurityHydrationProbeComponent extends Component
{
    use ValidatesWorkspaceOnHydration;

    public ?Lead $lead = null;
    public ?Member $member = null;

    public function mount(?Lead $lead = null, ?Member $member = null): void
    {
        $this->lead = $lead;
        $this->member = $member;
    }

    public function triggerProbeAction(): string
    {
        return 'executed';
    }

    public function render()
    {
        return '<div>Security Probe Component</div>';
    }
}

class SecurityAuditAndTamperingTest extends TestCase
{
    use RefreshDatabase;

    protected User $dhakaManager;
    protected User $sylhetManager;
    protected User $superAdmin;
    protected MemberUser $memberUserA;
    protected MemberUser $memberUserB;

    protected MembershipTerm $dhakaTerm;
    protected MembershipTerm $sylhetTerm;

    protected Lead $dhakaLead;
    protected Lead $dhakaLeadB;
    protected Lead $sylhetLead;

    protected Member $dhakaMemberA;
    protected Member $dhakaMemberB;
    protected Member $sylhetMember;

    protected Invoice $dhakaInvoice;
    protected Invoice $sylhetInvoice;

    protected LeadAttachment $memberAAttachment;
    protected LeadAttachment $memberBAttachment;
    protected LeadAttachment $sylhetAttachment;

    protected DocumentStorageService $storageService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        Storage::fake('private');
        $this->storageService = new DocumentStorageService(new StoragePathResolver());

        // 1. Ensure Sylhet workspace exists
        $sylSpace = DB::table('spaces')->where('code', 'CR-SYL')->first();
        Workspace::firstOrCreate(
            ['id' => 'ws-corner-sylhet'],
            [
                'space_id' => $sylSpace ? $sylSpace->id : 1,
                'name' => 'American Corner Sylhet',
                'is_active' => true,
            ]
        );

        // 2. Staff and Admins
        $this->dhakaManager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Manager',
            'email' => 'sec.manager.dhaka@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->dhakaManager->assignRole('workspace_manager');

        $this->sylhetManager = User::create([
            'workspace_id' => 'ws-corner-sylhet',
            'name' => 'Sylhet Manager',
            'email' => 'sec.manager.sylhet@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->sylhetManager->assignRole('workspace_manager');

        $this->superAdmin = User::create([
            'workspace_id' => null,
            'name' => 'Super Administrator',
            'email' => 'sec.superadmin@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('superadmin');

        // 3. Terms
        $this->dhakaTerm = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-emk-dhaka', 'code' => 'term_dhaka_1yr'],
            [
                'name' => 'Dhaka 1-Year Regular',
                'duration_months' => 12,
                'fee_bdt' => 500.00,
                'is_active' => true,
            ]
        );

        $this->sylhetTerm = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-corner-sylhet', 'code' => 'term_sylhet_1yr'],
            [
                'name' => 'Sylhet 1-Year Regular',
                'duration_months' => 12,
                'fee_bdt' => 400.00,
                'is_active' => true,
            ]
        );

        // 4. Dhaka Lead & Sylhet Lead
        $this->dhakaLead = Lead::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'approved',
            'bkash_txid' => 'TXIDDHK001',
            'first_name' => 'Rahim',
            'last_name' => 'Ahmed',
            'email' => 'rahim.dhaka@example.com',
            'contact_number' => '+8801700000001',
            'date_of_birth' => '1998-05-10',
            'age_at_submission' => 28,
            'sex' => 'male',
            'address_house_no' => '12',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'Bachelor',
            'area_of_subject' => 'Physics',
            'submitted_at' => now(),
        ]);

        $this->sylhetLead = Lead::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-corner-sylhet',
            'target_space_code' => 'CR-SYL',
            'status' => 'approved',
            'bkash_txid' => 'TXIDSYL001',
            'first_name' => 'Tamim',
            'last_name' => 'Iqbal',
            'email' => 'tamim.sylhet@example.com',
            'contact_number' => '+8801700000002',
            'date_of_birth' => '1997-04-12',
            'age_at_submission' => 29,
            'sex' => 'male',
            'address_house_no' => '45',
            'address_area' => 'Zindabazar',
            'address_zip_code' => '3100',
            'address_division' => 'Sylhet',
            'address_district' => 'Sylhet',
            'occupation' => 'Engineer',
            'institution_study' => 'SUST',
            'highest_certification' => 'Bachelor',
            'area_of_subject' => 'CSE',
            'submitted_at' => now(),
        ]);

        $this->dhakaLeadB = Lead::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'approved',
            'bkash_txid' => 'TXIDDHK002',
            'first_name' => 'Karim',
            'last_name' => 'Uddin',
            'email' => 'karim.dhaka@example.com',
            'contact_number' => '+8801700000003',
            'date_of_birth' => '1999-01-15',
            'age_at_submission' => 27,
            'sex' => 'male',
            'address_house_no' => '24',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Designer',
            'institution_study' => 'BUET',
            'highest_certification' => 'Bachelor',
            'area_of_subject' => 'Architecture',
            'submitted_at' => now(),
        ]);

        // 5. Member Users
        $this->memberUserA = MemberUser::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Member User A',
            'email' => 'member.a@emk.test',
            'password' => Hash::make('Secret123!'),
            'guard' => 'member',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $this->memberUserA->assignRole('member');

        $this->memberUserB = MemberUser::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Member User B',
            'email' => 'member.b@emk.test',
            'password' => Hash::make('Secret123!'),
            'guard' => 'member',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $this->memberUserB->assignRole('member');

        // 6. Members
        $this->dhakaMemberA = Member::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'member_id' => 'EMK-2026-00010',
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $this->dhakaLead->id,
            'user_id' => $this->memberUserA->id,
            'membership_term_id' => $this->dhakaTerm->id,
            'status' => 'active',
            'first_name' => 'Rahim',
            'last_name' => 'Ahmed',
            'email' => 'member.a@emk.test',
            'contact_number' => '+8801700000001',
            'date_of_birth' => '1998-05-10',
            'sex' => 'male',
            'address_house_no' => '12',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'Bachelor',
            'area_of_subject' => 'Physics',
            'start_date' => Carbon::now()->subMonths(1)->toDateString(),
            'expiry_date' => Carbon::now()->addMonths(11)->toDateString(),
        ]);

        $this->dhakaMemberB = Member::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'member_id' => 'EMK-2026-00011',
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $this->dhakaLeadB->id,
            'user_id' => $this->memberUserB->id,
            'membership_term_id' => $this->dhakaTerm->id,
            'status' => 'active',
            'first_name' => 'Karim',
            'last_name' => 'Uddin',
            'email' => 'member.b@emk.test',
            'contact_number' => '+8801700000003',
            'date_of_birth' => '1999-01-15',
            'sex' => 'male',
            'address_house_no' => '24',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Designer',
            'institution_study' => 'BUET',
            'highest_certification' => 'Bachelor',
            'area_of_subject' => 'Architecture',
            'start_date' => Carbon::now()->subMonths(1)->toDateString(),
            'expiry_date' => Carbon::now()->addMonths(11)->toDateString(),
        ]);

        $this->sylhetMember = Member::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'member_id' => 'SYL-2026-00020',
            'workspace_id' => 'ws-corner-sylhet',
            'lead_id' => $this->sylhetLead->id,
            'user_id' => null,
            'membership_term_id' => $this->sylhetTerm->id,
            'status' => 'active',
            'first_name' => 'Tamim',
            'last_name' => 'Iqbal',
            'email' => 'tamim.sylhet@example.com',
            'contact_number' => '+8801700000002',
            'date_of_birth' => '1997-04-12',
            'sex' => 'male',
            'address_house_no' => '45',
            'address_area' => 'Zindabazar',
            'address_zip_code' => '3100',
            'address_division' => 'Sylhet',
            'address_district' => 'Sylhet',
            'occupation' => 'Engineer',
            'institution_study' => 'SUST',
            'highest_certification' => 'Bachelor',
            'area_of_subject' => 'CSE',
            'start_date' => Carbon::now()->subMonths(1)->toDateString(),
            'expiry_date' => Carbon::now()->addMonths(11)->toDateString(),
        ]);

        // 7. Invoices
        $this->dhakaInvoice = Invoice::withoutGlobalScopes()->create([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $this->dhakaMemberA->id,
            'lead_id' => $this->dhakaLead->id,
            'invoice_number' => 'INV-DHK-00010',
            'amount_bdt' => 500.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'TXIDDHK001',
            'status' => 'paid',
            'paid_at' => Carbon::now()->subDays(3),
        ]);

        $this->sylhetInvoice = Invoice::withoutGlobalScopes()->create([
            'workspace_id' => 'ws-corner-sylhet',
            'member_id' => $this->sylhetMember->id,
            'lead_id' => $this->sylhetLead->id,
            'invoice_number' => 'INV-SYL-00020',
            'amount_bdt' => 400.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'TXIDSYL001',
            'status' => 'paid',
            'paid_at' => Carbon::now()->subDays(3),
        ]);

        // 8. Documents
        $storedA = $this->storageService->store(
            file: 'Member A Confidential Passport Data',
            leadUuid: $this->dhakaLead->uuid,
            workspaceId: 'ws-emk-dhaka',
            documentType: 'identity_document',
            originalFilename: 'passport_a.pdf',
            mimeType: 'application/pdf',
            encryptAtRest: true
        );

        $this->memberAAttachment = LeadAttachment::withoutGlobalScopes()->create([
            'lead_id' => $this->dhakaLead->id,
            'document_type' => 'identity_document',
            'storage_path' => $storedA['storage_path'],
            'original_filename' => $storedA['original_filename'],
            'mime_type' => $storedA['mime_type'],
            'size_bytes' => $storedA['size_bytes'],
            'file_hash' => $storedA['file_hash'],
            'youth_protection' => false,
        ]);

        $storedB = $this->storageService->store(
            file: 'Member B Confidential Identity Record',
            leadUuid: (string) Str::uuid(),
            workspaceId: 'ws-emk-dhaka',
            documentType: 'identity_document',
            originalFilename: 'national_id_b.pdf',
            mimeType: 'application/pdf',
            encryptAtRest: true
        );

        $this->memberBAttachment = LeadAttachment::withoutGlobalScopes()->create([
            'lead_id' => $this->dhakaLeadB->id,
            'document_type' => 'identity_document',
            'storage_path' => $storedB['storage_path'],
            'original_filename' => $storedB['original_filename'],
            'mime_type' => $storedB['mime_type'],
            'size_bytes' => $storedB['size_bytes'],
            'file_hash' => $storedB['file_hash'],
            'youth_protection' => false,
        ]);

        $storedSylhet = $this->storageService->store(
            file: 'Sylhet Applicant Private Record',
            leadUuid: $this->sylhetLead->uuid,
            workspaceId: 'ws-corner-sylhet',
            documentType: 'identity_document',
            originalFilename: 'nid_sylhet.pdf',
            mimeType: 'application/pdf',
            encryptAtRest: true
        );

        $this->sylhetAttachment = LeadAttachment::withoutGlobalScopes()->create([
            'lead_id' => $this->sylhetLead->id,
            'document_type' => 'identity_document',
            'storage_path' => $storedSylhet['storage_path'],
            'original_filename' => $storedSylhet['original_filename'],
            'mime_type' => $storedSylhet['mime_type'],
            'size_bytes' => $storedSylhet['size_bytes'],
            'file_hash' => $storedSylhet['file_hash'],
            'youth_protection' => false,
        ]);
    }

    /**
     * Test Livewire hydration tampering is strictly rejected with 403 Forbidden per DEC-01.
     */
    public function test_livewire_hydration_tampering_is_rejected_with_forbidden(): void
    {
        $this->actingAs($this->dhakaManager, 'web');

        // 1. Owned model hydrates successfully without exception
        Livewire::test(SecurityHydrationProbeComponent::class, ['lead' => $this->dhakaLead])
            ->call('triggerProbeAction')
            ->assertStatus(200);

        // 2. Foreign workspace lead hydration tampering is blocked with 403
        Livewire::test(SecurityHydrationProbeComponent::class, ['lead' => $this->sylhetLead])
            ->call('triggerProbeAction')
            ->assertStatus(403);

        // 3. Foreign workspace member hydration tampering is blocked with 403
        Livewire::test(SecurityHydrationProbeComponent::class, ['member' => $this->sylhetMember])
            ->call('triggerProbeAction')
            ->assertStatus(403);

        // 4. Superadmin bypasses hydration assertion per DEC-01 architecture rules
        $this->actingAs($this->superAdmin, 'web');
        Livewire::test(SecurityHydrationProbeComponent::class, ['lead' => $this->sylhetLead])
            ->call('triggerProbeAction')
            ->assertStatus(200);
    }

    /**
     * Test Livewire LeadProfile and MemberProfile reject cross-workspace entity parameter tampering.
     */
    public function test_livewire_lead_and_member_profile_components_reject_cross_workspace_parameters(): void
    {
        $this->actingAs($this->dhakaManager, 'web');

        // LeadProfile: owned lead in owned workspace succeeds
        Livewire::test(LeadProfile::class, [
            'workspace' => 'ws-emk-dhaka',
            'lead' => $this->dhakaLead,
        ])->assertOk();

        // LeadProfile: cross-workspace lead in owned workspace route is rejected with 403
        Livewire::test(LeadProfile::class, [
            'workspace' => 'ws-emk-dhaka',
            'lead' => $this->sylhetLead,
        ])->assertForbidden();

        // LeadProfile: tampered workspace route parameter is rejected with 403
        Livewire::test(LeadProfile::class, [
            'workspace' => 'ws-corner-sylhet',
            'lead' => $this->dhakaLead,
        ])->assertForbidden();

        // MemberProfile: owned member in owned workspace succeeds
        Livewire::test(MemberProfile::class, [
            'workspace' => 'ws-emk-dhaka',
            'member' => $this->dhakaMemberA,
        ])->assertOk();

        // MemberProfile: cross-workspace member is rejected with 403
        Livewire::test(MemberProfile::class, [
            'workspace' => 'ws-emk-dhaka',
            'member' => $this->sylhetMember,
        ])->assertForbidden();

        // MemberProfile: tampered workspace parameter is rejected with 403
        Livewire::test(MemberProfile::class, [
            'workspace' => 'ws-corner-sylhet',
            'member' => $this->dhakaMemberA,
        ])->assertForbidden();
    }

    /**
     * Test IDOR cross-workspace HTTP access to leads, members, invoices, and documents is strictly denied.
     */
    public function test_idor_cross_workspace_lead_member_and_invoice_http_access_is_strictly_denied(): void
    {
        $this->actingAs($this->dhakaManager, 'web');

        // 1. Direct workspace route barrier: Dhaka manager requesting Sylhet CRM routes receives 403
        $this->get('/crm/ws-corner-sylhet/leads')->assertStatus(403);
        $this->get("/crm/ws-corner-sylhet/leads/{$this->sylhetLead->id}")->assertStatus(403);
        $this->get('/crm/ws-corner-sylhet/members')->assertStatus(403);
        $this->get("/crm/ws-corner-sylhet/members/{$this->sylhetMember->uuid}")->assertStatus(403);
        $this->get('/crm/ws-corner-sylhet/invoices')->assertStatus(403);
        $this->get("/crm/ws-corner-sylhet/invoices/{$this->sylhetInvoice->id}/receipt")->assertStatus(403);

        // 2. Direct Object Reference Tampering: Dhaka manager attempting to query Sylhet entity via Dhaka route
        // Cross-workspace invoice receipt access is blocked with 403
        $this->get("/crm/ws-emk-dhaka/invoices/{$this->sylhetInvoice->id}/receipt")->assertStatus(403);

        // Cross-workspace lead access is blocked (403 or 404)
        $leadResp = $this->get("/crm/ws-emk-dhaka/leads/{$this->sylhetLead->id}");
        $this->assertTrue(in_array($leadResp->status(), [403, 404], true));

        // Cross-workspace member access is blocked (403 or 404)
        $memberResp = $this->get("/crm/ws-emk-dhaka/members/{$this->sylhetMember->uuid}");
        $this->assertTrue(in_array($memberResp->status(), [403, 404], true));

        // 3. Document Download IDOR Defense
        // Accessing Sylhet workspace document route is blocked with 403
        $this->get("/workspaces/ws-corner-sylhet/documents/{$this->sylhetAttachment->id}")->assertStatus(403);

        // Accessing Sylhet document via Dhaka workspace route is rejected (403 or 404)
        $docResp = $this->get("/workspaces/ws-emk-dhaka/documents/{$this->sylhetAttachment->id}");
        $this->assertTrue(in_array($docResp->status(), [403, 404], true));
    }

    /**
     * Test Member Portal Isolation: Member A cannot access or download Member B's documents per DEC-06.
     */
    public function test_member_portal_isolation_prevents_unauthorized_cross_member_document_download(): void
    {
        $this->actingAs($this->memberUserA, 'member');

        // Member A downloading their own document succeeds with 200 stream
        $ownDocResponse = $this->get("/portal/documents/{$this->memberAAttachment->id}");
        $ownDocResponse->assertStatus(200);

        // Member A attempting to download Member B's document is blocked with 403 Forbidden
        $tamperedDocResponse = $this->get("/portal/documents/{$this->memberBAttachment->id}");
        $tamperedDocResponse->assertStatus(403);

        // Attempting to download cross-workspace Sylhet document is also strictly blocked with 403
        $sylhetDocResponse = $this->get("/portal/documents/{$this->sylhetAttachment->id}");
        $sylhetDocResponse->assertStatus(403);
    }

    /**
     * Test Audit Log Immutability: update and delete operations are rejected per DEC-11.
     */
    public function test_audit_logs_are_strictly_immutable_and_append_only_per_dec_11(): void
    {
        // 1. Insert audit log record
        $logId = DB::table('audit_logs')->insertGetId([
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->dhakaManager->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_LOGIN_SUCCESS,
            'auditable_type' => User::class,
            'auditable_id' => $this->dhakaManager->id,
            'old_values' => null,
            'new_values' => json_encode(['ip' => '127.0.0.1']),
            'reason' => 'Immutable audit benchmark log',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'SecurityTest/1.0',
            'created_at' => now(),
        ]);

        $this->assertDatabaseHas('audit_logs', ['id' => $logId, 'reason' => 'Immutable audit benchmark log']);

        // 2. Direct DB Query Builder update fails with trigger exception
        $updateBlocked = false;
        try {
            DB::table('audit_logs')->where('id', $logId)->update(['reason' => 'Tampered By Attacker']);
        } catch (QueryException $e) {
            $updateBlocked = true;
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
        $this->assertTrue($updateBlocked, 'Direct SQL update on audit_logs must throw append-only trigger exception.');

        // 3. Direct DB Query Builder delete fails with trigger exception
        $deleteBlocked = false;
        try {
            DB::table('audit_logs')->where('id', $logId)->delete();
        } catch (QueryException $e) {
            $deleteBlocked = true;
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
        $this->assertTrue($deleteBlocked, 'Direct SQL delete on audit_logs must throw append-only trigger exception.');

        // 4. Eloquent model update fails
        $eloquentLog = AuditLog::withoutGlobalScopes()->find($logId);
        $this->assertNotNull($eloquentLog);

        $eloquentUpdateBlocked = false;
        try {
            $eloquentLog->update(['reason' => 'Eloquent Tampered']);
        } catch (QueryException $e) {
            $eloquentUpdateBlocked = true;
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
        $this->assertTrue($eloquentUpdateBlocked, 'Eloquent update on AuditLog must throw append-only trigger exception.');

        // 5. Eloquent model delete fails
        $eloquentDeleteBlocked = false;
        try {
            $eloquentLog->delete();
        } catch (QueryException $e) {
            $eloquentDeleteBlocked = true;
            $this->assertStringContainsString('append-only', $e->getMessage());
        }
        $this->assertTrue($eloquentDeleteBlocked, 'Eloquent delete on AuditLog must throw append-only trigger exception.');

        // 6. Assert records remain unchanged in database
        $this->assertDatabaseHas('audit_logs', [
            'id' => $logId,
            'reason' => 'Immutable audit benchmark log',
        ]);
    }

    /**
     * Test File Upload Security: magic bytes validation rejects disguised executable scripts.
     */
    public function test_file_upload_rejects_disguised_executable_scripts_and_spoofed_mime_types(): void
    {
        // 1. PHP script disguised with a .jpg extension (detected MIME: text/x-php)
        $fakeJpg = UploadedFile::fake()->create('shell.jpg', 10)->mimeType('text/x-php');

        $rules = [
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:4096'],
        ];

        $validator = Validator::make(['photo' => $fakeJpg], $rules);
        $this->assertTrue($validator->fails(), 'File disguised with .jpg extension containing PHP script must fail MIME validation.');
        $this->assertArrayHasKey('photo', $validator->errors()->toArray());

        // 2. Shellcode / bash script disguised with a .png extension (detected MIME: application/x-sh)
        $fakePng = UploadedFile::fake()->create('exploit.php.png', 10)->mimeType('application/x-sh');

        $validatorPng = Validator::make(['photo' => $fakePng], $rules);
        $this->assertTrue($validatorPng->fails(), 'Double-extension executable disguised as png must fail MIME validation.');
        $this->assertArrayHasKey('photo', $validatorPng->errors()->toArray());
    }

    /**
     * Test File Upload Security: SVG executable script uploads are strictly blocked per OWASP ASVS Level 1.
     */
    public function test_file_upload_blocks_svg_executable_script_uploads(): void
    {
        // SVG with embedded XSS script payload
        $maliciousSvg = UploadedFile::fake()->createWithContent(
            'payload.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert("XSS")</script></svg>'
        );

        // Application photo rule: strictly only raster images (jpg, jpeg, png)
        $photoRules = ['photo' => ['required', 'file', 'mimes:jpg,jpeg,png']];
        $validatorPhoto = Validator::make(['photo' => $maliciousSvg], $photoRules);
        $this->assertTrue($validatorPhoto->fails(), 'SVG files must be blocked on photo upload fields.');

        // Application identity document rule: mimes:pdf,jpg,jpeg,doc,docx,png (SVG disbarred)
        $docRules = ['doc' => ['required', 'file', 'mimes:pdf,jpg,jpeg,doc,docx,png']];
        $validatorDoc = Validator::make(['doc' => $maliciousSvg], $docRules);
        $this->assertTrue($validatorDoc->fails(), 'SVG files must be blocked on identity document fields.');
    }

    /**
     * Test Document Download Controller: path traversal sequences are rejected and confined to storage root.
     */
    public function test_document_download_path_traversal_is_blocked_and_confined_to_storage_root(): void
    {
        $this->actingAs($this->dhakaManager, 'web');

        // 1. Direct path traversal via HTTP route parameters
        $traversalAttempts = [
            '../../etc/passwd',
            '..%2F..%2Fetc%2Fpasswd',
            '..\\..\\Windows\\win.ini',
            '....//....//etc/passwd',
        ];

        foreach ($traversalAttempts as $attempt) {
            $response = $this->get('/workspaces/ws-emk-dhaka/documents/' . urlencode($attempt));
            $this->assertTrue(
                in_array($response->status(), [400, 403, 404], true),
                "Path traversal attempt [{$attempt}] must be rejected with 400, 403, or 404."
            );
        }

        // 2. Assert DocumentStorageService / Flysystem blocks retrieval outside storage root
        $caught = false;
        try {
            $this->storageService->get('../../etc/passwd');
        } catch (\Throwable $e) {
            $caught = true;
            $this->assertTrue(
                str_contains($e->getMessage(), 'Path traversal') ||
                str_contains($e->getMessage(), 'not found')
            );
        }
        $this->assertTrue($caught, 'Path traversal attempt must be caught and blocked.');
    }

    /**
     * Test CSV Formula Injection Defense (CWE-1236): formula triggers are escaped with single apostrophe.
     */
    public function test_csv_export_neutralizes_formula_injection_triggers_cwe_1236(): void
    {
        /** @var CsvExportService $csvService */
        $csvService = app(CsvExportService::class);

        // 1. Unit test formula injection neutralization across all OWASP formula triggers
        $dangerousTriggers = [
            '=1+1' => "'=1+1",
            '@SUM(A1:A10)' => "'@SUM(A1:A10)",
            "-cmd|' /C calc'!A0" => "'-cmd|' /C calc'!A0",
            '+test' => "'+test",
            "\t@calc" => "'@calc",
            "\r=cmd" => "'=cmd",
            "   =HYPERLINK(\"http://evil.com\")" => "'=HYPERLINK(\"http://evil.com\")",
        ];

        foreach ($dangerousTriggers as $input => $expected) {
            $sanitized = $csvService->sanitizeCsvCell($input);
            $this->assertSame(
                $expected,
                $sanitized,
                "Formula trigger [{$input}] was not properly sanitized. Got [{$sanitized}]."
            );
        }

        // Safe benign text values remain unmodified
        $this->assertSame('Rahim Ahmed', $csvService->sanitizeCsvCell('Rahim Ahmed'));
        $this->assertSame('Dhaka 1213', $csvService->sanitizeCsvCell('Dhaka 1213'));
        $this->assertSame('', $csvService->sanitizeCsvCell(null));

        // 2. Integration test: Lead export with formula injection fields
        Lead::withoutGlobalScopes()->create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'approved',
            'bkash_txid' => 'TXIDFORM01',
            'first_name' => '=cmd|calc!A0',
            'last_name' => '@SUM(A1:B10)',
            'email' => 'malicious.csv@example.com',
            'contact_number' => '+8801700000099',
            'date_of_birth' => '1995-01-01',
            'age_at_submission' => 31,
            'sex' => 'male',
            'address_house_no' => '-cmd',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => '+admin',
            'institution_study' => 'BUET',
            'highest_certification' => 'Bachelor',
            'area_of_subject' => 'CS',
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->dhakaManager, 'web');
        $response = $csvService->exportLeads('ws-emk-dhaka');
        $this->assertSame(200, $response->getStatusCode());

        ob_start();
        $response->sendContent();
        $streamedCsv = ob_get_clean();

        $this->assertNotEmpty($streamedCsv);
        // Assert formula characters in data rows are escaped with leading single apostrophe
        $this->assertStringContainsString("'=cmd|calc!A0", $streamedCsv);
        $this->assertStringContainsString("'@SUM(A1:B10)", $streamedCsv);
    }

    /**
     * Test HTTP Security Headers and Session Cookie Security configuration per OWASP ASVS.
     */
    public function test_http_security_headers_and_session_cookie_flags_enforced(): void
    {
        // 1. Assert HTTP Security Headers on web response
        $response = $this->get('/login');
        $response->assertStatus(200);

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // 2. Assert Session Cookie configuration per OWASP ASVS V3
        $this->assertTrue(
            config('session.http_only'),
            'Session cookies must have HttpOnly enabled to prevent XSS credential theft.'
        );

        $this->assertContains(
            config('session.same_site'),
            ['lax', 'strict'],
            'Session cookies must enforce SameSite=lax or SameSite=strict per ASVS V3.'
        );
    }

    /**
     * Test workspace data export gracefully handles malformed date filter inputs without unhandled exceptions.
     */
    public function test_workspace_data_export_gracefully_handles_malformed_date_inputs(): void
    {
        Auth::login($this->dhakaManager);

        /** @var \App\Domain\Reports\Services\WorkspaceDataExportService $exportService */
        $exportService = app(\App\Domain\Reports\Services\WorkspaceDataExportService::class);

        // Pass invalid dates
        $response = $exportService->exportMembers('ws-emk-dhaka', [
            'date_from' => 'not-a-real-date',
            'date_to' => '99999-invalid',
        ]);

        $this->assertSame(200, $response->getStatusCode());
    }
}
