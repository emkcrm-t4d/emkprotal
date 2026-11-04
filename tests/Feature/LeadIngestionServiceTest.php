<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Forms\Services\LeadIngestionService;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Services\DocumentStorageService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DefaultMembershipFormSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LeadIngestionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LeadIngestionService $service;
    protected DocumentStorageService $documentStorage;
    protected Form $form;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->seed(DatabaseSeeder::class);
        $this->seed(DefaultMembershipFormSeeder::class);

        $this->service = app(LeadIngestionService::class);
        $this->documentStorage = app(DocumentStorageService::class);
        $this->form = Form::where('slug', 'membership-application')->firstOrFail();
    }

    public function test_adult_applicant_ingestion_creates_lead_in_dhaka_workspace(): void
    {
        $payload = $this->getValidPayload([
            'date_of_birth' => now()->subYears(22)->format('Y-m-d'),
            'american_space' => 'emk_center',
            'bkash_txid' => 'ADLTBKSH01',
            'email' => 'adult.applicant@example.com',
        ]);

        $uploadedFiles = [
            'identity_document_files' => [
                UploadedFile::fake()->create('nid.pdf', 300, 'application/pdf'),
            ],
            'photo_file' => UploadedFile::fake()->image('passport.jpg', 600, 600),
        ];

        $lead = $this->service->ingest($this->form, $payload, $uploadedFiles, '127.0.0.1', 'Mozilla/5.0');

        $this->assertInstanceOf(Lead::class, $lead);
        $this->assertEquals('ws-emk-dhaka', $lead->workspace_id);
        $this->assertEquals('EMK-DHK', $lead->target_space_code);
        $this->assertEquals('submitted', $lead->status);
        $this->assertEquals('ADLTBKSH01', $lead->bkash_txid);
        $this->assertEquals('adult.applicant@example.com', $lead->email);
        $this->assertEquals(22, $lead->age_at_submission);
        $this->assertFalse($lead->has_parental_consent);
        $this->assertTrue($lead->terms_agreed);

        // Verification initial states must be pending
        $this->assertEquals('pending', $lead->payment_verification_status);
        $this->assertEquals('pending', $lead->document_verification_status);
        $this->assertEquals('pending', $lead->photo_verification_status);

        // Assert 2 attachments created (identity + photo)
        $this->assertCount(2, $lead->attachments);
        foreach ($lead->attachments as $attachment) {
            $this->assertFalse($attachment->youth_protection);
            Storage::disk('private')->assertExists($attachment->storage_path);
        }

        // Assert FormSubmission updated or created with status 'processed'
        $this->assertDatabaseHas('form_submissions', [
            'lead_id' => $lead->id,
            'status' => 'processed',
        ]);
    }

    public function test_minor_applicant_requires_parental_consent_and_flags_youth_protection(): void
    {
        $payload = $this->getValidPayload([
            'date_of_birth' => now()->subYears(16)->format('Y-m-d'),
            'american_space' => 'emk_center',
            'bkash_txid' => 'MNRBKSH001',
            'email' => 'minor.applicant@example.com',
        ]);

        $filesWithoutConsent = [
            'identity_document_files' => [
                UploadedFile::fake()->create('student_id.pdf', 200, 'application/pdf'),
            ],
            'photo_file' => UploadedFile::fake()->image('minor_photo.jpg', 600, 600),
        ];

        // 1. Ingestion without parental consent must fail validation per DEC-09
        $this->expectException(ValidationException::class);
        $this->service->ingest($this->form, $payload, $filesWithoutConsent);
    }

    public function test_minor_applicant_with_parental_consent_persists_with_youth_protection(): void
    {
        $payload = $this->getValidPayload([
            'date_of_birth' => now()->subYears(16)->format('Y-m-d'),
            'american_space' => 'emk_center',
            'bkash_txid' => 'MNRBKSH002',
            'email' => 'minor.withconsent@example.com',
        ]);

        $filesWithConsent = [
            'identity_document_files' => [
                UploadedFile::fake()->create('birth_cert.pdf', 250, 'application/pdf'),
            ],
            'photo_file' => UploadedFile::fake()->image('minor_photo.jpg', 600, 600),
            'parental_consent_file' => UploadedFile::fake()->create('consent_form.pdf', 300, 'application/pdf'),
        ];

        $lead = $this->service->ingest($this->form, $payload, $filesWithConsent);

        $this->assertEquals(16, $lead->age_at_submission);
        $this->assertTrue($lead->has_parental_consent);

        // 3 attachments created
        $this->assertCount(3, $lead->attachments);

        // All attachments for applicant < 18 or type parental_consent must have youth_protection = true per DEC-09
        foreach ($lead->attachments as $attachment) {
            $this->assertTrue($attachment->youth_protection);
            Storage::disk('private')->assertExists($attachment->storage_path);
        }
    }

    public function test_non_emk_space_applicant_routes_to_superadmin_staging_pool(): void
    {
        $payload = $this->getValidPayload([
            'american_space' => 'chattogram_corner',
            'bkash_txid' => 'CTGBKSH001',
            'email' => 'ctg.applicant@example.com',
        ]);

        $uploadedFiles = [
            'identity_document_files' => [
                UploadedFile::fake()->create('nid.pdf', 200, 'application/pdf'),
            ],
            'photo_file' => UploadedFile::fake()->image('photo.jpg', 600, 600),
        ];

        $lead = $this->service->ingest($this->form, $payload, $uploadedFiles);

        // DEC-01 / GAP-P1-01: Other spaces route to null workspace staging pool with specific space code
        $this->assertNull($lead->workspace_id);
        $this->assertEquals('CR-CTG', $lead->target_space_code);

        // Verify pool attachment storage path
        foreach ($lead->attachments as $attachment) {
            $this->assertNull($attachment->workspace_id);
            $this->assertStringStartsWith('workspaces/pool/leads/', $attachment->storage_path);
            Storage::disk('private')->assertExists($attachment->storage_path);
        }
    }

    public function test_uploaded_files_stored_with_uuid_filenames_and_match_sha256_hash(): void
    {
        $originalPayload = 'Secret identity document raw content ' . Str::random(32);
        $file = UploadedFile::fake()->createWithContent('applicant_passport.pdf', $originalPayload);

        $payload = $this->getValidPayload([
            'bkash_txid' => 'HASHBKSH01',
            'email' => 'hash.test@example.com',
        ]);

        $uploadedFiles = [
            'identity_document_files' => [$file],
            'photo_file' => UploadedFile::fake()->image('passport.jpg', 400, 400),
        ];

        $lead = $this->service->ingest($this->form, $payload, $uploadedFiles);
        $idAttachment = $lead->attachments->firstWhere('document_type', 'identity_document');

        $this->assertNotNull($idAttachment);
        $this->assertEquals('applicant_passport.pdf', $idAttachment->original_filename);

        // Physical filename on disk must be UUID v4, not original filename
        $diskBasename = basename($idAttachment->storage_path);
        $this->assertStringNotContainsString('applicant_passport', $diskBasename);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}\.pdf$/i', $diskBasename);

        // Decrypted content must match original SHA-256 hash
        $decryptedContent = $this->documentStorage->get($idAttachment->storage_path);
        $this->assertEquals($originalPayload, $decryptedContent);
        $this->assertEquals(hash('sha256', $originalPayload), $idAttachment->file_hash);
    }

    public function test_rollback_on_failure_cleans_up_files_from_private_disk(): void
    {
        $payload = $this->getValidPayload([
            'bkash_txid' => 'FAILBKSH01',
            'email' => 'fail.test@example.com',
        ]);

        $uploadedFiles = [
            'identity_document_files' => [
                UploadedFile::fake()->create('nid.pdf', 300, 'application/pdf'),
            ],
            'photo_file' => UploadedFile::fake()->image('photo.jpg', 600, 600),
        ];

        // Cause a DB failure during transaction by hooking into Lead::creating
        Lead::creating(function (): void {
            throw new \RuntimeException('Simulated database write failure.');
        });

        try {
            $this->service->ingest($this->form, $payload, $uploadedFiles);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Simulated database write failure.', $e->getMessage());
        }

        // Verify that private storage disk has zero leaked files after rollback
        $allFiles = Storage::disk('private')->allFiles();
        $this->assertEmpty($allFiles, 'Files on private disk were not rolled back upon DB transaction failure.');
    }

    /**
     * Helper to assemble a complete 31-field valid payload.
     */
    protected function getValidPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Rahim',
            'middle_name' => 'Uddin',
            'last_name' => 'Chowdhury',
            'american_space' => 'emk_center',
            'sex' => 'male',
            'date_of_birth' => now()->subYears(22)->format('Y-m-d'),
            'address_house_no' => 'House 14',
            'address_road_no' => 'Road 5',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'contact_number' => '+8801712345678',
            'email' => 'applicant.' . Str::random(6) . '@example.com',
            'website' => 'https://example.com',
            'occupation' => 'student',
            'occupation_other' => null,
            'institution_study' => 'University of Dhaka',
            'highest_certification' => 'undergraduate_degree',
            'highest_certification_other' => null,
            'area_of_subject' => 'Computer Science',
            'has_us_experience' => '1',
            'us_experience_location' => 'Boston, MA',
            'is_american_alumni' => '1',
            'american_institution_name' => 'MIT',
            'employer_name' => 'Tech Corp',
            'job_title' => 'Intern',
            'work_phone' => '+8801812345678',
            'bkash_txid' => 'TX' . strtoupper(Str::random(8)),
            'terms_agreed' => true,
        ], $overrides);
    }
}
