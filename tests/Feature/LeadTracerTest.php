<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeadTracerTest extends TestCase
{
    use RefreshDatabase;

    protected User $dhakaStaff;
    protected User $sylhetStaff;
    protected Lead $dhakaLead;
    protected LeadAttachment $dhakaAttachment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        // Ensure Sylhet workspace exists for cross-workspace isolation tests
        $sylhetSpace = DB::table('spaces')->where('code', 'CR-SYL')->first();
        DB::table('workspaces')->updateOrInsert(
            ['id' => 'ws-corner-sylhet'],
            [
                'space_id' => $sylhetSpace->id,
                'name' => 'American Corner Sylhet',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // 1. Create Dhaka staff with leads.view permission (via delegated_staff role)
        $this->dhakaStaff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Dhaka Admissions Officer',
            'email' => 'officer.dhaka@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->dhakaStaff->assignRole('delegated_staff');

        // 2. Create Sylhet staff
        $this->sylhetStaff = User::create([
            'workspace_id' => 'ws-corner-sylhet',
            'name' => 'Sylhet Corner Officer',
            'email' => 'officer.sylhet@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->sylhetStaff->assignRole('delegated_staff');

        // 3. Create sample Lead in ws-emk-dhaka
        $leadUuid = Str::uuid()->toString();
        $this->dhakaLead = Lead::create([
            'uuid' => $leadUuid,
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'TRACER0001',
            'first_name' => 'Tanvir',
            'last_name' => 'Hossain',
            'email' => 'tanvir.tracer@example.com',
            'contact_number' => '+8801712345678',
            'date_of_birth' => '1998-06-15',
            'age_at_submission' => 28,
            'sex' => 'male',
            'address_house_no' => 'House 14',
            'address_road_no' => 'Road 5',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'University of Dhaka',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Economics',
            'has_us_experience' => false,
            'is_american_alumni' => false,
            'has_parental_consent' => false,
            'terms_agreed' => true,
            'payment_verification_status' => 'pending',
            'document_verification_status' => 'pending',
            'photo_verification_status' => 'pending',
            'submitted_at' => now(),
        ]);

        // 4. Create sample attachment
        $this->dhakaAttachment = LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $this->dhakaLead->id,
            'document_type' => 'identity_document',
            'storage_path' => 'workspaces/ws-emk-dhaka/leads/' . $leadUuid . '/nid.pdf',
            'original_filename' => 'national_id_scan.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 102400,
            'file_hash' => hash('sha256', 'sample-pdf-content'),
            'youth_protection' => false,
        ]);
    }

    public function test_authorized_staff_can_view_lead_directory(): void
    {
        $response = $this->actingAs($this->dhakaStaff, 'web')
            ->get(route('crm.leads', ['workspace' => 'ws-emk-dhaka']));

        $response->assertStatus(200);
        $response->assertSee('Inbound Leads');
        $response->assertSee('Tanvir Hossain');
        $response->assertSee('TRACER0001');
        $response->assertSee('tanvir.tracer@example.com');
    }

    public function test_staff_can_view_lead_profile_with_document_links(): void
    {
        $response = $this->actingAs($this->dhakaStaff, 'web')
            ->get(route('crm.leads.show', [
                'workspace' => 'ws-emk-dhaka',
                'lead' => $this->dhakaLead->id,
            ]));

        $response->assertStatus(200);
        $response->assertSee('Tanvir Hossain');
        $response->assertSee('TRACER0001');
        $response->assertSee('University of Dhaka');
        $response->assertSee('national_id_scan.pdf');

        // Document download link is rendered
        $downloadUrl = route('documents.download', [
            'workspace' => 'ws-emk-dhaka',
            'attachment' => $this->dhakaAttachment->id,
        ]);
        $response->assertSee($downloadUrl);
    }

    public function test_cross_workspace_route_access_is_blocked_with_403(): void
    {
        // 1. Sylhet staff attempting to view Dhaka leads directory
        $directoryResponse = $this->actingAs($this->sylhetStaff, 'web')
            ->get(route('crm.leads', ['workspace' => 'ws-emk-dhaka']));

        $directoryResponse->assertStatus(403);

        // 2. Sylhet staff attempting to view Dhaka lead profile
        $profileResponse = $this->actingAs($this->sylhetStaff, 'web')
            ->get(route('crm.leads.show', [
                'workspace' => 'ws-emk-dhaka',
                'lead' => $this->dhakaLead->id,
            ]));

        $profileResponse->assertStatus(403);
    }

    public function test_unauthenticated_requests_redirect_to_login(): void
    {
        // 1. Lead directory
        $responseDir = $this->get(route('crm.leads', ['workspace' => 'ws-emk-dhaka']));
        $responseDir->assertRedirect('/login');

        // 2. Lead profile
        $responseProf = $this->get(route('crm.leads.show', [
            'workspace' => 'ws-emk-dhaka',
            'lead' => $this->dhakaLead->id,
        ]));
        $responseProf->assertRedirect('/login');
    }
}
