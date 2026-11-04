<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FormSubmissionExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $dhakaStaff;
    protected User $staffWithoutPerm;
    protected User $sylhetStaff;
    protected User $superAdmin;
    protected Form $form;
    protected FormSubmission $dhakaSub1;
    protected FormSubmission $dhakaSub2;
    protected FormSubmission $sylhetSub;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed spaces
        DB::table('spaces')->insert([
            ['id' => 1, 'code' => 'EMK-DHK', 'name' => 'EMK Center Dhaka', 'category' => 'Center', 'division' => 'Dhaka', 'district' => 'Dhaka', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'CR-SYL', 'name' => 'American Corner Sylhet', 'category' => 'Corner', 'division' => 'Sylhet', 'district' => 'Sylhet', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 2. Seed workspaces
        DB::table('workspaces')->insert([
            ['id' => 'ws-emk-dhaka', 'space_id' => 1, 'name' => 'EMK Center Dhaka', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 'ws-corner-sylhet', 'space_id' => 2, 'name' => 'American Corner Sylhet', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // 3. Seed roles and permissions
        $this->seed(RolesAndPermissionsSeeder::class);

        // 4. Create users
        $this->dhakaStaff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Staff User',
            'email' => 'export.staff.dhaka@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->dhakaStaff->assignRole('delegated_staff');

        $this->staffWithoutPerm = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Unprivileged User',
            'email' => 'export.noperms@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);

        $this->sylhetStaff = User::create([
            'workspace_id' => 'ws-corner-sylhet',
            'name' => 'Sylhet Staff User',
            'email' => 'export.staff.sylhet@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->sylhetStaff->assignRole('delegated_staff');

        $this->superAdmin = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Super Administrator',
            'email' => 'export.superadmin@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('superadmin');

        // 5. Create test form
        $this->form = Form::create([
            'workspace_id' => 'ws-emk-dhaka',
            'title' => 'Official Membership Application',
            'slug' => 'official-membership-app',
            'schema' => ['fields' => []],
            'is_published' => true,
            'is_default' => true,
            'version' => '1.0',
        ]);

        // 6. Create submissions with normal and formula injection values
        $this->dhakaSub1 = FormSubmission::create([
            'form_id' => $this->form->id,
            'workspace_id' => 'ws-emk-dhaka',
            'payload' => [
                'first_name' => 'Anisul',
                'last_name' => 'Haque',
                'email' => 'anisul.haque@example.com',
                'contact_number' => '01811000001',
                'bkash_txid' => 'TXID000001',
                'date_of_birth' => '1996-04-12',
                'sex' => 'male',
                'american_space' => 'emk_center',
                'address_division' => 'Dhaka',
                'address_district' => 'Dhaka',
                'occupation' => 'developer',
                'institution_study' => 'BUET',
                'terms_agreed' => true,
            ],
            'status' => 'processed',
            'submitted_at' => now()->subHours(2),
        ]);

        // Submission with formula injection attempts
        $this->dhakaSub2 = FormSubmission::create([
            'form_id' => $this->form->id,
            'workspace_id' => 'ws-emk-dhaka',
            'payload' => [
                'first_name' => '=cmd|"/C calc"!A0',
                'last_name' => '+123456789',
                'email' => '-malicious@example.com',
                'contact_number' => '@SUM(1,2)',
                'bkash_txid' => 'TXID000002',
                'date_of_birth' => '1999-01-01',
                'sex' => 'other',
                'american_space' => 'emk_center',
                'address_division' => 'Dhaka',
                'address_district' => 'Dhaka',
                'occupation' => 'researcher',
                'institution_study' => 'NSU',
                'terms_agreed' => true,
            ],
            'status' => 'submitted',
            'submitted_at' => now()->subHour(),
        ]);

        // Submission belonging to Sylhet workspace
        $this->sylhetSub = FormSubmission::create([
            'form_id' => $this->form->id,
            'workspace_id' => 'ws-corner-sylhet',
            'payload' => [
                'first_name' => 'Sylhet',
                'last_name' => 'Applicant',
                'email' => 'sylhet.applicant@example.com',
                'contact_number' => '01711000099',
                'bkash_txid' => 'TXIDSYS001',
                'date_of_birth' => '1997-09-09',
                'sex' => 'female',
                'american_space' => 'sylhet_corner',
                'address_division' => 'Sylhet',
                'address_district' => 'Sylhet',
                'occupation' => 'teacher',
                'institution_study' => 'SUST',
                'terms_agreed' => true,
            ],
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function test_exporting_csv_streams_valid_rfc4180_response_with_headers(): void
    {
        $response = $this->actingAs($this->dhakaStaff, 'web')
            ->get(route('crm.forms.submissions.export', [
                'workspace' => 'ws-emk-dhaka',
                'form' => $this->form->id,
            ]));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename="form-submissions-official-membership-app-', $response->headers->get('Content-Disposition') ?? '');

        // Capture streamed content
        $content = $response->streamedContent();

        $this->assertStringContainsString('Submission UUID', $content);
        $this->assertStringContainsString('Submitted At', $content);
        $this->assertStringContainsString('Target Space', $content);
        $this->assertStringContainsString('First Name', $content);
        $this->assertStringContainsString('Last Name', $content);
        $this->assertStringContainsString('Email', $content);
        $this->assertStringContainsString('bKash TxID', $content);
        $this->assertStringContainsString('Anisul', $content);
        $this->assertStringContainsString('anisul.haque@example.com', $content);
    }

    public function test_non_permitted_user_receives_403_forbidden(): void
    {
        $response = $this->actingAs($this->staffWithoutPerm, 'web')
            ->get(route('crm.forms.submissions.export', [
                'workspace' => 'ws-emk-dhaka',
                'form' => $this->form->id,
            ]));

        $response->assertStatus(403);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->get(route('crm.forms.submissions.export', [
            'workspace' => 'ws-emk-dhaka',
            'form' => $this->form->id,
        ]));

        $response->assertRedirect('/login');
    }

    public function test_exported_data_is_strictly_scoped_to_current_workspace(): void
    {
        $response = $this->actingAs($this->dhakaStaff, 'web')
            ->get(route('crm.forms.submissions.export', [
                'workspace' => 'ws-emk-dhaka',
                'form' => $this->form->id,
            ]));

        $content = $response->streamedContent();

        // Dhaka records must be present
        $this->assertStringContainsString('anisul.haque@example.com', $content);
        $this->assertStringContainsString('TXID000001', $content);

        // Sylhet records must NOT be present in Dhaka export
        $this->assertStringNotContainsString('sylhet.applicant@example.com', $content);
        $this->assertStringNotContainsString('TXIDSYS001', $content);
    }

    public function test_csv_formula_injection_characters_are_sanitized(): void
    {
        $response = $this->actingAs($this->dhakaStaff, 'web')
            ->get(route('crm.forms.submissions.export', [
                'workspace' => 'ws-emk-dhaka',
                'form' => $this->form->id,
            ]));

        $content = $response->streamedContent();

        // Cells starting with =, +, -, @ must be prepended with a single quote (')
        $this->assertStringContainsString("'=cmd|", $content);
        $this->assertStringContainsString("'+123456789", $content);
        $this->assertStringContainsString("'-malicious@example.com", $content);
        $this->assertStringContainsString("'@SUM(1,2)", $content);
    }

    public function test_immutable_audit_log_written_on_export(): void
    {
        $beforeCount = AuditLog::where('event_type', 'form_submissions_exported')->count();

        $response = $this->actingAs($this->dhakaStaff, 'web')
            ->get(route('crm.forms.submissions.export', [
                'workspace' => 'ws-emk-dhaka',
                'form' => $this->form->id,
            ]));

        $response->assertStatus(200);

        $afterCount = AuditLog::where('event_type', 'form_submissions_exported')->count();
        $this->assertEquals($beforeCount + 1, $afterCount);

        $log = AuditLog::where('event_type', 'form_submissions_exported')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals('ws-emk-dhaka', $log->workspace_id);
        $this->assertEquals($this->dhakaStaff->id, $log->actor_id);
        $this->assertEquals('web', $log->actor_guard);
        $this->assertEquals(Form::class, $log->auditable_type);
        $this->assertEquals($this->form->id, $log->auditable_id);
        $this->assertEquals(2, $log->new_values['exported_rows']);
        $this->assertStringContainsString('official-membership-app', $log->new_values['filename']);
    }

    public function test_superadmin_can_export_cross_workspace(): void
    {
        $response = $this->actingAs($this->superAdmin, 'web')
            ->get(route('crm.forms.submissions.export', [
                'workspace' => 'ws-emk-dhaka',
                'form' => $this->form->id,
            ]));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        // Superadmin export without workspace filter includes all submissions
        $this->assertStringContainsString('anisul.haque@example.com', $content);
        $this->assertStringContainsString('sylhet.applicant@example.com', $content);
    }
}
