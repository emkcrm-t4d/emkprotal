<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Forms\Models\Form;
use App\Domain\Forms\Models\FormSubmission;
use App\Domain\Leads\Models\Lead;
use App\Livewire\Forms\SubmissionViewer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SubmissionViewerTest extends TestCase
{
    use RefreshDatabase;

    protected User $dhakaStaff;
    protected User $staffWithoutPerm;
    protected User $sylhetStaff;
    protected User $superAdmin;
    protected Form $dhakaForm;
    protected FormSubmission $dhakaSubmission1;
    protected FormSubmission $dhakaSubmission2;
    protected FormSubmission $sylhetSubmission;

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
            'name' => 'Dhaka Delegated Staff',
            'email' => 'viewer.staff.dhaka@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->dhakaStaff->assignRole('delegated_staff');

        $this->staffWithoutPerm = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Unprivileged User',
            'email' => 'viewer.noperms@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        // No role assigned, lacks forms.view_entries

        $this->sylhetStaff = User::create([
            'workspace_id' => 'ws-corner-sylhet',
            'name' => 'Sylhet Delegated Staff',
            'email' => 'viewer.staff.sylhet@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->sylhetStaff->assignRole('delegated_staff');

        $this->superAdmin = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Super Administrator',
            'email' => 'viewer.superadmin@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('superadmin');

        // 5. Create forms and submissions
        $this->dhakaForm = Form::create([
            'workspace_id' => 'ws-emk-dhaka',
            'title' => 'Membership Application Form',
            'slug' => 'membership-application-test',
            'description' => 'Test form for submissions',
            'schema' => ['fields' => []],
            'settings' => [],
            'is_published' => true,
            'is_default' => true,
            'version' => '1.0',
            'created_by' => $this->dhakaStaff->id,
        ]);

        $this->dhakaSubmission1 = FormSubmission::create([
            'form_id' => $this->dhakaForm->id,
            'workspace_id' => 'ws-emk-dhaka',
            'payload' => [
                'first_name' => 'Rahim',
                'last_name' => 'Ahmed',
                'email' => 'rahim.ahmed@example.com',
                'contact_number' => '01711000001',
                'bkash_txid' => 'BKASH00001',
                'date_of_birth' => '1998-05-15',
                'sex' => 'male',
                'american_space' => 'emk_center',
                'address_division' => 'Dhaka',
                'address_district' => 'Dhaka',
                'occupation' => 'student',
                'institution_study' => 'Dhaka University',
                'terms_agreed' => true,
            ],
            'status' => 'submitted',
            'ip_address' => '127.0.0.1',
            'submitted_at' => now()->subDay(),
        ]);

        $this->dhakaSubmission2 = FormSubmission::create([
            'form_id' => $this->dhakaForm->id,
            'workspace_id' => 'ws-emk-dhaka',
            'payload' => [
                'first_name' => 'Fatima',
                'last_name' => 'Begum',
                'email' => 'fatima.begum@example.com',
                'contact_number' => '01711000002',
                'bkash_txid' => 'BKASH00002',
                'date_of_birth' => '2000-08-20',
                'sex' => 'female',
                'american_space' => 'emk_center',
                'address_division' => 'Dhaka',
                'address_district' => 'Dhaka',
                'occupation' => 'employed',
                'institution_study' => 'BRAC University',
                'terms_agreed' => true,
            ],
            'status' => 'processed',
            'ip_address' => '127.0.0.1',
            'submitted_at' => now(),
        ]);

        $this->sylhetSubmission = FormSubmission::create([
            'form_id' => $this->dhakaForm->id,
            'workspace_id' => 'ws-corner-sylhet',
            'payload' => [
                'first_name' => 'Karim',
                'last_name' => 'Chowdhury',
                'email' => 'karim.sylhet@example.com',
                'contact_number' => '01711000003',
                'bkash_txid' => 'BKASH00003',
                'date_of_birth' => '1995-03-10',
                'sex' => 'male',
                'american_space' => 'sylhet_corner',
                'address_division' => 'Sylhet',
                'address_district' => 'Sylhet',
                'occupation' => 'business',
                'institution_study' => 'SUST',
                'terms_agreed' => true,
            ],
            'status' => 'submitted',
            'ip_address' => '127.0.0.1',
            'submitted_at' => now(),
        ]);
    }

    public function test_staff_with_permission_can_access_submissions_viewer(): void
    {
        $response = $this->actingAs($this->dhakaStaff, 'web')
            ->get(route('crm.forms.submissions', [
                'workspace' => 'ws-emk-dhaka',
                'form' => $this->dhakaForm->id,
            ]));

        $response->assertStatus(200);
        $response->assertSee('Form Submissions');
        $response->assertSee('Membership Application Form');
    }

    public function test_staff_without_permission_receives_403_forbidden(): void
    {
        $response = $this->actingAs($this->staffWithoutPerm, 'web')
            ->get(route('crm.forms.submissions', [
                'workspace' => 'ws-emk-dhaka',
                'form' => $this->dhakaForm->id,
            ]));

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('crm.forms.submissions', [
            'workspace' => 'ws-emk-dhaka',
            'form' => $this->dhakaForm->id,
        ]));

        $response->assertRedirect('/login');
    }

    public function test_workspace_isolation_dhaka_staff_only_sees_dhaka_submissions(): void
    {
        $component = Livewire::actingAs($this->dhakaStaff, 'web')
            ->test(SubmissionViewer::class, [
                'workspace' => 'ws-emk-dhaka',
                'form' => $this->dhakaForm,
            ]);

        // Dhaka submissions should be visible
        $component->assertSee('rahim.ahmed@example.com');
        $component->assertSee('fatima.begum@example.com');
        $component->assertSee('BKASH00001');
        $component->assertSee('BKASH00002');

        // Sylhet submission must NOT be visible to Dhaka staff
        $component->assertDontSee('karim.sylhet@example.com');
        $component->assertDontSee('BKASH00003');
    }

    public function test_cross_workspace_route_access_is_blocked_with_403(): void
    {
        // Dhaka staff attempting to access Sylhet workspace submissions route
        $response = $this->actingAs($this->dhakaStaff, 'web')
            ->get(route('crm.forms.submissions', [
                'workspace' => 'ws-corner-sylhet',
                'form' => $this->dhakaForm->id,
            ]));

        $response->assertStatus(403);
    }

    public function test_superadmin_can_view_submissions_across_workspaces(): void
    {
        $component = Livewire::actingAs($this->superAdmin, 'web')
            ->test(SubmissionViewer::class, [
                'workspace' => 'ws-emk-dhaka',
                'form' => $this->dhakaForm,
            ]);

        // Superadmin sees all submissions across workspaces
        $component->assertSee('rahim.ahmed@example.com');
        $component->assertSee('fatima.begum@example.com');
        $component->assertSee('karim.sylhet@example.com');
        $component->assertSee('BKASH00003');
    }

    public function test_search_filter_filters_by_email_and_bkash_txid(): void
    {
        $component = Livewire::actingAs($this->dhakaStaff, 'web')
            ->test(SubmissionViewer::class, [
                'workspace' => 'ws-emk-dhaka',
                'form' => $this->dhakaForm,
            ]);

        // Filter by email
        $component->set('search', 'rahim.ahmed');
        $component->assertSee('rahim.ahmed@example.com');
        $component->assertDontSee('fatima.begum@example.com');

        // Filter by bKash TxID
        $component->set('search', 'BKASH00002');
        $component->assertSee('fatima.begum@example.com');
        $component->assertDontSee('rahim.ahmed@example.com');
    }

    public function test_status_filter_filters_by_submission_status(): void
    {
        $component = Livewire::actingAs($this->dhakaStaff, 'web')
            ->test(SubmissionViewer::class, [
                'workspace' => 'ws-emk-dhaka',
                'form' => $this->dhakaForm,
            ]);

        // Filter by processed
        $component->set('statusFilter', 'processed');
        $component->assertSee('fatima.begum@example.com');
        $component->assertDontSee('rahim.ahmed@example.com');

        // Filter by submitted
        $component->set('statusFilter', 'submitted');
        $component->assertSee('rahim.ahmed@example.com');
        $component->assertDontSee('fatima.begum@example.com');
    }

    public function test_modal_inspector_correctly_displays_full_submission_payload(): void
    {
        $component = Livewire::actingAs($this->dhakaStaff, 'web')
            ->test(SubmissionViewer::class, [
                'workspace' => 'ws-emk-dhaka',
                'form' => $this->dhakaForm,
            ]);

        $component->assertSet('showModal', false);

        // Call inspectSubmission
        $component->call('inspectSubmission', $this->dhakaSubmission1->id);

        $component->assertSet('showModal', true);
        $component->assertSet('selectedSubmissionId', $this->dhakaSubmission1->id);
        $component->assertSee('Application Inspector (All 31 Fields)');
        $component->assertSee('BKASH00001');
        $component->assertSee('Dhaka University');

        // Close modal
        $component->call('closeModal');
        $component->assertSet('showModal', false);
        $component->assertSet('selectedSubmissionId', null);
    }
}
