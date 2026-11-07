<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Forms\Models\Form;
use Database\Seeders\DefaultMembershipFormSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicMembershipFormRenderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('spaces')->insert([
            ['id' => 1, 'code' => 'EMK-DHK', 'name' => 'EMK Center Dhaka', 'category' => 'Center', 'division' => 'Dhaka', 'district' => 'Dhaka', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('workspaces')->insert([
            ['id' => 'ws-emk-dhaka', 'space_id' => 1, 'name' => 'EMK Center Dhaka', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        // Seed the official 31-field default membership application form
        $this->seed(DefaultMembershipFormSeeder::class);
    }

    public function test_get_apply_redirects_to_membership_application_permalink(): void
    {
        $response = $this->get('/apply');

        $response->assertRedirect(route('public.forms.show', 'membership-application'));
    }

    public function test_get_apply_membership_application_returns_200_and_renders_public_layout(): void
    {
        $response = $this->get('/apply/membership-application');

        $response->assertStatus(200);

        // Official Branding Header
        $response->assertSee('American Spaces Bangladesh');
        $response->assertSee('EMK Center');
        $response->assertSee('U.S. Embassy Partner');
        $response->assertSee('TLS Secured Application');

        // Form Title & Overview
        $response->assertSee('Membership Form for American Spaces in Bangladesh');
        $response->assertSee('Requirements to fill-up the form');

        // Footer Contact Guidelines
        $response->assertSee('membership@emk.com.bd');
        $response->assertSee('SKS Tower');
    }

    public function test_public_form_is_completely_decoupled_from_admin_shell_and_navigation(): void
    {
        $response = $this->get('/apply/membership-application');

        $response->assertStatus(200);

        // Ensure zero administrative navigation or internal CRM links leak into the public perimeter
        $response->assertDontSee('Admin Console');
        $response->assertDontSee('livewire:admin.admin-shell');
        $response->assertDontSee('/crm/ws-emk-dhaka/leads');
        $response->assertDontSee('/crm/ws-emk-dhaka/staff');
        $response->assertDontSee('Role Delegation');
        $response->assertDontSee('Audit Trail');
    }

    public function test_renders_all_31_fields_matching_field_dictionary(): void
    {
        $response = $this->get('/apply/membership-application');

        $response->assertStatus(200);

        // Section 1: Personal Information (Fields 1-6)
        $response->assertSee('first_name');
        $response->assertSee('middle_name');
        $response->assertSee('last_name');
        $response->assertSee('american_space');
        $response->assertSee('sex');
        $response->assertSee('date_of_birth');

        // Section 2: Minor Applicant Consent (Field 7 per DEC-09)
        $response->assertSee('parental_consent_file');
        $response->assertSee('EMK-Parental-Consent-Form.pdf');
        $response->assertSee('Because you are under 18 years old, a signed Parental/Guardian Consent Form is required.');

        // Section 3: Address in Bangladesh (Fields 8-13)
        $response->assertSee('address_house_no');
        $response->assertSee('address_road_no');
        $response->assertSee('address_area');
        $response->assertSee('address_zip_code');
        $response->assertSee('address_division');
        $response->assertSee('address_district');

        // Section 4: Contact & Online Presence (Fields 14-16)
        $response->assertSee('contact_number');
        $response->assertSee('email');
        $response->assertSee('website');

        // Section 5: Education & Professional Background (Fields 17-27)
        $response->assertSee('occupation');
        $response->assertSee('occupation_other');
        $response->assertSee('institution_study');
        $response->assertSee('highest_certification');
        $response->assertSee('highest_certification_other');
        $response->assertSee('area_of_subject');
        $response->assertSee('has_us_experience');
        $response->assertSee('us_experience_location');
        $response->assertSee('is_american_alumni');
        $response->assertSee('american_institution_name');
        $response->assertSee('employer_name');
        $response->assertSee('job_title');
        $response->assertSee('work_phone');

        // Section 6: Identity Documents & Studio Photograph (Fields 28-29)
        $response->assertSee('identity_document_files');
        $response->assertSee('photo_file');

        // Section 7: Payment Verification & Terms Acceptance (Fields 30-31 per DEC-05)
        $response->assertSee('bkash_txid');
        $response->assertSee('terms_agreed');
    }

    public function test_renders_applicant_guidance_for_photos_documents_and_bkash(): void
    {
        $response = $this->get('/apply/membership-application');

        $response->assertStatus(200);

        // Studio Passport Photo Guidelines
        $response->assertSee('Plain solid white background');
        $response->assertSee('No glasses or sunglasses permitted');
        $response->assertSee('Both ears visible');
        $response->assertSee('Strictly no selfies');
        $response->assertSee('300x300');

        // Identity Document Guidelines
        $response->assertSee('National ID (NID)');
        $response->assertSee('Passport');
        $response->assertSee('Birth Certificate');
        $response->assertSee('Student ID');

        // bKash Payment Instructions per DEC-05
        $response->assertSee('bKash Transaction ID');
        $response->assertSee('017 7774 2140');
        $response->assertSee('10');

        // Terms and Conditions Rules for the Space
        $response->assertSee('General Rules for the Space');
        $response->assertSee('I accept the Terms and Conditions.');
    }

    public function test_unpublished_or_non_existent_form_returns_404(): void
    {
        // Non-existent form slug
        $missingResponse = $this->get('/apply/non-existent-membership-slug');
        $missingResponse->assertStatus(404);

        // Form exists but is unpublished
        Form::withoutGlobalScopes()->create([
            'workspace_id' => 'ws-emk-dhaka',
            'slug' => 'draft-hidden-form',
            'title' => 'Draft Form Not For Public',
            'schema' => ['fields' => []],
            'is_published' => false,
        ]);

        $draftResponse = $this->get('/apply/draft-hidden-form');
        $draftResponse->assertStatus(404);
    }

    public function test_consent_form_pdf_is_accessible_and_viewable_in_browser(): void
    {
        $response1 = $this->get('/forms/templates/V3-Parents-Consent-Form.pdf');
        $response1->assertStatus(200);
        $this->assertEquals('application/pdf', $response1->headers->get('content-type'));

        $response2 = $this->get('/wp-content/uploads/2025/12/V3-Parents-Consent-Form.pdf');
        $response2->assertStatus(200);
        $this->assertEquals('application/pdf', $response2->headers->get('content-type'));
    }
}
