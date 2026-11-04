<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Forms\Mail\ApplicationConfirmationMail;
use App\Domain\Forms\Models\Form;
use App\Domain\Leads\Models\Lead;
use App\Livewire\Forms\PublicMembershipForm;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DefaultMembershipFormSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicationSubmissionNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected Form $form;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->seed(DatabaseSeeder::class);
        $this->seed(DefaultMembershipFormSeeder::class);

        $this->form = Form::where('slug', 'membership-application')->firstOrFail();
    }

    public function test_submitting_public_membership_form_dispatches_confirmation_email_and_transitions_ui(): void
    {
        Mail::fake();

        $identityFile = UploadedFile::fake()->create('nid.pdf', 300, 'application/pdf');
        $photoFile = UploadedFile::fake()->image('passport.jpg', 600, 600);

        $component = Livewire::test(PublicMembershipForm::class)
            ->set('first_name', 'Tanvir')
            ->set('middle_name', 'Hasan')
            ->set('last_name', 'Ahmed')
            ->set('american_space', 'emk_center')
            ->set('sex', 'male')
            ->set('date_of_birth', '1998-05-15')
            ->set('address_house_no', 'House 42')
            ->set('address_road_no', 'Road 7')
            ->set('address_area', 'Dhanmondi')
            ->set('address_zip_code', '1209')
            ->set('address_division', 'dhaka')
            ->set('address_district', 'dhaka')
            ->set('contact_number', '+8801712345678')
            ->set('email', 'tanvir.ahmed@example.com')
            ->set('website', 'https://tanvir.example.com')
            ->set('occupation', 'student')
            ->set('institution_study', 'University of Dhaka')
            ->set('highest_certification', 'undergraduate_degree')
            ->set('area_of_subject', 'Software Engineering')
            ->set('has_us_experience', '1')
            ->set('us_experience_location', 'New York, NY')
            ->set('is_american_alumni', '0')
            ->set('employer_name', 'Tech Lab')
            ->set('job_title', 'Developer')
            ->set('work_phone', '+8801812345678')
            ->set('identity_document_files', [$identityFile])
            ->set('photo_file', $photoFile)
            ->set('bkash_txid', 'BKSHNOTIF1')
            ->set('terms_agreed', true)
            ->call('submit');

        $component->assertHasNoErrors();
        $this->assertTrue($component->get('submitted'));
        $this->assertTrue($component->get('is_submitted'));

        $referenceUuid = $component->get('reference_uuid');
        $this->assertNotNull($referenceUuid);

        // Verify UI rendered emerald confirmation receipt
        $component->assertSee('Application Submitted Successfully!');
        $component->assertSee($referenceUuid);
        $component->assertSee('BKSHNOTIF1');
        $component->assertSee('Tanvir Ahmed');
        $component->assertSee('Our verification team will review your payment, documents, and photo within 2-3 business days.');

        // Verify ApplicationConfirmationMail was queued to applicant email
        Mail::assertQueued(ApplicationConfirmationMail::class, function (ApplicationConfirmationMail $mail) use ($referenceUuid) {
            return $mail->hasTo('tanvir.ahmed@example.com')
                && $mail->lead->uuid === $referenceUuid
                && $mail->lead->bkash_txid === 'BKSHNOTIF1';
        });
    }

    public function test_confirmation_email_content_contains_applicant_details_and_review_steps(): void
    {
        $identityFile = UploadedFile::fake()->create('nid.pdf', 300, 'application/pdf');
        $photoFile = UploadedFile::fake()->image('passport.jpg', 600, 600);

        Livewire::test(PublicMembershipForm::class)
            ->set('first_name', 'Fatima')
            ->set('middle_name', null)
            ->set('last_name', 'Zahra')
            ->set('american_space', 'emk_center')
            ->set('sex', 'female')
            ->set('date_of_birth', '2001-08-20')
            ->set('address_house_no', 'Flat 4B')
            ->set('address_area', 'Banani')
            ->set('address_zip_code', '1213')
            ->set('address_division', 'dhaka')
            ->set('address_district', 'dhaka')
            ->set('contact_number', '+8801912345678')
            ->set('email', 'fatima.zahra@example.com')
            ->set('occupation', 'artist')
            ->set('institution_study', 'Faculty of Fine Arts')
            ->set('highest_certification', 'undergraduate_degree')
            ->set('area_of_subject', 'Fine Arts')
            ->set('has_us_experience', '0')
            ->set('is_american_alumni', '0')
            ->set('identity_document_files', [$identityFile])
            ->set('photo_file', $photoFile)
            ->set('bkash_txid', 'FATIMABK01')
            ->set('terms_agreed', true)
            ->call('submit');

        $lead = Lead::where('bkash_txid', 'FATIMABK01')->firstOrFail();

        $mailable = new ApplicationConfirmationMail($lead);
        $rendered = $mailable->render();

        $this->assertStringContainsString('Fatima Zahra', $rendered);
        $this->assertStringContainsString($lead->uuid, $rendered);
        $this->assertStringContainsString('EMK-DHK', $rendered);
        $this->assertStringContainsString('FATIMABK01', $rendered);
        $this->assertStringContainsString('Payment Verification', $rendered);
        $this->assertStringContainsString('Document Compliance Check', $rendered);
        $this->assertStringContainsString('Studio Photo Check', $rendered);
    }
}
