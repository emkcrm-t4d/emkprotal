<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Forms\Services\DuplicateSubmissionGuard;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Exceptions\UnauthorizedVerificationException;
use App\Domain\Leads\Exceptions\UnmetConversionInvariantException;
use App\Domain\Leads\Mail\LeadRejectionMail;
use App\Domain\Leads\Mail\MissingItemsNotificationMail;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Leads\Models\LeadVerification;
use App\Domain\Leads\Services\LeadCommunicationService;
use App\Domain\Leads\Services\LeadConversionService;
use App\Domain\Leads\Services\VerificationService;
use App\Domain\Members\Exceptions\InvalidConversionReversalException;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Services\ConversionReversalService;
use App\Jobs\SendMemberWelcomeEmailJob;
use App\Livewire\Leads\LeadDirectory;
use App\Livewire\Leads\LeadProfile;
use App\Livewire\Members\Modals\ConversionReversalModal;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DefaultMembershipFormSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class EndToEndLeadVerificationAndConversionTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $dhakaStaff;
    protected User $sylhetStaff;
    protected User $superAdmin;
    protected MembershipTerm $term;

    protected VerificationService $verificationService;
    protected LeadCommunicationService $communicationService;
    protected LeadConversionService $conversionService;
    protected ConversionReversalService $reversalService;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Mail::fake();
        Queue::fake();

        // 1. Seed complete schema, roles, permissions, spaces, and forms
        $this->seed(DatabaseSeeder::class);
        $this->seed(DefaultMembershipFormSeeder::class);

        // Ensure Sylhet workspace exists for tenancy defense assertions
        $sylhetSpace = DB::table('spaces')->where('code', 'CR-SYL')->first();
        if ($sylhetSpace) {
            DB::table('workspaces')->updateOrInsert(
                ['id' => 'ws-corner-sylhet'],
                [
                    'space_id' => $sylhetSpace->id,
                    'name' => 'American Corner Sylhet',
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        // 2. Provision Users
        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'EMK Workspace Manager',
            'email' => 'e2e.manager@emk.test',
            'password' => bcrypt('password123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');
        $this->manager->givePermissionTo([
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
        ]);

        $this->dhakaStaff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Admissions Staff',
            'email' => 'e2e.dhaka.staff@emk.test',
            'password' => bcrypt('password123'),
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
        ]);

        $this->sylhetStaff = User::create([
            'workspace_id' => 'ws-corner-sylhet',
            'name' => 'Sylhet Corner Staff',
            'email' => 'e2e.sylhet.staff@emk.test',
            'password' => bcrypt('password123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->sylhetStaff->assignRole('delegated_staff');
        $this->sylhetStaff->givePermissionTo(['leads.view']);

        $this->superAdmin = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Platform Superadmin',
            'email' => 'e2e.superadmin@emk.test',
            'password' => bcrypt('password123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('superadmin');

        // 3. Active Membership Term
        $this->term = MembershipTerm::where('workspace_id', 'ws-emk-dhaka')
            ->where('code', 'std-1yr')
            ->firstOrFail();

        // 4. Resolve Domain Services
        $this->verificationService = app(VerificationService::class);
        $this->communicationService = app(LeadCommunicationService::class);
        $this->conversionService = app(LeadConversionService::class);
        $this->reversalService = app(ConversionReversalService::class);
    }

    /**
     * Scenario 1 (LEAD-01 & Tenancy Defense):
     * Directory viewing, multi-facet search by name/bKash, and cross-workspace 403 isolation.
     */
    public function test_scenario_1_lead_directory_search_and_workspace_tenancy_defense(): void
    {
        // Provision Dhaka Lead
        $dhakaLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'first_name' => 'Farhan',
            'last_name' => 'Kabir',
            'email' => 'farhan.kabir@example.com',
            'contact_number' => '+8801711223344',
            'bkash_txid' => 'BKASHFAR01',
            'date_of_birth' => '1997-08-20',
            'age_at_submission' => 29,
            'sex' => 'male',
            'address_house_no' => 'House 14',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'BRAC University',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Computer Science',
            'status' => 'submitted',
            'terms_agreed' => true,
            'submitted_at' => now(),
        ]);

        // 1. Dhaka Staff views directory
        $response = $this->actingAs($this->dhakaStaff, 'web')
            ->get(route('crm.leads', ['workspace' => 'ws-emk-dhaka']));
        $response->assertStatus(200);
        $response->assertSee('Farhan Kabir');
        $response->assertSee('BKASHFAR01');

        // 2. Multi-facet search in LeadDirectory
        Livewire::actingAs($this->dhakaStaff, 'web')
            ->test(LeadDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->set('search', 'Farhan')
            ->assertSee('Farhan Kabir')
            ->set('search', 'BKASHFAR01')
            ->assertSee('Farhan Kabir')
            ->set('search', 'NONEXISTENTTXID')
            ->assertDontSee('Farhan Kabir');

        // 3. Cross-Workspace Tenancy Defense: Sylhet staff attempting to access Dhaka workspace directly receives HTTP 403
        $crossWorkspaceResponse = $this->actingAs($this->sylhetStaff, 'web')
            ->get(route('crm.leads.show', [
                'workspace' => 'ws-emk-dhaka',
                'lead' => $dhakaLead->id,
            ]));
        $crossWorkspaceResponse->assertStatus(403);

        // Direct Livewire component mount with cross-workspace entity aborts with HTTP 403
        Livewire::actingAs($this->sylhetStaff, 'web')
            ->test(LeadProfile::class, [
                'workspace' => 'ws-corner-sylhet',
                'lead' => $dhakaLead,
            ])
            ->assertStatus(403);
    }

    /**
     * Scenario 2 (LEAD-02 & LEAD-03 Tri-State Verifications):
     * Minor consent enforcement (DEC-09), 5-point photo compliance, staff payment rejection, and manager bKash verification.
     */
    public function test_scenario_2_tristate_verifications_minor_consent_and_manager_payment_authority(): void
    {
        // 1. Create minor applicant (under 18)
        $minorLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'first_name' => 'Arefin',
            'last_name' => 'Shuvo',
            'email' => 'arefin.minor@example.com',
            'contact_number' => '+8801755667788',
            'bkash_txid' => 'BKASHMIN01',
            'date_of_birth' => '2009-03-15',
            'age_at_submission' => 17,
            'sex' => 'male',
            'address_house_no' => 'Block C',
            'address_area' => 'Banani',
            'address_zip_code' => '1213',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'Notre Dame College',
            'highest_certification' => 'ssc',
            'area_of_subject' => 'Science',
            'status' => 'submitted',
            'terms_agreed' => true,
            'has_parental_consent' => true,
            'submitted_at' => now(),
        ]);

        LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $minorLead->id,
            'document_type' => 'identity_document',
            'original_filename' => 'birth_certificate.pdf',
            'storage_path' => "workspaces/ws-emk-dhaka/leads/{$minorLead->uuid}/birth_cert.pdf",
            'size_bytes' => 2048,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', 'dummy-data-birth-cert'),
            'youth_protection' => true,
        ]);

        // 2. Delegated staff verifies documents with minor consent checked per DEC-09
        $docVerification = $this->verificationService->verifyDocuments($minorLead, $this->dhakaStaff, [
            'id_document_type' => 'birth_certificate',
            'id_document_number' => '19991234567890123',
            'name_matches_form' => true,
            'dob_matches_form' => true,
            'under_18_consent_verified' => true,
            'guardian_name' => 'Rafiqul Islam',
            'guardian_contact_phone' => '+8801711998877',
            'decision' => 'verified',
            'notes' => 'Minor consent form verified with birth certificate.',
        ]);

        $minorLead->refresh();
        $this->assertSame('verified', $minorLead->document_verification_status);
        $this->assertSame('verified', $docVerification->status);
        $this->assertTrue($docVerification->evidence_payload['under_18_consent_verified']);

        // 3. Delegated staff completes 5-point studio photo checklist
        $photoVerification = $this->verificationService->verifyPhoto($minorLead, $this->dhakaStaff, [
            'white_background' => true,
            'no_glasses' => true,
            'ears_visible' => true,
            'regular_attire' => true,
            'no_selfie' => true,
            'decision' => 'verified',
            'notes' => 'Studio compliant white background photo.',
        ]);

        $minorLead->refresh();
        $this->assertSame('verified', $minorLead->photo_verification_status);
        $this->assertSame('verified', $photoVerification->status);

        // 4. Delegated staff attempts payment verification -> REJECTED (T-01-07, DEC-05)
        try {
            $this->verificationService->verifyPayment($minorLead, $this->dhakaStaff, [
                'decision' => 'verified',
                'amount_bdt' => 1000.0,
                'bkash_txid' => $minorLead->bkash_txid,
            ]);
            $this->fail('Expected UnauthorizedVerificationException when staff attempts payment verification.');
        } catch (UnauthorizedVerificationException $e) {
            $this->assertStringContainsString('leads.verify_payment', $e->getMessage());
        }

        // 5. Workspace Manager verifies bKash TxID with duplicate check
        $paymentVerification = $this->verificationService->verifyPayment($minorLead, $this->manager, [
            'decision' => 'verified',
            'amount_bdt' => 1000.0,
            'bkash_txid' => $minorLead->bkash_txid,
            'notes' => 'bKash merchant statement verified.',
        ]);

        $minorLead->refresh();
        $this->assertSame('verified', $minorLead->payment_verification_status);
        $this->assertSame('verified', $paymentVerification->status);

        // All 3 streams verified!
        $this->assertTrue(
            $minorLead->payment_verification_status === 'verified'
            && $minorLead->document_verification_status === 'verified'
            && $minorLead->photo_verification_status === 'verified'
        );
    }

    /**
     * Scenario 3 (LEAD-04 & LEAD-07 Missing Items, Rejection, and Blocking):
     * Missing items mail dispatch, rejection with taxonomy code, reapplication history, and abusive submitter blocking.
     */
    public function test_scenario_3_missing_items_rejection_taxonomy_reapplication_and_abusive_blocking(): void
    {
        // 1. Staff flags missing items and sends notification
        $incompleteLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'first_name' => 'Sumona',
            'last_name' => 'Haque',
            'email' => 'sumona.missing@example.com',
            'contact_number' => '+8801799887766',
            'bkash_txid' => 'BKASHSUM01',
            'date_of_birth' => '2001-11-05',
            'age_at_submission' => 25,
            'sex' => 'female',
            'address_house_no' => '5',
            'address_area' => 'Uttara',
            'address_zip_code' => '1230',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'IUB',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Media Studies',
            'status' => 'under_review',
            'terms_agreed' => true,
            'submitted_at' => now(),
        ]);

        $this->communicationService->sendMissingItemsNotification(
            $incompleteLead,
            $this->dhakaStaff,
            ['National ID / Passport Scan', 'White Background Photo'],
            'Your uploaded scan was illegible and photo was a selfie.'
        );

        $incompleteLead->refresh();
        $this->assertSame('missing_items', $incompleteLead->status);
        Mail::assertQueued(MissingItemsNotificationMail::class, function ($mail) use ($incompleteLead) {
            return $mail->hasTo($incompleteLead->email);
        });

        // 2. Manager rejects fraudulent lead with taxonomy code INVALID_TXID
        $fraudLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'first_name' => 'Fahim',
            'last_name' => 'Reza',
            'email' => 'fahim.fraud@example.com',
            'contact_number' => '+8801788776655',
            'bkash_txid' => 'FAKETXID01',
            'date_of_birth' => '1996-01-15',
            'age_at_submission' => 30,
            'sex' => 'male',
            'address_house_no' => 'House 9',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'other',
            'institution_study' => 'None',
            'highest_certification' => 'hsc',
            'area_of_subject' => 'Business',
            'status' => 'under_review',
            'terms_agreed' => true,
            'submitted_at' => now(),
        ]);

        $this->communicationService->rejectLead(
            $fraudLead,
            $this->manager,
            'INVALID_TXID',
            'Transaction ID does not exist in bKash merchant statement.'
        );

        $fraudLead->refresh();
        $this->assertSame('rejected', $fraudLead->status);
        Mail::assertQueued(LeadRejectionMail::class, function ($mail) use ($fraudLead) {
            return $mail->hasTo($fraudLead->email);
        });

        // 3. Clean reapplicant submits new application: past rejection history appears on profile
        $reapplicantLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'first_name' => 'Fahim',
            'last_name' => 'Reza',
            'email' => 'fahim.fraud@example.com', // Same email reapplying
            'contact_number' => '+8801788776655',
            'bkash_txid' => 'LEGITTX001',
            'date_of_birth' => '1996-01-15',
            'age_at_submission' => 30,
            'sex' => 'male',
            'address_house_no' => 'House 9',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'other',
            'institution_study' => 'None',
            'highest_certification' => 'hsc',
            'area_of_subject' => 'Business',
            'status' => 'submitted',
            'terms_agreed' => true,
            'submitted_at' => now(),
        ]);

        $this->actingAs($this->dhakaStaff, 'web');
        $profile = new LeadProfile();
        $profile->mount('ws-emk-dhaka', $reapplicantLead);

        $this->assertTrue($profile->hasReapplicationHistory);
        $this->assertNotEmpty($profile->historicalLeads);
        $this->assertSame($fraudLead->id, $profile->historicalLeads[0]['id']);
        $this->assertSame('rejected', $profile->historicalLeads[0]['status']);

        // 4. Manager blocks abusive submitter email
        $abusiveLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'first_name' => 'Spammer',
            'last_name' => 'Actor',
            'email' => 'spammer.abusive@example.com',
            'contact_number' => '+8801700112233',
            'bkash_txid' => 'SPAMTXID01',
            'date_of_birth' => '1990-01-01',
            'age_at_submission' => 36,
            'sex' => 'male',
            'address_house_no' => '0',
            'address_area' => 'Unknown',
            'address_zip_code' => '0000',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'other',
            'institution_study' => 'None',
            'highest_certification' => 'hsc',
            'area_of_subject' => 'None',
            'status' => 'under_review',
            'terms_agreed' => true,
            'submitted_at' => now(),
        ]);

        $this->communicationService->blockApplicantEmail(
            $abusiveLead,
            $this->manager,
            'Abusive email submitting fabricated receipts repeatedly.'
        );

        $abusiveLead->refresh();
        $this->assertSame('blocked', $abusiveLead->status);

        // Future submissions from this email blocked at form boundary
        try {
            DuplicateSubmissionGuard::assertEmailNotBlocked('spammer.abusive@example.com');
            $this->fail('Expected ValidationException when blocked email attempts submission.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
            $this->assertStringContainsString('restricted from submitting', $e->errors()['email'][0]);
        }
    }

    /**
     * Scenario 4 (LEAD-05, ARCH-03, DEC-03 Atomic Conversion):
     * Invariant gate, dynamic date computation (DEC-08), monotonic Member ID, post-conversion invoice, badge queue, and audit log.
     */
    public function test_scenario_4_atomic_lead_to_member_conversion_and_invariant_gate(): void
    {
        // 1. Incomplete Lead: conversion invariant gate blocks conversion
        $incompleteLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'first_name' => 'Tasnim',
            'last_name' => 'Farhana',
            'email' => 'tasnim.incomplete@example.com',
            'contact_number' => '+8801744556677',
            'bkash_txid' => 'BKASHTAS01',
            'date_of_birth' => '1999-07-22',
            'age_at_submission' => 27,
            'sex' => 'female',
            'address_house_no' => 'House 7',
            'address_area' => 'Mohakhali',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'North South University',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Biochemistry',
            'status' => 'under_review',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'pending', // Incomplete!
            'photo_verification_status' => 'verified',
            'terms_agreed' => true,
            'submitted_at' => now(),
        ]);

        try {
            $this->conversionService->convert($incompleteLead, $this->term->id, $this->manager);
            $this->fail('Expected UnmetConversionInvariantException for incomplete streams.');
        } catch (UnmetConversionInvariantException $e) {
            $this->assertArrayHasKey('document', $e->getUnmetConditions());
        }

        // 2. Fully Verified & Approved Lead
        $verifiedLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'first_name' => 'Mehedi',
            'last_name' => 'Hasan',
            'email' => 'mehedi.verified@example.com',
            'contact_number' => '+8801755667799',
            'bkash_txid' => 'BKASHMEH01',
            'date_of_birth' => '1998-12-01',
            'age_at_submission' => 28,
            'sex' => 'male',
            'address_house_no' => 'House 22',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'entrepreneur',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Finance',
            'status' => 'approved',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'terms_agreed' => true,
            'submitted_at' => now()->subDay(),
        ]);

        LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $verifiedLead->id,
            'document_type' => 'identity_document',
            'original_filename' => 'passport.pdf',
            'storage_path' => "workspaces/ws-emk-dhaka/leads/{$verifiedLead->uuid}/passport.pdf",
            'size_bytes' => 4096,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', 'dummy-data-passport'),
        ]);

        // 3. Manager triggers atomic conversion
        $member = $this->conversionService->convert($verifiedLead, $this->term->id, $this->manager);

        // A. Monotonic Member ID pattern
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{3}-\d{4}-\d{5}$/', $member->member_id);

        // B. Dynamic start and expiry dates per DEC-08
        $this->assertSame(now()->toDateString(), $member->start_date->toDateString());
        $expectedExpiry = now()->addMonths($this->term->duration_months)->toDateString();
        $this->assertSame($expectedExpiry, $member->expiry_date->toDateString());

        // C. Post-conversion paid invoice referencing bKash TxID
        $invoice = Invoice::where('member_id', $member->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('BKASHMEH01', $invoice->payment_reference);
        $this->assertEquals($this->term->fee_bdt, $invoice->amount_bdt);

        // D. ID card queued in provided_for_printing
        $card = IdCard::where('member_id', $member->id)->first();
        $this->assertNotNull($card);
        $this->assertSame('provided_for_printing', $card->status);

        // E. Portal user provisioned
        $user = User::find($member->user_id);
        $this->assertNotNull($user);
        $this->assertSame('member', $user->guard);
        $this->assertTrue($user->is_active);
        $this->assertTrue($user->must_reset_password);

        // F. Immutable audit log written
        $auditLog = AuditLog::where('auditable_type', Member::class)
            ->where('auditable_id', $member->id)
            ->where('event_type', AuditLog::EVENT_CONVERSION_COMPLETED)
            ->first();
        $this->assertNotNull($auditLog);
        $this->assertSame($this->manager->id, $auditLog->actor_id);

        // G. Horizon welcome email dispatched
        Queue::assertPushed(SendMemberWelcomeEmailJob::class, function ($job) use ($member) {
            return $job->memberId === $member->id;
        });

        // H. Lead updated to converted
        $verifiedLead->refresh();
        $this->assertSame('converted', $verifiedLead->status);
        $this->assertSame($member->id, $verifiedLead->converted_member_id);
    }

    /**
     * Scenario 5 (LEAD-06, ARCH-04, DEC-04 30-Day Conversion Reversal):
     * Permission gating, justification length, cascading entity rollback, Day 31 cutoff, and card delivery lock.
     */
    public function test_scenario_5_30_day_conversion_reversal_guards_and_cascading_rollback(): void
    {
        // 1. Provision converted lead and member
        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'first_name' => 'Nazmul',
            'last_name' => 'Huda',
            'email' => 'nazmul.reversal@example.com',
            'contact_number' => '+8801766778899',
            'bkash_txid' => 'BKASHNAZ01',
            'date_of_birth' => '1995-09-10',
            'age_at_submission' => 31,
            'sex' => 'male',
            'address_house_no' => 'Flat 2A',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'undergraduate_degree',
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
            'verified_by_user_id' => $this->manager->id,
            'verified_at' => now(),
            'evidence_payload' => ['txid' => $lead->bkash_txid],
        ]);

        $member = $this->conversionService->convert($lead, $this->term->id, $this->manager);

        // 2. Reversal attempted by delegated staff -> REJECTED (403)
        try {
            $this->reversalService->reverse(
                $member,
                'Valid justification string of at least twenty characters here.',
                $this->dhakaStaff
            );
            $this->fail('Expected InvalidConversionReversalException when staff attempts reversal.');
        } catch (InvalidConversionReversalException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertStringContainsString('leads.reverse_conversion', $e->getMessage());
        }

        // 3. Reversal attempted with short justification -> REJECTED (< 20 chars)
        try {
            $this->reversalService->reverse($member, 'Too short', $this->manager);
            $this->fail('Expected InvalidConversionReversalException for short justification.');
        } catch (InvalidConversionReversalException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertStringContainsString('at least 20 characters', $e->getMessage());
        }

        // 4. Manager executes reversal with valid 20+ char justification
        $validReason = 'Accidental approval during internal system integration test.';
        $this->reversalService->reverse($member, $validReason, $this->manager);

        // Verify cascading rollback
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
        $this->assertCount(1, $lead->verifications); // Preserved!

        $reversalAudit = AuditLog::where('auditable_type', Member::class)
            ->where('auditable_id', $member->id)
            ->where('event_type', AuditLog::EVENT_CONVERSION_REVERSED)
            ->first();
        $this->assertNotNull($reversalAudit);
        $this->assertSame($this->manager->id, $reversalAudit->actor_id);
        $this->assertSame($validReason, $reversalAudit->reason);

        // 5. Day 31 cutoff: Cannot reverse beyond 30 days
        $oldLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'first_name' => 'Old',
            'last_name' => 'Member',
            'email' => 'old.member@example.com',
            'contact_number' => '+8801700998877',
            'bkash_txid' => 'BKASHOLD01',
            'date_of_birth' => '1990-01-01',
            'age_at_submission' => 36,
            'sex' => 'male',
            'address_house_no' => '1',
            'address_area' => 'Old Town',
            'address_zip_code' => '1100',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'other',
            'institution_study' => 'None',
            'highest_certification' => 'hsc',
            'area_of_subject' => 'None',
            'status' => 'approved',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'terms_agreed' => true,
            'submitted_at' => now()->subDays(40),
        ]);
        $oldMember = $this->conversionService->convert($oldLead, $this->term->id, $this->manager);
        $oldMember->created_at = Carbon::now()->subDays(31);
        $oldMember->save();

        try {
            $this->reversalService->reverse($oldMember, 'Attempting reversal after statutory window expired.', $this->manager);
            $this->fail('Expected InvalidConversionReversalException on Day 31.');
        } catch (InvalidConversionReversalException $e) {
            $this->assertStringContainsString('Statutory 30-day cutoff expired', $e->getMessage());
        }

        // 6. Delivery lock: Cannot reverse if physical card was marked delivered
        $cardDeliveredLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'first_name' => 'Delivered',
            'last_name' => 'Card',
            'email' => 'delivered.card@example.com',
            'contact_number' => '+8801711224466',
            'bkash_txid' => 'BKASHDEL01',
            'date_of_birth' => '1995-05-05',
            'age_at_submission' => 31,
            'sex' => 'female',
            'address_house_no' => '2',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'other',
            'institution_study' => 'None',
            'highest_certification' => 'hsc',
            'area_of_subject' => 'None',
            'status' => 'approved',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'terms_agreed' => true,
            'submitted_at' => now()->subDays(5),
        ]);
        $deliveredMember = $this->conversionService->convert($cardDeliveredLead, $this->term->id, $this->manager);
        IdCard::where('member_id', $deliveredMember->id)->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        try {
            $this->reversalService->reverse($deliveredMember, 'Attempting reversal after card delivered.', $this->manager);
            $this->fail('Expected InvalidConversionReversalException when card delivered.');
        } catch (InvalidConversionReversalException $e) {
            $this->assertStringContainsString('Physical membership card has already been delivered', $e->getMessage());
        }
    }
}
