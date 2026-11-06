<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Mail\Mailables\TemplatedLifecycleMail;
use App\Domain\Mail\Models\EmailMessage;
use App\Domain\Mail\Models\EmailTemplate;
use App\Domain\Mail\Services\SmtpTransportManager;
use App\Domain\Workspaces\Models\Workspace;
use App\Jobs\DispatchOfficialEmailJob;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\StaffLogin;
use App\Livewire\Forms\PublicMembershipForm;
use App\Livewire\Portal\PortalLogin;
use App\Models\User;
use Database\Seeders\EmailTemplateSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpaceAndWorkspaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class EmailTemplatesAndForgotPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            SpaceAndWorkspaceSeeder::class,
            RolesAndPermissionsSeeder::class,
            EmailTemplateSeeder::class,
            \Database\Seeders\DefaultMembershipFormSeeder::class,
        ]);
    }

    public function test_all_canonical_email_templates_are_seeded_and_active(): void
    {
        $templates = EmailTemplate::withoutGlobalScopes()
            ->where('workspace_id', 'ws-emk-dhaka')
            ->get();

        $this->assertCount(11, $templates);

        foreach ($templates as $template) {
            $this->assertTrue($template->is_active, "Template '{$template->template_key}' should be active.");
            $this->assertNotEmpty($template->name);
            $this->assertNotEmpty($template->subject_en);
            $this->assertNotEmpty($template->body_en);
            $this->assertNotEmpty($template->subject_bn);
            $this->assertNotEmpty($template->body_bn);
        }
    }

    public function test_staff_login_forgot_password_generates_token_and_dispatches_password_reset_email(): void
    {
        Bus::fake([DispatchOfficialEmailJob::class]);

        $admin = User::where('email', 'admin@emk.test')->firstOrFail();

        Livewire::test(StaffLogin::class)
            ->call('openForgotPasswordModal')
            ->assertSet('showForgotPasswordModal', true)
            ->set('forgotEmail', 'admin@emk.test')
            ->call('sendPasswordResetLink')
            ->assertSet('forgotSuccessMessage', 'If your email is registered, we have dispatched a secure password reset link to your inbox.');

        $tokenRecord = DB::table('password_reset_tokens')->where('email', 'admin@emk.test')->first();
        $this->assertNotNull($tokenRecord);

        // Verify queued message was created in email_messages
        $queuedMessage = EmailMessage::withoutGlobalScopes()
            ->where('to_email', 'admin@emk.test')
            ->where('template_key', EmailTemplate::MILESTONE_PASSWORD_RESET)
            ->first();

        $this->assertNotNull($queuedMessage);
        $this->assertSame('queued', $queuedMessage->status);
        $this->assertStringContainsString('Reset Your', $queuedMessage->subject);
    }

    public function test_member_portal_login_forgot_password_modal_dispatches_password_reset_email(): void
    {
        Bus::fake([DispatchOfficialEmailJob::class]);

        Livewire::test(PortalLogin::class)
            ->call('openForgotPasswordModal')
            ->assertSet('showForgotPasswordModal', true)
            ->set('forgotIdentifier', 'member@emk.test')
            ->call('sendPasswordResetLink')
            ->assertSet('forgotSuccessMessage', 'If an account matches this detail, we have dispatched a secure password reset link to the registered email address.');

        $tokenRecord = DB::table('password_reset_tokens')->where('email', 'member@emk.test')->first();
        $this->assertNotNull($tokenRecord);
    }

    public function test_password_reset_component_successfully_resets_user_password(): void
    {
        $rawToken = 'test-token-1234567890';
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => 'admin@emk.test'],
            [
                'token' => Hash::make($rawToken),
                'created_at' => now(),
            ]
        );

        Livewire::test(ResetPassword::class, ['token' => $rawToken])
            ->set('email', 'admin@emk.test')
            ->set('password', 'NewSecurePass@2026')
            ->set('password_confirmation', 'NewSecurePass@2026')
            ->call('resetPassword')
            ->assertSet('isSuccess', true)
            ->assertHasNoErrors();

        $admin = User::where('email', 'admin@emk.test')->firstOrFail();
        $this->assertTrue(Hash::check('NewSecurePass@2026', $admin->password));
        $this->assertFalse((bool) $admin->must_reset_password);

        // Token should be consumed
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'admin@emk.test']);
    }

    public function test_new_application_submission_dispatches_admin_notification_email(): void
    {
        Bus::fake([DispatchOfficialEmailJob::class]);

        $identityFile = \Illuminate\Http\UploadedFile::fake()->create('nid.pdf', 300, 'application/pdf');
        $photoFile = \Illuminate\Http\UploadedFile::fake()->image('passport.jpg', 600, 600);

        Livewire::test(PublicMembershipForm::class)
            ->set('first_name', 'Zubair')
            ->set('last_name', 'Hossain')
            ->set('email', 'zubair.test@example.com')
            ->set('contact_number', '+8801700998877')
            ->set('american_space', 'emk_center')
            ->set('date_of_birth', '2000-01-01')
            ->set('sex', 'male')
            ->set('address_house_no', 'House 12')
            ->set('address_road_no', 'Road 3')
            ->set('address_area', 'Dhanmondi')
            ->set('address_zip_code', '1209')
            ->set('address_division', 'dhaka')
            ->set('address_district', 'dhaka')
            ->set('occupation', 'student')
            ->set('institution_study', 'University of Dhaka')
            ->set('highest_certification', 'undergraduate_degree')
            ->set('area_of_subject', 'Computer Science')
            ->set('has_us_experience', '0')
            ->set('is_american_alumni', '0')
            ->set('bkash_txid', 'TXNEW99881')
            ->set('identity_document_files', [$identityFile])
            ->set('photo_file', $photoFile)
            ->set('terms_agreed', true)
            ->call('submit')
            ->assertHasNoErrors();

        // Verify admin notification was created in email_messages
        $adminAlert = EmailMessage::withoutGlobalScopes()
            ->where('template_key', EmailTemplate::MILESTONE_ADMIN_NEW_USER)
            ->first();

        $this->assertNotNull($adminAlert);
        $this->assertSame('queued', $adminAlert->status);
        $this->assertStringContainsString('Zubair Hossain', $adminAlert->subject);
    }

    public function test_templated_lifecycle_mail_renders_light_mode_layout_without_black_background(): void
    {
        $mail = new TemplatedLifecycleMail(
            subjectLine: 'Application Received Confirmation',
            htmlContent: '<p>Dear Applicant, your form has been accepted.</p>'
        );

        $content = $mail->content();
        $renderedHtml = $content->htmlString;

        // Verify background is light and does NOT contain dark blues or black
        $this->assertStringContainsString('background-color: #f1f5f9;', $renderedHtml);
        $this->assertStringContainsString('background: #ffffff;', $renderedHtml);
        $this->assertStringContainsString('color: #1e293b;', $renderedHtml);
        $this->assertStringNotContainsString('#0b1120', $renderedHtml);
        $this->assertStringNotContainsString('#1e293b; border: 1px solid rgba(255,255,255,0.12);', $renderedHtml);
    }
}
