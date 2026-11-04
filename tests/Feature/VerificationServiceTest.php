<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Leads\Exceptions\UnauthorizedVerificationException;
use App\Domain\Leads\Exceptions\UnmetConversionInvariantException;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadVerification;
use App\Domain\Leads\Services\VerificationService;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected VerificationService $service;
    protected User $manager;
    protected User $staff;
    protected Lead $adultLead;
    protected Lead $minorLead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->service = app(VerificationService::class);

        // 1. Setup Workspace Manager (has leads.verify_payment, leads.verify_documents, leads.verify_photo, leads.approve)
        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Manager Person',
            'email' => 'manager.test@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');

        // 2. Setup Delegated Staff (has leads.verify_documents, leads.verify_photo; NO verify_payment, NO approve)
        $this->staff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Staff Person',
            'email' => 'staff.test@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staff->assignRole('delegated_staff');

        // 3. Setup Adult Lead (age 24)
        $this->adultLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'submitted',
            'bkash_txid' => 'ADULTTX001',
            'first_name' => 'Adult',
            'last_name' => 'Applicant',
            'email' => 'adult@example.com',
            'contact_number' => '+8801712345001',
            'date_of_birth' => '2002-05-10',
            'age_at_submission' => 24,
            'sex' => 'male',
            'address_house_no' => '10',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'student',
            'institution_study' => 'DU',
            'highest_certification' => 'bachelors',
            'area_of_subject' => 'Law',
            'payment_verification_status' => 'pending',
            'document_verification_status' => 'pending',
            'photo_verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        // 4. Setup Minor Lead (age 16)
        $this->minorLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'submitted',
            'bkash_txid' => 'MINORTX001',
            'first_name' => 'Minor',
            'last_name' => 'Applicant',
            'email' => 'minor@example.com',
            'contact_number' => '+8801712345002',
            'date_of_birth' => '2010-08-15',
            'age_at_submission' => 16,
            'sex' => 'female',
            'address_house_no' => '25',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'student',
            'institution_study' => 'Scholastica',
            'highest_certification' => 'o_level',
            'area_of_subject' => 'Science',
            'has_parental_consent' => true,
            'payment_verification_status' => 'pending',
            'document_verification_status' => 'pending',
            'photo_verification_status' => 'pending',
            'submitted_at' => now(),
        ]);
    }

    public function test_manager_can_verify_payment_stream(): void
    {
        $verification = $this->service->verifyPayment($this->adultLead, $this->manager, [
            'bkash_txid' => 'ADULTTX001',
            'amount_bdt' => 1000.0,
            'notes' => 'Confirmed in merchant statement',
        ]);

        $this->assertInstanceOf(LeadVerification::class, $verification);
        $this->assertEquals('payment', $verification->stream);
        $this->assertEquals('verified', $verification->status);
        $this->assertEquals($this->manager->id, $verification->verified_by_user_id);
        $this->assertEquals('verified', $this->adultLead->fresh()->payment_verification_status);
        $this->assertEquals('under_review', $this->adultLead->fresh()->status);
    }

    public function test_staff_cannot_verify_payment_without_permission(): void
    {
        $this->expectException(UnauthorizedVerificationException::class);
        $this->expectExceptionMessage('leads.verify_payment');

        $this->service->verifyPayment($this->adultLead, $this->staff, [
            'bkash_txid' => 'ADULTTX001',
            'amount_bdt' => 1000.0,
        ]);
    }

    public function test_document_verification_requires_minor_consent_for_under_18(): void
    {
        // Minor applicant without parental consent verified -> MISSING_ITEMS
        $verification = $this->service->verifyDocuments($this->minorLead, $this->staff, [
            'id_document_type' => 'birth_certificate',
            'id_document_number' => 'BC1234567890',
            'name_matches_form' => true,
            'dob_matches_form' => true,
            'under_18_consent_verified' => false,
            'notes' => 'Birth cert verified, but parental consent missing',
        ]);

        $this->assertEquals('missing_items', $verification->status);
        $this->assertEquals('MISSING_GUARDIAN_CONSENT', $verification->rejection_reason_code);
        $this->assertEquals('missing_items', $this->minorLead->fresh()->document_verification_status);
        $this->assertEquals('missing_items', $this->minorLead->fresh()->status);

        // Now verify with parental consent confirmed -> VERIFIED
        $verificationSuccess = $this->service->verifyDocuments($this->minorLead, $this->staff, [
            'id_document_type' => 'birth_certificate',
            'id_document_number' => 'BC1234567890',
            'name_matches_form' => true,
            'dob_matches_form' => true,
            'under_18_consent_verified' => true,
            'guardian_name' => 'Mother Applicant',
            'notes' => 'Guardian consent signed and verified',
        ]);

        $this->assertEquals('verified', $verificationSuccess->status);
        $this->assertNull($verificationSuccess->rejection_reason_code);
        $this->assertEquals('verified', $this->minorLead->fresh()->document_verification_status);
    }

    public function test_document_verification_for_adult_applicant(): void
    {
        $verification = $this->service->verifyDocuments($this->adultLead, $this->staff, [
            'id_document_type' => 'national_id',
            'id_document_number' => 'NID1234567890',
            'name_matches_form' => true,
            'dob_matches_form' => true,
            'notes' => 'NID verified',
        ]);

        $this->assertEquals('verified', $verification->status);
        $this->assertEquals('verified', $this->adultLead->fresh()->document_verification_status);
    }

    public function test_photo_verification_requires_five_point_checklist(): void
    {
        // 1. Partial checklist (wearing glasses) -> reupload_requested
        $verificationFail = $this->service->verifyPhoto($this->adultLead, $this->staff, [
            'white_background' => true,
            'no_glasses' => false, // Fails checklist
            'ears_visible' => true,
            'regular_attire' => true,
            'no_selfie' => true,
            'notes' => 'Applicant wearing glasses; studio policy requires no eyeglasses.',
        ]);

        $this->assertEquals('reupload_requested', $verificationFail->status);
        $this->assertEquals('reupload_requested', $this->adultLead->fresh()->photo_verification_status);

        // 2. All 5 satisfied -> verified
        $verificationPass = $this->service->verifyPhoto($this->adultLead, $this->staff, [
            'white_background' => true,
            'no_glasses' => true,
            'ears_visible' => true,
            'regular_attire' => true,
            'no_selfie' => true,
            'notes' => 'All 5 checklist criteria satisfied',
        ]);

        $this->assertEquals('verified', $verificationPass->status);
        $this->assertEquals('verified', $this->adultLead->fresh()->photo_verification_status);
    }

    public function test_can_approve_lead_evaluates_true_only_when_all_three_streams_verified(): void
    {
        $this->assertFalse($this->service->canApproveLead($this->adultLead));

        // Verify Payment only
        $this->service->verifyPayment($this->adultLead, $this->manager, [
            'bkash_txid' => 'ADULTTX001',
            'amount_bdt' => 1000.0,
        ]);
        $this->assertFalse($this->service->canApproveLead($this->adultLead->fresh()));

        // Verify Documents only
        $this->service->verifyDocuments($this->adultLead, $this->staff, [
            'name_matches_form' => true,
            'dob_matches_form' => true,
        ]);
        $this->assertFalse($this->service->canApproveLead($this->adultLead->fresh()));

        // Verify Photo
        $this->service->verifyPhoto($this->adultLead, $this->staff, [
            'white_background' => true,
            'no_glasses' => true,
            'ears_visible' => true,
            'regular_attire' => true,
            'no_selfie' => true,
        ]);

        $this->assertTrue($this->service->canApproveLead($this->adultLead->fresh()));
    }

    public function test_approve_lead_succeeds_for_manager_when_all_streams_verified(): void
    {
        // Verify all 3 streams
        $this->adultLead->update([
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
        ]);

        $approvedLead = $this->service->approveLead($this->adultLead, $this->manager, 'Approved for induction');

        $this->assertEquals('approved', $approvedLead->status);
        $this->assertEquals('approved', $this->adultLead->fresh()->status);
    }

    public function test_approve_lead_fails_if_streams_incomplete(): void
    {
        $this->expectException(UnmetConversionInvariantException::class);
        $this->expectExceptionMessage('All three verification streams');

        // Adult lead has pending streams
        $this->service->approveLead($this->adultLead, $this->manager);
    }

    public function test_approve_lead_fails_for_staff_without_approve_permission(): void
    {
        $this->adultLead->update([
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
        ]);

        $this->expectException(UnauthorizedVerificationException::class);
        $this->expectExceptionMessage('leads.approve');

        $this->service->approveLead($this->adultLead, $this->staff);
    }
}
