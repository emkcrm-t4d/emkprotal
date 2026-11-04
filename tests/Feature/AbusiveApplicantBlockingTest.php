<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Services\DuplicateSubmissionGuard;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Services\LeadCommunicationService;
use App\Livewire\Forms\PublicMembershipForm;
use App\Livewire\Leads\LeadProfile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DefaultMembershipFormSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AbusiveApplicantBlockingTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Mail::fake();

        $this->seed(DatabaseSeeder::class);
        $this->seed(DefaultMembershipFormSeeder::class);

        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Manager Admin',
            'email' => 'manager.abuse@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');
    }

    public function test_blocked_applicant_email_cannot_submit_public_membership_form(): void
    {
        // 1. Existing lead with status 'blocked'
        $blockedEmail = 'abusive.fraudster@example.com';
        Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'blocked',
            'bkash_txid' => 'BLOCKEDTX1',
            'first_name' => 'Bad',
            'last_name' => 'Actor',
            'email' => $blockedEmail,
            'contact_number' => '+8801712345678',
            'date_of_birth' => '1995-05-20',
            'age_at_submission' => 31,
            'sex' => 'male',
            'address_house_no' => '1',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'other',
            'institution_study' => 'None',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'General',
            'payment_verification_status' => 'rejected',
            'document_verification_status' => 'rejected',
            'photo_verification_status' => 'rejected',
            'submitted_at' => now()->subDays(5),
        ]);

        // Direct guard check
        try {
            DuplicateSubmissionGuard::assertEmailNotBlocked($blockedEmail);
            $this->fail('Expected ValidationException from assertEmailNotBlocked');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
            $this->assertStringContainsString('restricted from submitting further applications', $e->errors()['email'][0]);
        }

        // Livewire public form submission check
        $idFile = UploadedFile::fake()->create('id.pdf', 200, 'application/pdf');
        $photoFile = UploadedFile::fake()->image('photo.jpg', 600, 600);

        Livewire::test(PublicMembershipForm::class)
            ->set('first_name', 'Bad')
            ->set('last_name', 'Actor')
            ->set('american_space', 'emk_center')
            ->set('sex', 'male')
            ->set('date_of_birth', '1995-05-20')
            ->set('contact_number', '+8801712345678')
            ->set('email', $blockedEmail)
            ->set('address_house_no', '12')
            ->set('address_area', 'Dhanmondi')
            ->set('address_zip_code', '1209')
            ->set('address_division', 'dhaka')
            ->set('address_district', 'dhaka')
            ->set('occupation', 'student')
            ->set('institution_study', 'University of Dhaka')
            ->set('highest_certification', 'undergraduate_degree')
            ->set('area_of_subject', 'Business')
            ->set('identity_document_files', [$idFile])
            ->set('photo_file', $photoFile)
            ->set('bkash_txid', 'FRESHBKASH')
            ->set('terms_agreed', true)
            ->call('submit')
            ->assertHasErrors(['email']);
    }

    public function test_applicant_with_prior_rejected_lead_can_submit_fresh_public_form(): void
    {
        $reapplicantEmail = 'legit.reapplicant@example.com';

        // Prior rejected application
        Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'rejected',
            'bkash_txid' => 'REJECTTX01',
            'first_name' => 'John',
            'last_name' => 'Reapplicant',
            'email' => $reapplicantEmail,
            'contact_number' => '+8801712345777',
            'date_of_birth' => '1999-03-15',
            'age_at_submission' => 27,
            'sex' => 'male',
            'address_house_no' => '44',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Science',
            'payment_verification_status' => 'pending',
            'document_verification_status' => 'rejected',
            'photo_verification_status' => 'pending',
            'submitted_at' => now()->subDays(10),
        ]);

        // Direct guard check must pass without exception
        DuplicateSubmissionGuard::assertEmailNotBlocked($reapplicantEmail);
        DuplicateSubmissionGuard::assertUniqueEmail($reapplicantEmail);
        $this->assertTrue(true);

        // Submit fresh form with new bKash TxID
        $idFile = UploadedFile::fake()->create('clean_id.pdf', 250, 'application/pdf');
        $photoFile = UploadedFile::fake()->image('clean_photo.jpg', 600, 600);

        $component = Livewire::test(PublicMembershipForm::class)
            ->set('first_name', 'John')
            ->set('last_name', 'Reapplicant')
            ->set('american_space', 'emk_center')
            ->set('sex', 'male')
            ->set('date_of_birth', '1999-03-15')
            ->set('contact_number', '+8801712345777')
            ->set('email', $reapplicantEmail)
            ->set('address_house_no', '44')
            ->set('address_area', 'Gulshan')
            ->set('address_zip_code', '1212')
            ->set('address_division', 'dhaka')
            ->set('address_district', 'dhaka')
            ->set('occupation', 'student')
            ->set('institution_study', 'Dhaka University')
            ->set('highest_certification', 'undergraduate_degree')
            ->set('area_of_subject', 'Science')
            ->set('identity_document_files', [$idFile])
            ->set('photo_file', $photoFile)
            ->set('bkash_txid', 'CLEANTX002')
            ->set('terms_agreed', true)
            ->call('submit');

        $component->assertHasNoErrors();
        $this->assertTrue($component->get('submitted'));

        // Assert 2 leads exist with this email: 1 rejected, 1 submitted
        $leads = Lead::where('email', $reapplicantEmail)->orderBy('id')->get();
        $this->assertCount(2, $leads);
        $this->assertEquals('rejected', $leads[0]->status);
        $this->assertEquals('submitted', $leads[1]->status);
    }

    public function test_reapplication_shows_previous_rejection_history_on_profile(): void
    {
        $reapplicantEmail = 'history.reapplicant@example.com';

        // 1. Old rejected lead
        $oldLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'rejected',
            'bkash_txid' => 'OLDREJTX01',
            'first_name' => 'Alex',
            'last_name' => 'History',
            'email' => $reapplicantEmail,
            'contact_number' => '+8801712345999',
            'date_of_birth' => '1997-07-20',
            'age_at_submission' => 29,
            'sex' => 'male',
            'address_house_no' => '10',
            'address_area' => 'Banani',
            'address_zip_code' => '1213',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'student',
            'institution_study' => 'NSU',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'BBA',
            'submitted_at' => now()->subDays(20),
        ]);

        // Add verification with rejection reason code
        DB::table('lead_verifications')->insert([
            'lead_id' => $oldLead->id,
            'stream' => 'document',
            'status' => 'rejected',
            'verified_by_user_id' => $this->manager->id,
            'verified_at' => now()->subDays(20),
            'evidence_payload' => json_encode(['rejection_reason_code' => 'DOCUMENT_UNREADABLE']),
            'rejection_reason_code' => 'DOCUMENT_UNREADABLE',
            'notes' => 'NID unreadable',
            'created_at' => now()->subDays(20),
            'updated_at' => now()->subDays(20),
        ]);

        // 2. New submitted lead
        $newLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'NEWTXID999',
            'first_name' => 'Alex',
            'last_name' => 'History',
            'email' => $reapplicantEmail,
            'contact_number' => '+8801712345999',
            'date_of_birth' => '1997-07-20',
            'age_at_submission' => 29,
            'sex' => 'male',
            'address_house_no' => '10',
            'address_area' => 'Banani',
            'address_zip_code' => '1213',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'student',
            'institution_study' => 'NSU',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'BBA',
            'submitted_at' => now(),
        ]);

        // 3. Inspect new lead in LeadProfile
        Livewire::actingAs($this->manager)
            ->test(LeadProfile::class, [
                'workspace' => 'ws-emk-dhaka',
                'lead' => $newLead,
            ])
            ->assertSet('hasReapplicationHistory', true)
            ->assertSee('Applicant Reapplication Detected')
            ->assertSee('OLDREJTX01')
            ->assertSee('DOCUMENT_UNREADABLE');
    }

    public function test_email_blocking_is_audited_in_audit_logs(): void
    {
        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'under_review',
            'bkash_txid' => 'FRAUDTX001',
            'first_name' => 'Spam',
            'last_name' => 'Submitter',
            'email' => 'spam.submitter@example.com',
            'contact_number' => '+8801712345000',
            'date_of_birth' => '1990-01-01',
            'age_at_submission' => 36,
            'sex' => 'male',
            'address_house_no' => '99',
            'address_area' => 'Mirpur',
            'address_zip_code' => '1216',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'other',
            'institution_study' => 'None',
            'highest_certification' => 'other',
            'area_of_subject' => 'None',
            'submitted_at' => now(),
        ]);

        $justification = 'Repeatedly submitting falsified documents and spam requests.';
        $service = app(LeadCommunicationService::class);
        $service->blockApplicantEmail($lead, $this->manager, $justification);

        $this->assertEquals('blocked', $lead->fresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->manager->id,
            'event_type' => AuditLog::EVENT_LEAD_BLOCKED,
            'auditable_type' => Lead::class,
            'auditable_id' => $lead->id,
            'reason' => $justification,
        ]);
    }
}
