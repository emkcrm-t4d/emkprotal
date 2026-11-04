<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Leads\Models\LeadVerification;
use App\Livewire\Leads\LeadProfile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LeadProfileTest extends TestCase
{
    use RefreshDatabase;

    protected User $staffWithEdit;
    protected User $staffViewOnly;
    protected Lead $fullLead;
    protected LeadAttachment $attachmentDoc;
    protected LeadAttachment $attachmentPhoto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        // Staff with leads.view AND leads.edit (delegated_staff role includes leads.edit per MODULE-REGISTRY)
        $this->staffWithEdit = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Editor Staff',
            'email' => 'editor.staff@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staffWithEdit->assignRole('delegated_staff');
        $this->staffWithEdit->givePermissionTo(['leads.view', 'leads.edit']);

        // Staff with ONLY leads.view (no leads.edit permission)
        $this->staffViewOnly = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'View Only Staff',
            'email' => 'viewonly.staff@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staffViewOnly->givePermissionTo('leads.view');

        // Create Lead with all 31 fields populated
        $leadUuid = Str::uuid()->toString();
        $this->fullLead = Lead::create([
            'uuid' => $leadUuid,
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'under_review',
            'bkash_txid' => 'TX31FULL01',
            'first_name' => 'Farhan',
            'middle_name' => 'Ahmed',
            'last_name' => 'Chowdhury',
            'email' => 'farhan.full31@example.com',
            'contact_number' => '+8801755555555',
            'date_of_birth' => '1999-04-12',
            'age_at_submission' => 27,
            'sex' => 'male',
            'address_house_no' => 'House 42B',
            'address_road_no' => 'Road 7A',
            'address_area' => 'Banani',
            'address_zip_code' => '1213',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'website' => 'https://farhanchowdhury.dev',
            'occupation' => 'student',
            'occupation_other' => null,
            'institution_study' => 'Islamic University of Technology',
            'highest_certification' => 'undergraduate_degree',
            'highest_certification_other' => null,
            'area_of_subject' => 'Software Engineering',
            'has_us_experience' => true,
            'us_experience_location' => 'Seattle, WA',
            'is_american_alumni' => true,
            'american_institution_name' => 'University of Washington',
            'employer_name' => 'Apex Innovations',
            'job_title' => 'Research Assistant',
            'work_phone' => '+8801766666666',
            'has_parental_consent' => false,
            'terms_agreed' => true,
            'payment_verification_status' => 'pending',
            'document_verification_status' => 'in_progress',
            'photo_verification_status' => 'pending',
            'submitted_at' => now()->subDays(2),
        ]);

        $this->attachmentDoc = LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $this->fullLead->id,
            'document_type' => 'identity_document',
            'storage_path' => 'workspaces/ws-emk-dhaka/leads/' . $leadUuid . '/passport.pdf',
            'original_filename' => 'farhan_passport_scan.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 204800,
            'file_hash' => hash('sha256', 'passport-doc-sample'),
            'youth_protection' => false,
        ]);

        $this->attachmentPhoto = LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $this->fullLead->id,
            'document_type' => 'studio_photo',
            'storage_path' => 'workspaces/ws-emk-dhaka/leads/' . $leadUuid . '/studio.jpg',
            'original_filename' => 'farhan_studio_photo.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 51200,
            'file_hash' => hash('sha256', 'studio-photo-sample'),
            'youth_protection' => false,
        ]);
    }

    public function test_all_31_fields_render_accurately_in_profile_inspector(): void
    {
        $response = $this->actingAs($this->staffWithEdit, 'web')
            ->get(route('crm.leads.show', [
                'workspace' => 'ws-emk-dhaka',
                'lead' => $this->fullLead->id,
            ]));

        $response->assertStatus(200);

        // Group 1: Demographics & Identity (Fields 1-6, 31)
        $response->assertSee('Farhan');
        $response->assertSee('Ahmed');
        $response->assertSee('Chowdhury');
        $response->assertSee('EMK-DHK');
        $response->assertSee('MALE');
        $response->assertSee('1999-04-12');
        $response->assertSee('27 years');
        $response->assertSee('Agreed (Yes)');

        // Group 2: Contact Information (Fields 14-16, 27)
        $response->assertSee('+8801755555555');
        $response->assertSee('farhan.full31@example.com');
        $response->assertSee('https://farhanchowdhury.dev');
        $response->assertSee('+8801766666666');

        // Group 3: Residential Address (Fields 8-13)
        $response->assertSee('House 42B');
        $response->assertSee('Road 7A');
        $response->assertSee('Banani');
        $response->assertSee('1213');
        $response->assertSee('dhaka'); // division / district

        // Group 4: Education & Profession (Fields 17-20, 25-26)
        $response->assertSee('student');
        $response->assertSee('Islamic University of Technology');
        $response->assertSee('UNDERGRADUATE DEGREE');
        $response->assertSee('Software Engineering');
        $response->assertSee('Apex Innovations');
        $response->assertSee('Research Assistant');

        // Group 5: U.S. Experience & Alumni (Fields 21-24)
        $response->assertSee('Seattle, WA');
        $response->assertSee('University of Washington');

        // Group 6: Payment & Minor Consent (Fields 7, 30)
        $response->assertSee('TX31FULL01');
        $response->assertSee('Not Required'); // adult consent

        // Attachments (Fields 28, 29)
        $response->assertSee('farhan_passport_scan.pdf');
        $response->assertSee('farhan_studio_photo.jpg');
    }

    public function test_reapplication_history_banner_appears_when_prior_rejected_lead_exists(): void
    {
        $reapplicantEmail = 'reapplicant.test@example.com';

        // 1. Prior rejected application
        $priorLead = Lead::create([
            'uuid' => Str::uuid()->toString(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'rejected',
            'bkash_txid' => 'PRIORREJ01',
            'first_name' => 'Sabrina',
            'last_name' => 'Akter',
            'email' => $reapplicantEmail,
            'contact_number' => '+8801744444444',
            'date_of_birth' => '2001-09-09',
            'age_at_submission' => 25,
            'sex' => 'female',
            'address_house_no' => 'House 12',
            'address_area' => 'Mirpur',
            'address_zip_code' => '1216',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'BUET',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'EEE',
            'submitted_at' => now()->subDays(20),
            'created_at' => now()->subDays(20),
        ]);

        LeadVerification::create([
            'lead_id' => $priorLead->id,
            'stream' => 'document',
            'status' => 'rejected',
            'verified_by_user_id' => $this->staffWithEdit->id,
            'verified_at' => now()->subDays(19),
            'evidence_payload' => ['error' => 'Unreadable scan'],
            'rejection_reason_code' => 'INVALID_NID_SCAN',
            'notes' => 'National ID document was blurry and unreadable.',
        ]);

        // 2. Fresh reapplication by same applicant
        $currentLead = Lead::create([
            'uuid' => Str::uuid()->toString(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'FRESHAPP02',
            'first_name' => 'Sabrina',
            'last_name' => 'Akter',
            'email' => $reapplicantEmail,
            'contact_number' => '+8801744444444',
            'date_of_birth' => '2001-09-09',
            'age_at_submission' => 25,
            'sex' => 'female',
            'address_house_no' => 'House 12',
            'address_area' => 'Mirpur',
            'address_zip_code' => '1216',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'BUET',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'EEE',
            'submitted_at' => now(),
            'created_at' => now(),
        ]);

        // Inspect fresh lead profile
        $response = $this->actingAs($this->staffWithEdit, 'web')
            ->get(route('crm.leads.show', [
                'workspace' => 'ws-emk-dhaka',
                'lead' => $currentLead->id,
            ]));

        $response->assertStatus(200);

        // Assert reapplication banner and rejection details are prominent per LEAD-07
        $response->assertSee('Applicant Reapplication Detected');
        $response->assertSee('INVALID_NID_SCAN');
        $response->assertSee('PRIORREJ01');
    }

    public function test_staff_with_leads_edit_can_update_contact_and_address_fields(): void
    {
        $component = Livewire::actingAs($this->staffWithEdit, 'web')
            ->test(LeadProfile::class, [
                'workspace' => 'ws-emk-dhaka',
                'lead' => $this->fullLead,
            ]);

        // Toggle edit mode and provide updated contact/address information
        $component->call('toggleEditMode');
        $component->assertSet('editMode', true);

        $component->set('contact_number', '+8801899999999');
        $component->set('address_house_no', 'Apartment 9C, Road 11');
        $component->set('address_area', 'Dhanmondi R/A');
        $component->set('address_zip_code', '1209');
        $component->set('address_division', 'dhaka');
        $component->set('address_district', 'Dhaka');
        $component->set('occupation', 'educator');
        $component->set('institution_study', 'University of Oxford');

        $component->call('saveContactDetails');

        $component->assertHasNoErrors();
        $component->assertSet('editMode', false);

        // Verify database persistence
        $this->assertDatabaseHas('leads', [
            'id' => $this->fullLead->id,
            'contact_number' => '+8801899999999',
            'address_house_no' => 'Apartment 9C, Road 11',
            'address_area' => 'Dhanmondi R/A',
            'institution_study' => 'University of Oxford',
        ]);

        // Verify immutable audit log recording
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->staffWithEdit->id,
            'event_type' => 'lead_contact_updated',
            'auditable_type' => Lead::class,
            'auditable_id' => $this->fullLead->id,
        ]);
    }

    public function test_unauthorized_staff_cannot_edit_lead_details(): void
    {
        $component = Livewire::actingAs($this->staffViewOnly, 'web')
            ->test(LeadProfile::class, [
                'workspace' => 'ws-emk-dhaka',
                'lead' => $this->fullLead,
            ]);

        // View only staff calling saveContactDetails must be rejected with 403
        $component->call('saveContactDetails');
        $component->assertStatus(403);
    }

    public function test_core_identity_fields_cannot_be_modified_via_save_contact_details(): void
    {
        $originalFirstName = $this->fullLead->first_name;
        $originalEmail = $this->fullLead->email;
        $originalDob = $this->fullLead->date_of_birth->format('Y-m-d');
        $originalTxid = $this->fullLead->bkash_txid;

        $component = Livewire::actingAs($this->staffWithEdit, 'web')
            ->test(LeadProfile::class, [
                'workspace' => 'ws-emk-dhaka',
                'lead' => $this->fullLead,
            ]);

        $component->call('toggleEditMode');

        // Update contact number (valid action)
        $component->set('contact_number', '+8801799887766');
        $component->call('saveContactDetails');
        $component->assertHasNoErrors();

        $freshLead = $this->fullLead->fresh();

        // Core identity attributes must remain strictly immutable per T-06-02
        $this->assertEquals($originalFirstName, $freshLead->first_name);
        $this->assertEquals($originalEmail, $freshLead->email);
        $this->assertEquals($originalDob, $freshLead->date_of_birth->format('Y-m-d'));
        $this->assertEquals($originalTxid, $freshLead->bkash_txid);
        $this->assertEquals('+8801799887766', $freshLead->contact_number);
    }
}
