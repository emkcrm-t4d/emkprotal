<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Mail\ApplicationConfirmationMail;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Livewire\Forms\PublicMembershipForm;
use App\Livewire\Forms\SubmissionViewer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DefaultMembershipFormSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class EndToEndFormBuilderAndApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected Form $defaultForm;
    protected User $dhakaStaff;
    protected User $sylhetStaff;
    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Mail::fake();

        // 1. Seed complete database schema, roles, spaces, and default 31-field form
        $this->seed(DatabaseSeeder::class);
        $this->seed(DefaultMembershipFormSeeder::class);

        $this->defaultForm = Form::withoutGlobalScopes()
            ->where('slug', 'membership-application')
            ->firstOrFail();

        // Ensure ws-corner-sylhet workspace exists for tenancy tests
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

        // 2. Provision staff users for workspace tenancy assertions
        $this->dhakaStaff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Admissions Staff',
            'email' => 'e2e.dhaka.staff@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->dhakaStaff->assignRole('delegated_staff');

        $this->sylhetStaff = User::create([
            'workspace_id' => 'ws-corner-sylhet',
            'name' => 'Sylhet Admissions Staff',
            'email' => 'e2e.sylhet.staff@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->sylhetStaff->assignRole('delegated_staff');

        $this->superAdmin = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Super Administrator',
            'email' => 'e2e.superadmin@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('superadmin');
    }

    /**
     * Scenario 1: Default 31-field Form Baseline Verification (FORM-01, FORM-02)
     */
    public function test_scenario_1_default_31_field_form_baseline_verification(): void
    {
        $this->assertNotNull($this->defaultForm);
        $this->assertTrue($this->defaultForm->is_published);
        $this->assertTrue($this->defaultForm->is_default);
        $this->assertEquals('1.0-31f', $this->defaultForm->version);
        $this->assertEquals('ws-emk-dhaka', $this->defaultForm->workspace_id);

        $fields = $this->defaultForm->schema['fields'] ?? [];
        $this->assertCount(31, $fields, 'Official membership form must declare exactly 31 schema fields per FIELD-DICTIONARY.md');

        $fieldNames = array_column($fields, 'name');
        $expectedKeyFields = [
            'first_name',
            'middle_name',
            'last_name',
            'american_space',
            'email',
            'contact_number',
            'date_of_birth',
            'sex',
            'parental_consent_path',
            'address_house_no',
            'address_division',
            'address_district',
            'occupation',
            'institution_study',
            'identity_document_paths',
            'photo_path',
            'bkash_txid',
            'terms_agreed',
        ];

        foreach ($expectedKeyFields as $expectedField) {
            $this->assertContains($expectedField, $fieldNames, "Missing required baseline field: {$expectedField}");
        }
    }

    /**
     * Scenario 2: Public Form Rendering and Dynamic Cascades (FORM-02, FORM-03, FORM-04)
     */
    public function test_scenario_2_public_form_rendering_and_dynamic_cascades(): void
    {
        // 1. GET /apply/membership-application renders cleanly with 200 OK
        $response = $this->get('/apply/membership-application');
        $response->assertStatus(200);
        $response->assertSee('Membership Form for American Spaces in Bangladesh');

        // 2. Division-to-district cascade restricts available districts
        $component = Livewire::test(PublicMembershipForm::class);

        // Barishal Division cascade: exactly 6 districts
        $component->set('address_division', 'barishal');
        $barishalDistricts = $component->get('available_districts');
        $this->assertCount(6, $barishalDistricts);
        $this->assertContains('barishal', $barishalDistricts);
        $this->assertContains('patuakhali', $barishalDistricts);
        $this->assertNotContains('dhaka', $barishalDistricts);

        // Dhaka Division cascade: exactly 13 districts
        $component->set('address_division', 'dhaka');
        $dhakaDistricts = $component->get('available_districts');
        $this->assertCount(13, $dhakaDistricts);
        $this->assertContains('dhaka', $dhakaDistricts);
        $this->assertContains('gazipur', $dhakaDistricts);
        $this->assertNotContains('barishal', $dhakaDistricts);
    }

    /**
     * Scenario 3: Adult Membership Application Submission (FORM-02, FORM-04, FORM-06)
     */
    public function test_scenario_3_adult_membership_application_submission(): void
    {
        $identityDoc1 = UploadedFile::fake()->createWithContent('nid_front.pdf', '%PDF-1.4 SAMPLE-NID-FRONT-CONTENT');
        $identityDoc2 = UploadedFile::fake()->createWithContent('nid_back.pdf', '%PDF-1.4 SAMPLE-NID-BACK-CONTENT');
        $photoFile = UploadedFile::fake()->image('studio_headshot.jpg', 600, 600);

        $component = Livewire::test(PublicMembershipForm::class)
            ->set('first_name', 'Mubashir')
            ->set('middle_name', 'Hassan')
            ->set('last_name', 'Khandakar')
            ->set('american_space', 'emk_center')
            ->set('sex', 'male')
            ->set('date_of_birth', '2000-01-15') // Adult (age 24-26)
            ->set('address_house_no', 'Apartment 4B, Concord Tower')
            ->set('address_road_no', 'Road 11')
            ->set('address_area', 'Banani')
            ->set('address_zip_code', '1213')
            ->set('address_division', 'dhaka')
            ->set('address_district', 'dhaka')
            ->set('contact_number', '+8801712345678')
            ->set('email', 'mubashir.khandakar@example.com')
            ->set('website', 'https://mubashir.dev')
            ->set('occupation', 'entrepreneur')
            ->set('institution_study', 'North South University')
            ->set('highest_certification', 'undergraduate_degree')
            ->set('area_of_subject', 'Computer Science & Engineering')
            ->set('has_us_experience', '1')
            ->set('us_experience_location', 'Boston, MA')
            ->set('is_american_alumni', '1')
            ->set('american_institution_name', 'MIT OpenCourseWare Alumni')
            ->set('employer_name', 'Tech Solutions Ltd')
            ->set('job_title', 'Software Engineer')
            ->set('work_phone', '+8801812345678')
            ->set('identity_document_files', [$identityDoc1, $identityDoc2])
            ->set('photo_file', $photoFile)
            ->set('bkash_txid', 'BL79K2P09Q')
            ->set('terms_agreed', true)
            ->call('submit');

        $component->assertHasNoErrors();
        $this->assertTrue($component->get('submitted'));

        $referenceUuid = $component->get('reference_uuid');
        $this->assertNotNull($referenceUuid);

        // Assert Lead created in ws-emk-dhaka with all fields matching
        $lead = Lead::where('uuid', $referenceUuid)->first();
        $this->assertNotNull($lead);
        $this->assertEquals('ws-emk-dhaka', $lead->workspace_id);
        $this->assertEquals('EMK-DHK', $lead->target_space_code);
        $this->assertEquals('Mubashir', $lead->first_name);
        $this->assertEquals('Khandakar', $lead->last_name);
        $this->assertEquals('mubashir.khandakar@example.com', $lead->email);
        $this->assertEquals('BL79K2P09Q', $lead->bkash_txid);
        $this->assertEquals('submitted', $lead->status);
        $this->assertGreaterThanOrEqual(18, $lead->age_at_submission);
        $this->assertFalse($lead->has_parental_consent);

        // Assert files stored on private encrypted disk under UUID filenames
        $attachments = LeadAttachment::where('lead_id', $lead->id)->get();
        $this->assertCount(3, $attachments); // 2 identity docs + 1 studio photo

        foreach ($attachments as $att) {
            $this->assertTrue(Storage::disk('private')->exists($att->storage_path));
            $this->assertStringNotContainsString($att->original_filename, $att->storage_path);
            $this->assertMatchesRegularExpression('/[a-f0-9\-]{36}\.[a-z0-9]+$/i', $att->storage_path);

            // Assert encrypted at rest
            $rawCiphertext = Storage::disk('private')->get($att->storage_path);
            $decrypted = Crypt::decryptString($rawCiphertext);
            $this->assertNotEmpty($decrypted);
        }

        // Assert confirmation email queued to applicant
        Mail::assertQueued(ApplicationConfirmationMail::class, function (ApplicationConfirmationMail $mail) use ($referenceUuid) {
            return $mail->hasTo('mubashir.khandakar@example.com')
                && $mail->lead->uuid === $referenceUuid
                && $mail->lead->bkash_txid === 'BL79K2P09Q';
        });
    }

    /**
     * Scenario 4: Minor Applicant Youth Consent Enforcement (FORM-03, DEC-09)
     */
    public function test_scenario_4_minor_applicant_youth_consent_enforcement(): void
    {
        $identityDoc = UploadedFile::fake()->create('school_id.pdf', 250, 'application/pdf');
        $photoFile = UploadedFile::fake()->image('minor_photo.jpg', 600, 600);
        $consentFile = UploadedFile::fake()->create('parent_consent.pdf', 350, 'application/pdf');

        // 1. Attempt submission as minor (age 16) without consent file -> must fail validation
        $component = Livewire::test(PublicMembershipForm::class)
            ->set('first_name', 'Sadman')
            ->set('last_name', 'Sakib')
            ->set('american_space', 'emk_center')
            ->set('sex', 'male')
            ->set('date_of_birth', '2010-06-01') // Minor (age 16)
            ->set('address_house_no', 'House 12')
            ->set('address_area', 'Dhanmondi')
            ->set('address_zip_code', '1209')
            ->set('address_division', 'dhaka')
            ->set('address_district', 'dhaka')
            ->set('contact_number', '+8801711223344')
            ->set('email', 'sadman.sakib@example.com')
            ->set('occupation', 'student')
            ->set('institution_study', 'Dhaka College')
            ->set('highest_certification', 'ssc')
            ->set('area_of_subject', 'Science')
            ->set('identity_document_files', [$identityDoc])
            ->set('photo_file', $photoFile)
            ->set('parental_consent_file', null) // Missing consent!
            ->set('bkash_txid', 'MNRCSNT001')
            ->set('terms_agreed', true)
            ->call('submit');

        $component->assertHasErrors(['parental_consent_file']);
        $this->assertFalse($component->get('submitted'));

        // 2. Attach parental consent file -> submission must succeed
        $component->set('parental_consent_file', $consentFile)
            ->call('submit');

        $component->assertHasNoErrors();
        $this->assertTrue($component->get('submitted'));

        $referenceUuid = $component->get('reference_uuid');
        $lead = Lead::where('uuid', $referenceUuid)->firstOrFail();
        $this->assertTrue($lead->has_parental_consent);
        $this->assertLessThan(18, $lead->age_at_submission);

        // Assert parental consent attachment received youth_protection = true
        $consentAttachment = LeadAttachment::where('lead_id', $lead->id)
            ->where('document_type', 'parental_consent')
            ->firstOrFail();

        $this->assertTrue($consentAttachment->youth_protection);
    }

    /**
     * Scenario 5: Duplicate bKash Transaction ID Rejection (FORM-04, DEC-05)
     */
    public function test_scenario_5_duplicate_bkash_transaction_id_rejection(): void
    {
        $identityDoc = UploadedFile::fake()->create('nid.pdf', 300, 'application/pdf');
        $photoFile = UploadedFile::fake()->image('photo.jpg', 600, 600);

        // First application with BL79K2P09Q was ingested in Scenario 3 or seed
        // Submit fresh first application
        Livewire::test(PublicMembershipForm::class)
            ->set('first_name', 'First')
            ->set('last_name', 'Applicant')
            ->set('american_space', 'emk_center')
            ->set('sex', 'male')
            ->set('date_of_birth', '1995-05-15')
            ->set('address_house_no', 'House 1')
            ->set('address_area', 'Mirpur')
            ->set('address_zip_code', '1216')
            ->set('address_division', 'dhaka')
            ->set('address_district', 'dhaka')
            ->set('contact_number', '+8801711111111')
            ->set('email', 'first.txid@example.com')
            ->set('occupation', 'student')
            ->set('institution_study', 'BUET')
            ->set('highest_certification', 'undergraduate_degree')
            ->set('area_of_subject', 'Engineering')
            ->set('identity_document_files', [$identityDoc])
            ->set('photo_file', $photoFile)
            ->set('bkash_txid', 'TXIDDUP001')
            ->set('terms_agreed', true)
            ->call('submit')
            ->assertHasNoErrors();

        // Second application reusing identical bKash TxID TXIDDUP001 must be blocked
        $secondIdentity = UploadedFile::fake()->create('nid2.pdf', 300, 'application/pdf');
        $secondPhoto = UploadedFile::fake()->image('photo2.jpg', 600, 600);

        $component = Livewire::test(PublicMembershipForm::class)
            ->set('first_name', 'Second')
            ->set('last_name', 'Applicant')
            ->set('american_space', 'emk_center')
            ->set('sex', 'female')
            ->set('date_of_birth', '1998-08-20')
            ->set('address_house_no', 'House 2')
            ->set('address_area', 'Uttara')
            ->set('address_zip_code', '1230')
            ->set('address_division', 'dhaka')
            ->set('address_district', 'dhaka')
            ->set('contact_number', '+8801722222222')
            ->set('email', 'second.applicant@example.com')
            ->set('occupation', 'educator')
            ->set('institution_study', 'DU')
            ->set('highest_certification', 'undergraduate_degree')
            ->set('area_of_subject', 'Business')
            ->set('identity_document_files', [$secondIdentity])
            ->set('photo_file', $secondPhoto)
            ->set('bkash_txid', 'TXIDDUP001') // Duplicate bKash TxID!
            ->set('terms_agreed', true)
            ->call('submit');

        $component->assertHasErrors(['bkash_txid']);
        $this->assertFalse($component->get('submitted'));
    }

    /**
     * Scenario 6: Duplicate Email Prevention and Reapplication Policy (FORM-05, DEC-01, LEAD-07)
     */
    public function test_scenario_6_duplicate_email_prevention_and_reapplication_policy(): void
    {
        $identityDoc = UploadedFile::fake()->create('nid.pdf', 300, 'application/pdf');
        $photoFile = UploadedFile::fake()->image('photo.jpg', 600, 600);

        // 1. Submit application for applicant
        Livewire::test(PublicMembershipForm::class)
            ->set('first_name', 'Reapply')
            ->set('last_name', 'Candidate')
            ->set('american_space', 'emk_center')
            ->set('sex', 'female')
            ->set('date_of_birth', '1997-04-10')
            ->set('address_house_no', 'House 5')
            ->set('address_area', 'Gulshan')
            ->set('address_zip_code', '1212')
            ->set('address_division', 'dhaka')
            ->set('address_district', 'dhaka')
            ->set('contact_number', '+8801733333333')
            ->set('email', 'reapply.candidate@example.com')
            ->set('occupation', 'artist')
            ->set('institution_study', 'IUB')
            ->set('highest_certification', 'undergraduate_degree')
            ->set('area_of_subject', 'Media')
            ->set('identity_document_files', [$identityDoc])
            ->set('photo_file', $photoFile)
            ->set('bkash_txid', 'TXIDREAP01')
            ->set('terms_agreed', true)
            ->call('submit')
            ->assertHasNoErrors();

        $activeLead = Lead::where('email', 'reapply.candidate@example.com')->firstOrFail();
        $this->assertEquals('submitted', $activeLead->status);

        // 2. Submit second application with same email while active -> must be blocked
        $doc2 = UploadedFile::fake()->create('nid2.pdf', 300, 'application/pdf');
        $photo2 = UploadedFile::fake()->image('photo2.jpg', 600, 600);

        $component = Livewire::test(PublicMembershipForm::class)
            ->set('first_name', 'Reapply')
            ->set('last_name', 'Candidate')
            ->set('american_space', 'emk_center')
            ->set('sex', 'female')
            ->set('date_of_birth', '1997-04-10')
            ->set('address_house_no', 'House 5')
            ->set('address_area', 'Gulshan')
            ->set('address_zip_code', '1212')
            ->set('address_division', 'dhaka')
            ->set('address_district', 'dhaka')
            ->set('contact_number', '+8801733333333')
            ->set('email', 'reapply.candidate@example.com') // Duplicate email!
            ->set('occupation', 'artist')
            ->set('institution_study', 'IUB')
            ->set('highest_certification', 'undergraduate_degree')
            ->set('area_of_subject', 'Media')
            ->set('identity_document_files', [$doc2])
            ->set('photo_file', $photo2)
            ->set('bkash_txid', 'TXIDREAP02')
            ->set('terms_agreed', true)
            ->call('submit');

        $component->assertHasErrors(['email']);
        $this->assertFalse($component->get('submitted'));

        // 3. Mark existing lead as rejected (LEAD-07 reapplication policy)
        $activeLead->update(['status' => 'rejected']);

        // 4. Submit new application with same email and new valid bKash TxID -> must succeed!
        $doc3 = UploadedFile::fake()->create('nid3.pdf', 300, 'application/pdf');
        $photo3 = UploadedFile::fake()->image('photo3.jpg', 600, 600);

        $reapplyComponent = Livewire::test(PublicMembershipForm::class)
            ->set('first_name', 'Reapply')
            ->set('last_name', 'Candidate')
            ->set('american_space', 'emk_center')
            ->set('sex', 'female')
            ->set('date_of_birth', '1997-04-10')
            ->set('address_house_no', 'House 5')
            ->set('address_area', 'Gulshan')
            ->set('address_zip_code', '1212')
            ->set('address_division', 'dhaka')
            ->set('address_district', 'dhaka')
            ->set('contact_number', '+8801733333333')
            ->set('email', 'reapply.candidate@example.com') // Reapplying!
            ->set('occupation', 'artist')
            ->set('institution_study', 'IUB')
            ->set('highest_certification', 'undergraduate_degree')
            ->set('area_of_subject', 'Media')
            ->set('identity_document_files', [$doc3])
            ->set('photo_file', $photo3)
            ->set('bkash_txid', 'TXIDREAP03')
            ->set('terms_agreed', true)
            ->call('submit');

        $reapplyComponent->assertHasNoErrors();
        $this->assertTrue($reapplyComponent->get('submitted'));

        // Verify two leads now exist: one rejected, one newly submitted
        $leads = Lead::where('email', 'reapply.candidate@example.com')->get();
        $this->assertCount(2, $leads);
        $this->assertTrue($leads->contains('status', 'rejected'));
        $this->assertTrue($leads->contains('status', 'submitted'));
    }

    /**
     * Scenario 7: Staff Submissions Review & Tenancy Isolation (FORM-01, DEC-01)
     */
    public function test_scenario_7_staff_submissions_review_and_tenancy_isolation(): void
    {
        $identityDoc = UploadedFile::fake()->create('doc.pdf', 200, 'application/pdf');
        $photo = UploadedFile::fake()->image('photo.jpg', 500, 500);

        // 1. Submit application targeting Chattogram Corner (routes to staging pool: workspace_id = null per DEC-01)
        Livewire::test(PublicMembershipForm::class)
            ->set('first_name', 'Chittagong')
            ->set('last_name', 'Applicant')
            ->set('american_space', 'chattogram_corner')
            ->set('sex', 'male')
            ->set('date_of_birth', '1998-11-11')
            ->set('address_house_no', 'Station Road')
            ->set('address_area', 'GEC Circle')
            ->set('address_zip_code', '4000')
            ->set('address_division', 'chattogram')
            ->set('address_district', 'chattogram')
            ->set('contact_number', '+8801812345678')
            ->set('email', 'ctg.applicant@example.com')
            ->set('occupation', 'student')
            ->set('institution_study', 'CUET')
            ->set('highest_certification', 'undergraduate_degree')
            ->set('area_of_subject', 'Electrical')
            ->set('identity_document_files', [$identityDoc])
            ->set('photo_file', $photo)
            ->set('bkash_txid', 'TXIDCTG001')
            ->set('terms_agreed', true)
            ->call('submit')
            ->assertHasNoErrors();

        // 2. Dhaka staff accesses Submissions Viewer
        $component = Livewire::actingAs($this->dhakaStaff, 'web')
            ->test(SubmissionViewer::class, [
                'workspace' => 'ws-emk-dhaka',
                'form' => $this->defaultForm,
            ]);

        // Dhaka applications appear in Dhaka staff view
        // Chattogram application in staging pool must NOT appear in Dhaka staff view
        $component->assertDontSee('ctg.applicant@example.com');
        $component->assertDontSee('TXIDCTG001');

        // 3. Superadmin accesses Submissions Viewer
        $superadminComponent = Livewire::actingAs($this->superAdmin, 'web')
            ->test(SubmissionViewer::class, [
                'workspace' => 'ws-emk-dhaka',
                'form' => $this->defaultForm,
            ]);

        // Superadmin has visibility over all records
        $superadminComponent->assertSee('ctg.applicant@example.com');
        $superadminComponent->assertSee('TXIDCTG001');
    }

    /**
     * Scenario 8: Audited CSV Export (FORM-01, DEC-11)
     */
    public function test_scenario_8_audited_csv_export(): void
    {
        $initialAuditCount = AuditLog::where('event_type', 'form_submissions_exported')->count();

        // Staff exports submissions to CSV
        $response = $this->actingAs($this->dhakaStaff, 'web')
            ->get(route('crm.forms.submissions.export', [
                'workspace' => 'ws-emk-dhaka',
                'form' => $this->defaultForm->id,
            ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        // Asserts valid RFC 4180 CSV headers
        $this->assertStringContainsString('Submission UUID', $content);
        $this->assertStringContainsString('Submitted At', $content);
        $this->assertStringContainsString('Target Space', $content);
        $this->assertStringContainsString('Status', $content);
        $this->assertStringContainsString('First Name', $content);
        $this->assertStringContainsString('bKash TxID', $content);

        // Asserts audit_logs table contains form_submissions_exported event
        $afterAuditCount = AuditLog::where('event_type', 'form_submissions_exported')->count();
        $this->assertEquals($initialAuditCount + 1, $afterAuditCount);

        $latestLog = AuditLog::where('event_type', 'form_submissions_exported')->latest('id')->firstOrFail();
        $this->assertEquals('ws-emk-dhaka', $latestLog->workspace_id);
        $this->assertEquals($this->dhakaStaff->id, $latestLog->actor_id);
        $this->assertEquals(Form::class, $latestLog->auditable_type);
        $this->assertEquals($this->defaultForm->id, $latestLog->auditable_id);
        $this->assertArrayHasKey('exported_rows', $latestLog->new_values);
    }
}
