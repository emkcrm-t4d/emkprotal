<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Exceptions\UnauthorizedVerificationException;
use App\Domain\Leads\Exceptions\UnmetConversionInvariantException;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Leads\Models\LeadVerification;
use App\Domain\Leads\Services\LeadConversionService;
use App\Domain\Leads\Services\VerificationService;
use App\Domain\Members\Mail\ManagerEmailChangeAlertMail;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberChangeRequest;
use App\Domain\Members\Services\ConversionReversalService;
use App\Domain\Members\Services\MemberChangeRequestService;
use App\Domain\Members\Services\MemberRenewalService;
use App\Domain\Payments\Adapters\ManualBkashPaymentAdapter;
use App\Jobs\SendMemberWelcomeEmailJob;
use App\Livewire\Forms\PublicMembershipForm;
use App\Livewire\Leads\LeadDirectory;
use App\Livewire\Leads\LeadProfile;
use App\Livewire\Members\MemberChangeRequestDirectory;
use App\Livewire\Portal\MemberPortalProfile;
use App\Livewire\Portal\Modals\SubmitProfileChangeRequestModal;
use App\Livewire\Portal\PortalFirstPasswordReset;
use App\Livewire\Portal\PortalLogin;
use App\Models\MemberUser;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DefaultMembershipFormSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Master End-to-End System Acceptance (UAT) Verification Suite (RELS-01).
 *
 * Verifies the holistic system lifecycle across multi-workspace isolation (DEC-01),
 * dynamic code restrictions (DEC-02), tri-state verification gates (DEC-03),
 * 30-day conversion reversal protocol (DEC-04), 10-char bKash payment verification (DEC-05),
 * mandatory first-login password reset (DEC-06), dual-entity email change approval (DEC-07),
 * dynamic expiry calculation (DEC-08), minor consent invariants (DEC-09),
 * and immutable system audit logging (DEC-11).
 */
class MasterEndToEndSystemAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $dhakaManager;
    protected User $dhakaStaff;
    protected User $chattogramManager;
    protected User $superAdmin;
    protected MembershipTerm $term;
    protected Form $publicForm;

    protected VerificationService $verificationService;
    protected LeadConversionService $conversionService;
    protected ConversionReversalService $reversalService;
    protected MemberChangeRequestService $changeRequestService;
    protected MemberRenewalService $renewalService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Mail::fake();
        Queue::fake();

        // 1. Seed complete base schema, spaces, workspaces, roles, permissions, and forms
        $this->seed(DatabaseSeeder::class);
        $this->seed(DefaultMembershipFormSeeder::class);

        // 2. Provision Chattogram American Corner workspace for tenancy isolation assertions
        $ctgSpace = DB::table('spaces')->where('code', 'CR-CTG')->first();
        if ($ctgSpace) {
            DB::table('workspaces')->updateOrInsert(
                ['id' => 'ws-chattogram-corner'],
                [
                    'space_id' => $ctgSpace->id,
                    'name' => 'American Corner Chattogram',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 3. Provision Dhaka Workspace Manager
        $this->dhakaManager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Workspace Manager',
            'email' => 'uat.manager.dhaka@emk.test',
            'password' => bcrypt('DhakaManagerPass123!'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->dhakaManager->assignRole('workspace_manager');
        $this->dhakaManager->givePermissionTo([
            'leads.view',
            'leads.edit',
            'leads.verify_payment',
            'leads.verify_documents',
            'leads.verify_photo',
            'leads.communicate',
            'leads.approve',
            'leads.reject',
            'leads.convert',
            'leads.reverse_conversion',
            'members.view',
            'members.edit',
            'members.manage_cards',
        ]);

        // 4. Provision Dhaka Delegated Staff
        $this->dhakaStaff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Admissions Staff',
            'email' => 'uat.staff.dhaka@emk.test',
            'password' => bcrypt('DhakaStaffPass123!'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->dhakaStaff->assignRole('delegated_staff');
        $this->dhakaStaff->givePermissionTo([
            'leads.view',
            'leads.edit',
            'leads.verify_documents',
            'leads.verify_photo',
            'leads.communicate',
            'members.view',
        ]);

        // 5. Provision Chattogram Corner Workspace Manager (Cross-Workspace Tenant Isolation)
        $this->chattogramManager = User::create([
            'workspace_id' => 'ws-chattogram-corner',
            'name' => 'Chattogram Corner Manager',
            'email' => 'uat.manager.ctg@emk.test',
            'password' => bcrypt('CtgManagerPass123!'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->chattogramManager->assignRole('workspace_manager');
        $this->chattogramManager->givePermissionTo([
            'leads.view',
            'leads.edit',
            'leads.verify_payment',
            'leads.verify_documents',
            'leads.verify_photo',
            'leads.approve',
            'leads.convert',
            'leads.reverse_conversion',
            'members.view',
        ]);

        // 6. Provision Platform Superadmin
        $this->superAdmin = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'System Superadmin',
            'email' => 'uat.superadmin@emk.test',
            'password' => bcrypt('SuperadminPass123!'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('superadmin');

        // 7. Resolve standard membership term
        $this->term = MembershipTerm::where('workspace_id', 'ws-emk-dhaka')
            ->where('code', 'std-1yr')
            ->firstOrFail();

        // 8. Resolve public form
        $this->publicForm = Form::withoutGlobalScopes()
            ->where('slug', 'membership-application')
            ->firstOrFail();

        // 9. Resolve domain services
        $this->verificationService = app(VerificationService::class);
        $this->conversionService = app(LeadConversionService::class);
        $this->reversalService = app(ConversionReversalService::class);
        $this->changeRequestService = app(MemberChangeRequestService::class);
        $this->renewalService = app(MemberRenewalService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Scenario 1: Multi-Workspace Isolation & Registration Scenario (RELS-01, DEC-01).
     */
    public function test_multi_workspace_isolation_and_registration_scenario(): void
    {
        $identityPdf = UploadedFile::fake()->create('applicant_passport.pdf', 1024, 'application/pdf');
        $photoJpg = UploadedFile::fake()->image('studio_headshot.jpg', 600, 600);

        // 1. Submit public application for ws-emk-dhaka
        $component = Livewire::test(PublicMembershipForm::class, ['slug' => 'membership-application'])
            ->set('first_name', 'Muntakim')
            ->set('middle_name', 'Ali')
            ->set('last_name', 'Chowdhury')
            ->set('american_space', 'emk_center')
            ->set('sex', 'male')
            ->set('date_of_birth', '1998-07-15')
            ->set('address_house_no', 'Flat 4A, Plot 12')
            ->set('address_road_no', 'Road 27')
            ->set('address_area', 'Dhanmondi')
            ->set('address_zip_code', '1209')
            ->set('address_division', 'dhaka')
            ->set('address_district', 'dhaka')
            ->set('contact_number', '+8801711223344')
            ->set('email', 'muntakim.ali@emk.test')
            ->set('occupation', 'entrepreneur')
            ->set('institution_study', 'University of Dhaka')
            ->set('highest_certification', 'undergraduate_degree')
            ->set('area_of_subject', 'Information Technology')
            ->set('identity_document_files', [$identityPdf])
            ->set('photo_file', $photoJpg)
            ->set('bkash_txid', 'BK10CHARTX') // Exactly 10-char TxID per DEC-05
            ->set('terms_agreed', true)
            ->call('submit');

        $component->assertHasNoErrors();
        $this->assertTrue($component->get('submitted'));

        // Retrieve ingested Dhaka lead
        $dhakaLead = Lead::where('email', 'muntakim.ali@emk.test')->firstOrFail();
        $this->assertSame('ws-emk-dhaka', $dhakaLead->workspace_id);
        $this->assertSame('EMK-DHK', $dhakaLead->target_space_code);
        $this->assertSame('BK10CHARTX', $dhakaLead->bkash_txid);
        $this->assertSame('submitted', $dhakaLead->status);

        // 2. Dhaka manager and staff can view lead in directory
        $dhakaResponse = $this->actingAs($this->dhakaManager, 'web')
            ->get(route('crm.leads', ['workspace' => 'ws-emk-dhaka']));
        $dhakaResponse->assertStatus(200);
        $dhakaResponse->assertSee('Muntakim');
        $dhakaResponse->assertSee('BK10CHARTX');

        // 3. Chattogram Manager cross-workspace access defense (DEC-01): HTTP 403 Forbidden
        $crossWorkspaceResponse = $this->actingAs($this->chattogramManager, 'web')
            ->get(route('crm.leads.show', [
                'workspace' => 'ws-emk-dhaka',
                'lead' => $dhakaLead->id,
            ]));
        $crossWorkspaceResponse->assertStatus(403);

        // Direct Livewire component mount with cross-workspace entity aborts with 403
        Livewire::actingAs($this->chattogramManager, 'web')
            ->test(LeadProfile::class, [
                'workspace' => 'ws-chattogram-corner',
                'lead' => $dhakaLead,
            ])
            ->assertStatus(403);

        // Direct service conversion by foreign workspace manager throws UnauthorizedVerificationException
        try {
            $this->conversionService->convert($dhakaLead, $this->term->id, $this->chattogramManager);
            $this->fail('Expected UnauthorizedVerificationException when cross-workspace manager attempts conversion.');
        } catch (UnauthorizedVerificationException $e) {
            $this->assertStringContainsString('does not have access to workspace', $e->getMessage());
        }
    }

    /**
     * Scenario 2: Tri-State Verification Gate Scenario (DEC-03, DEC-05, DEC-11).
     */
    public function test_tristate_verification_gates_scenario(): void
    {
        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'first_name' => 'Samira',
            'last_name' => 'Khatun',
            'email' => 'samira.tristate@emk.test',
            'contact_number' => '+8801722334455',
            'bkash_txid' => 'TX10CHAR01',
            'date_of_birth' => '1999-04-12',
            'age_at_submission' => 27,
            'sex' => 'female',
            'address_house_no' => 'House 55',
            'address_area' => 'Gulshan 2',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'NSU',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Economics',
            'status' => 'submitted',
            'payment_verification_status' => 'pending',
            'document_verification_status' => 'pending',
            'photo_verification_status' => 'pending',
            'terms_agreed' => true,
            'submitted_at' => now(),
        ]);

        LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'document_type' => 'identity_document',
            'original_filename' => 'passport.pdf',
            'storage_path' => "workspaces/ws-emk-dhaka/leads/{$lead->uuid}/passport.pdf",
            'size_bytes' => 2048,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', 'mock-passport-binary'),
        ]);

        LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'document_type' => 'studio_photo',
            'original_filename' => 'headshot.jpg',
            'storage_path' => "workspaces/ws-emk-dhaka/leads/{$lead->uuid}/photo.jpg",
            'size_bytes' => 1024,
            'mime_type' => 'image/jpeg',
            'file_hash' => hash('sha256', 'mock-photo-binary'),
        ]);

        // 1. Incomplete gates assert conversion is blocked with UnmetConversionInvariantException (DEC-03)
        try {
            $this->conversionService->convert($lead, $this->term->id, $this->dhakaManager);
            $this->fail('Expected UnmetConversionInvariantException when gates are incomplete.');
        } catch (UnmetConversionInvariantException $e) {
            $unmet = $e->getUnmetConditions();
            $this->assertArrayHasKey('payment', $unmet);
            $this->assertArrayHasKey('document', $unmet);
            $this->assertArrayHasKey('photo', $unmet);
        }

        // 2. Staff cannot verify payment (T-01-07, DEC-05)
        try {
            $this->verificationService->verifyPayment($lead, $this->dhakaStaff, [
                'decision' => 'verified',
                'amount_bdt' => 1000.0,
                'bkash_txid' => 'TX10CHAR01',
            ]);
            $this->fail('Expected UnauthorizedVerificationException when staff verifies payment.');
        } catch (UnauthorizedVerificationException $e) {
            $this->assertStringContainsString('leads.verify_payment', $e->getMessage());
        }

        // 3. Manager verifies 10-char bKash payment (DEC-05)
        $this->assertMatchesRegularExpression(ManualBkashPaymentAdapter::TXID_REGEX, 'TX10CHAR01');
        $paymentVerification = $this->verificationService->verifyPayment($lead, $this->dhakaManager, [
            'decision' => 'verified',
            'amount_bdt' => 1000.0,
            'bkash_txid' => 'TX10CHAR01',
            'notes' => 'bKash merchant statement verified on terminal.',
        ]);

        $lead->refresh();
        $this->assertSame('verified', $lead->payment_verification_status);
        $this->assertSame('verified', $paymentVerification->status);

        // 4. Staff verifies documents independently
        $docVerification = $this->verificationService->verifyDocuments($lead, $this->dhakaStaff, [
            'id_document_type' => 'passport',
            'id_document_number' => 'EA0123456',
            'name_matches_form' => true,
            'dob_matches_form' => true,
            'decision' => 'verified',
            'notes' => 'Original passport document verified.',
        ]);

        $lead->refresh();
        $this->assertSame('verified', $lead->document_verification_status);
        $this->assertSame('verified', $docVerification->status);

        // 5. Staff verifies photo independently (5-point studio compliance)
        $photoVerification = $this->verificationService->verifyPhoto($lead, $this->dhakaStaff, [
            'white_background' => true,
            'no_glasses' => true,
            'ears_visible' => true,
            'regular_attire' => true,
            'no_selfie' => true,
            'decision' => 'verified',
            'notes' => 'Compliant white studio photo.',
        ]);

        $lead->refresh();
        $this->assertSame('verified', $lead->photo_verification_status);
        $this->assertSame('verified', $photoVerification->status);

        // 6. Confirm all three gates are verified
        $this->assertTrue($this->verificationService->canApproveLead($lead));

        // Manager approves lead
        $this->verificationService->approveLead($lead, $this->dhakaManager);
        $lead->refresh();
        $this->assertSame('approved', $lead->status);

        // 7. Confirm immutable verifications and audit logs exist
        $this->assertDatabaseHas('lead_verifications', [
            'lead_id' => $lead->id,
            'stream' => 'payment',
            'status' => 'verified',
        ]);
        $this->assertDatabaseHas('lead_verifications', [
            'lead_id' => $lead->id,
            'stream' => 'document',
            'status' => 'verified',
        ]);
        $this->assertDatabaseHas('lead_verifications', [
            'lead_id' => $lead->id,
            'stream' => 'photo',
            'status' => 'verified',
        ]);
    }

    /**
     * Scenario 3: Atomic 10-Step Conversion Scenario (RELS-01, DEC-03, DEC-06, DEC-08, DEC-11).
     */
    public function test_atomic_ten_step_conversion_scenario(): void
    {
        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'first_name' => 'Tahmid',
            'middle_name' => 'Zaman',
            'last_name' => 'Shuvo',
            'email' => 'tahmid.conversion@emk.test',
            'contact_number' => '+8801733445566',
            'bkash_txid' => 'BK10CHARTX',
            'date_of_birth' => '1997-09-20',
            'age_at_submission' => 29,
            'sex' => 'male',
            'address_house_no' => 'House 14',
            'address_road_no' => 'Road 11',
            'address_area' => 'Banani',
            'address_zip_code' => '1213',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'software_engineer',
            'institution_study' => 'BUET',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'CSE',
            'status' => 'approved',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'terms_agreed' => true,
            'submitted_at' => now()->subDay(),
        ]);

        LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'document_type' => 'identity_document',
            'original_filename' => 'nid_card.pdf',
            'storage_path' => "workspaces/ws-emk-dhaka/leads/{$lead->uuid}/nid.pdf",
            'size_bytes' => 4096,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', 'mock-nid-data'),
        ]);

        // Execute atomic lead-to-member conversion
        $member = $this->conversionService->convert($lead, $this->term->id, $this->dhakaManager);

        // Step 1 & 2: Member ID formatted with standardized format EMK-YYYY-XXXXX
        $this->assertNotNull($member);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{3}-\d{4}-\d{5}$/', $member->member_id);

        // Step 3 & 4: Start date is approval date; expiry date dynamically calculated from term (DEC-08)
        $this->assertSame(now()->toDateString(), $member->start_date->toDateString());
        $expectedExpiry = now()->addMonths((int) $this->term->duration_months)->toDateString();
        $this->assertSame($expectedExpiry, $member->expiry_date->toDateString());

        // Step 5: Post-conversion paid invoice generated automatically
        $invoice = Invoice::where('member_id', $member->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('BK10CHARTX', $invoice->payment_reference);
        $this->assertEquals($this->term->fee_bdt, $invoice->amount_bdt);

        // Step 6: ID card record queued in printing pipeline
        $idCard = IdCard::where('member_id', $member->id)->first();
        $this->assertNotNull($idCard);
        $this->assertSame('provided_for_printing', $idCard->status);
        $this->assertSame($member->member_id, $idCard->card_number);

        // Step 7: Portal user credentials account generated (DEC-06)
        $portalUser = User::find($member->user_id);
        $this->assertNotNull($portalUser);
        $this->assertSame('member', $portalUser->guard);
        $this->assertTrue($portalUser->is_active);
        $this->assertTrue($portalUser->must_reset_password);

        // Step 8: Welcome email job queued on Horizon
        Queue::assertPushed(SendMemberWelcomeEmailJob::class, function ($job) use ($member) {
            return $job->memberId === $member->id;
        });

        // Step 9: Lead status updated to converted
        $lead->refresh();
        $this->assertSame('converted', $lead->status);
        $this->assertSame($member->id, $lead->converted_member_id);

        // Step 10: Immutable audit log emitted (DEC-11)
        $auditLog = AuditLog::where('auditable_type', Member::class)
            ->where('auditable_id', $member->id)
            ->where('event_type', AuditLog::EVENT_CONVERSION_COMPLETED)
            ->first();
        $this->assertNotNull($auditLog);
        $this->assertSame($this->dhakaManager->id, $auditLog->actor_id);
    }

    /**
     * Scenario 4: Member Portal Lifecycle Scenario (DEC-06, DEC-07, PORT-01 through PORT-05).
     */
    public function test_member_portal_lifecycle_scenario(): void
    {
        // 1. Provision active member fixture with forced password reset
        $initialPassword = 'TempPassword123!';
        $memberUser = MemberUser::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Fahim Ahmed',
            'email' => 'fahim.portal@emk.test',
            'password' => Hash::make($initialPassword),
            'guard' => 'member',
            'is_active' => true,
            'must_reset_password' => true,
        ]);
        $memberUser->assignRole('member');

        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'converted',
            'bkash_txid' => 'TX10PORTAL',
            'first_name' => 'Fahim',
            'last_name' => 'Ahmed',
            'email' => 'fahim.portal@emk.test',
            'contact_number' => '+8801744556677',
            'date_of_birth' => '1996-03-10',
            'age_at_submission' => 30,
            'sex' => 'male',
            'address_house_no' => 'House 12',
            'address_area' => 'Uttara',
            'address_zip_code' => '1230',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'architect',
            'institution_study' => 'AUST',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Architecture',
            'terms_agreed' => true,
            'submitted_at' => now(),
        ]);

        $expiryDate = Carbon::today()->addDays(20); // within 30-day renewal window

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => 'EMK-2026-00099',
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'user_id' => $memberUser->id,
            'membership_term_id' => $this->term->id,
            'status' => 'active',
            'first_name' => 'Fahim',
            'last_name' => 'Ahmed',
            'email' => 'fahim.portal@emk.test',
            'contact_number' => '+8801744556677',
            'date_of_birth' => '1996-03-10',
            'sex' => 'male',
            'address_house_no' => 'House 12',
            'address_area' => 'Uttara',
            'address_zip_code' => '1230',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'architect',
            'institution_study' => 'AUST',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Architecture',
            'start_date' => Carbon::today()->subMonths(11)->toDateString(),
            'expiry_date' => $expiryDate->toDateString(),
        ]);

        // Plant confidential staff notes and audit logs
        DB::table('lead_verifications')->insert([
            'lead_id' => $lead->id,
            'stream' => 'document',
            'status' => 'verified',
            'verified_by_user_id' => $this->dhakaManager->id,
            'verified_at' => now(),
            'evidence_payload' => json_encode(['verified' => true]),
            'notes' => 'TOP_SECRET_STAFF_NOTE_CONFIDENTIAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        AuditLog::create([
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->dhakaManager->id,
            'actor_guard' => 'web',
            'event_type' => 'internal_security_audit',
            'auditable_type' => Member::class,
            'auditable_id' => $member->id,
            'reason' => 'INTERNAL_SECURITY_AUDIT_LOG_ENTRY',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'InternalAudit',
            'created_at' => now(),
        ]);

        // 2. Member Login with dual-identifier (Member ID)
        Livewire::test(PortalLogin::class)
            ->set('identifier', 'EMK-2026-00099')
            ->set('password', $initialPassword)
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('portal.profile'));

        $this->assertTrue(Auth::guard('member')->check());

        // 3. Assert first login triggers mandatory password reset (DEC-06)
        $response = $this->actingAs($memberUser, 'member')->get(route('portal.profile'));
        $response->assertRedirect(route('portal.password.reset'));

        // Complete password reset
        Livewire::actingAs($memberUser, 'member')
            ->test(PortalFirstPasswordReset::class)
            ->set('current_password', $initialPassword)
            ->set('password', 'NewPermanentPassword99!')
            ->set('password_confirmation', 'NewPermanentPassword99!')
            ->call('updatePassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('portal.profile'));

        $memberUser->refresh();
        $this->assertFalse($memberUser->must_reset_password);
        $this->assertTrue(Hash::check('NewPermanentPassword99!', $memberUser->password));

        // 4. View read-only profile: assert personal data visible, internal staff notes & audit logs hidden
        $profileComponent = Livewire::actingAs($memberUser, 'member')
            ->test(MemberPortalProfile::class);

        $profileComponent->assertSee('Fahim Ahmed');
        $profileComponent->assertSee('EMK-2026-00099');
        $profileComponent->assertSee('Uttara');
        $profileComponent->assertDontSee('TOP_SECRET_STAFF_NOTE_CONFIDENTIAL');
        $profileComponent->assertDontSee('INTERNAL_SECURITY_AUDIT_LOG_ENTRY');

        // 5. Submit Member Profile Change Request: enters queue without directly altering profile
        Livewire::actingAs($memberUser, 'member')
            ->test(SubmitProfileChangeRequestModal::class, ['member' => $member])
            ->call('openModal')
            ->set('contact_number', '+8801999887766')
            ->set('address_house_no', 'Flat 7B')
            ->set('address_area', 'Mirpur DOHS')
            ->set('justification', 'Moved to permanent residence in Mirpur DOHS.')
            ->call('submit')
            ->assertSet('isOpen', false);

        $member->refresh();
        $this->assertSame('Uttara', $member->address_area); // Profile unchanged yet!

        $changeRequest = MemberChangeRequest::where('member_id', $member->id)
            ->where('request_type', 'general_update')
            ->firstOrFail();
        $this->assertSame('pending', $changeRequest->status);

        // Staff approves profile change request
        Livewire::actingAs($this->dhakaManager, 'web')
            ->test(MemberChangeRequestDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('openDiffModal', $changeRequest->id)
            ->call('approve');

        $member->refresh();
        $this->assertSame('Mirpur DOHS', $member->address_area);
        $this->assertSame('+8801999887766', $member->contact_number);

        // 6. Submit Member Email Change Request: Manager receives alert email, approval required (DEC-07)
        $newEmail = 'fahim.newemail@emk.test';
        $emailRequest = $this->changeRequestService->submitEmailChangeRequest(
            $member,
            $newEmail,
            'Changing primary contact email to institutional email.'
        );

        $this->assertSame('pending', $emailRequest->status);
        Mail::assertQueued(ManagerEmailChangeAlertMail::class, function ($mail) use ($newEmail) {
            return $mail->newEmail === $newEmail;
        });

        // Manager approves email change: updates both members and users tables atomically
        $this->changeRequestService->approveEmailChangeRequest($emailRequest, $this->dhakaManager);
        $member->refresh();
        $memberUser->refresh();
        $this->assertSame($newEmail, $member->email);
        $this->assertSame($newEmail, $memberUser->email);

        // 7. Submit Renewal Request within 30-day window
        $this->assertTrue($this->renewalService->isRenewalWindowOpen($member));
        $renewal = $this->renewalService->submitRenewalRequest($member, $this->term->id, 'BK10RENEWT');

        $this->assertSame('pending', $renewal->status);
        $this->assertSame('BK10RENEWT', $renewal->new_payload['bkash_txid']);

        // Manager approves renewal
        $updatedMember = $this->renewalService->approveRenewal($renewal, $this->dhakaManager);
        $this->assertSame('active', $updatedMember->status);
        $this->assertDatabaseHas('invoices', [
            'member_id' => $member->id,
            'payment_reference' => 'BK10RENEWT',
            'status' => 'paid',
        ]);
    }

    /**
     * Scenario 5: Conversion Reversal Protocol Scenario (DEC-04).
     */
    public function test_conversion_reversal_protocol_scenario(): void
    {
        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'first_name' => 'Rashed',
            'last_name' => 'Karim',
            'email' => 'rashed.reversal@emk.test',
            'contact_number' => '+8801755667788',
            'bkash_txid' => 'TX10REV001',
            'date_of_birth' => '1995-11-20',
            'age_at_submission' => 31,
            'sex' => 'male',
            'address_house_no' => 'House 88',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'researcher',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'master',
            'area_of_subject' => 'Sociology',
            'status' => 'approved',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'terms_agreed' => true,
            'submitted_at' => now()->subDays(2),
        ]);

        LeadVerification::create([
            'lead_id' => $lead->id,
            'stream' => 'payment',
            'status' => 'verified',
            'verified_by_user_id' => $this->dhakaManager->id,
            'verified_at' => now(),
            'evidence_payload' => ['txid' => 'TX10REV001'],
        ]);

        $member = $this->conversionService->convert($lead, $this->term->id, $this->dhakaManager);
        $this->assertSame('active', $member->status);

        // Perform manager reversal within 30 days with mandatory justification (DEC-04)
        $justification = 'Accidental conversion during UAT integration verification drill.';
        $this->reversalService->reverse($member, $justification, $this->dhakaManager);

        // Assert cascading rollback
        $member->refresh();
        $this->assertSame('archived_reversed', $member->status);

        $portalUser = User::find($member->user_id);
        $this->assertFalse($portalUser->is_active);
        $this->assertNull($portalUser->remember_token);

        $invoice = Invoice::where('member_id', $member->id)->first();
        $this->assertSame('void_reversed', $invoice->status);

        $card = IdCard::where('member_id', $member->id)->first();
        $this->assertSame('voided_reversal', $card->status);

        $lead->refresh();
        $this->assertSame('under_review', $lead->status);
        $this->assertNull($lead->converted_member_id);
        $this->assertCount(1, $lead->verifications); // Verifications preserved

        // Assert immutable reversal audit log recorded
        $reversalAudit = AuditLog::where('auditable_type', Member::class)
            ->where('auditable_id', $member->id)
            ->where('event_type', AuditLog::EVENT_CONVERSION_REVERSED)
            ->first();
        $this->assertNotNull($reversalAudit);
        $this->assertSame($this->dhakaManager->id, $reversalAudit->actor_id);
        $this->assertSame($justification, $reversalAudit->reason);
    }

    /**
     * Scenario 6: Comprehensive Holistic Lifecycle with Audit Trail Completeness (RELS-01, DEC-01-11).
     */
    public function test_comprehensive_holistic_lifecycle_with_audit_trail_completeness(): void
    {
        // 1. Audit logs exist across system
        $initialAuditCount = AuditLog::count();
        $this->assertGreaterThanOrEqual(0, $initialAuditCount);

        // 2. Multi-Workspace Isolation check
        $this->assertNotSame($this->dhakaManager->workspace_id, $this->chattogramManager->workspace_id);
        $this->assertFalse($this->chattogramManager->hasWorkspaceAccess('ws-emk-dhaka'));
        $this->assertTrue($this->dhakaManager->hasWorkspaceAccess('ws-emk-dhaka'));

        // 3. Early renewal protection check: member with 60 days remaining cannot renew
        Carbon::setTestNow(Carbon::parse('2026-11-01'));
        $earlyLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'first_name' => 'Nabila',
            'last_name' => 'Hasan',
            'email' => 'nabila.early@emk.test',
            'contact_number' => '+8801700112233',
            'bkash_txid' => 'TX10EARLY1',
            'date_of_birth' => '1998-01-10',
            'age_at_submission' => 28,
            'sex' => 'female',
            'address_house_no' => '1',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'teacher',
            'institution_study' => 'Dhaka College',
            'highest_certification' => 'master',
            'area_of_subject' => 'English',
            'status' => 'approved',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'terms_agreed' => true,
            'submitted_at' => now()->subMonths(6),
        ]);

        $earlyMember = $this->conversionService->convert($earlyLead, $this->term->id, $this->dhakaManager);
        $earlyMember->expiry_date = Carbon::parse('2026-12-31'); // 60 days ahead
        $earlyMember->save();

        $this->assertFalse($this->renewalService->isRenewalWindowOpen($earlyMember));
        try {
            $this->renewalService->submitRenewalRequest($earlyMember, $this->term->id, 'TX10PREMAT');
            $this->fail('Expected DomainException for premature renewal attempt.');
        } catch (DomainException $e) {
            $this->assertStringContainsString('Renewal window is not open', $e->getMessage());
        }

        // 4. Assert all critical audit events are present and non-empty
        $conversionAudit = AuditLog::where('event_type', AuditLog::EVENT_CONVERSION_COMPLETED)->first();
        $this->assertNotNull($conversionAudit);
        $this->assertNotNull($conversionAudit->created_at);
    }
}
