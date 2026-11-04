<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Livewire\Leads\LeadProfile;
use App\Livewire\Leads\Modals\ConvertLeadModal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LeadConversionModalTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $staff;
    protected MembershipTerm $term;
    protected Lead $approvedLead;
    protected Lead $pendingLead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Queue::fake();

        // 1. Workspace Manager with leads.convert & leads.view
        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Manager User',
            'email' => 'mgr.modal@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');
        $this->manager->givePermissionTo(['leads.view', 'leads.convert']);

        // 2. Delegated Staff with leads.view but NO leads.convert
        $this->staff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Staff User',
            'email' => 'staff.modal@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staff->assignRole('delegated_staff');
        $this->staff->givePermissionTo(['leads.view']);

        // 3. Active Membership Term
        $this->term = MembershipTerm::where('workspace_id', 'ws-emk-dhaka')
            ->where('code', 'std-1yr')
            ->firstOrFail();

        // 4. Approved Lead with all 3 streams verified
        $this->approvedLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'approved',
            'bkash_txid' => 'BKASHAPP01',
            'first_name' => 'Tanvir',
            'middle_name' => 'Hasan',
            'last_name' => 'Rifat',
            'email' => 'tanvir.approved@example.com',
            'contact_number' => '+8801711111111',
            'date_of_birth' => '1998-05-15',
            'age_at_submission' => 28,
            'sex' => 'male',
            'address_house_no' => 'House 10',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'entrepreneur',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Business Administration',
            'has_parental_consent' => false,
            'terms_agreed' => true,
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'submitted_at' => now()->subDay(),
        ]);

        // 5. Incomplete Lead with pending verification streams
        $this->pendingLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'under_review',
            'bkash_txid' => 'BKASHPEND01',
            'first_name' => 'Nusrat',
            'last_name' => 'Jahan',
            'email' => 'nusrat.pending@example.com',
            'contact_number' => '+8801722222222',
            'date_of_birth' => '2000-01-01',
            'age_at_submission' => 26,
            'sex' => 'female',
            'address_house_no' => 'House 5',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'BRAC University',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Economics',
            'has_parental_consent' => false,
            'terms_agreed' => true,
            'payment_verification_status' => 'pending',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'submitted_at' => now()->subDay(),
        ]);
    }

    public function test_convert_to_member_button_disabled_when_any_stream_unverified(): void
    {
        $response = $this->actingAs($this->manager, 'web')
            ->get(route('crm.leads.show', [
                'workspace' => 'ws-emk-dhaka',
                'lead' => $this->pendingLead->id,
            ]));

        $response->assertStatus(200);
        $response->assertSee('All 3 verifications (Payment, Document, Photo) and Manager Approval must be verified to convert.');
        $response->assertSee('id="btn-convert-lead-disabled-invariant"', false);
        $response->assertDontSee('id="btn-convert-lead"', false);
    }

    public function test_convert_to_member_button_enabled_when_all_3_streams_verified_and_lead_approved(): void
    {
        $response = $this->actingAs($this->manager, 'web')
            ->get(route('crm.leads.show', [
                'workspace' => 'ws-emk-dhaka',
                'lead' => $this->approvedLead->id,
            ]));

        $response->assertStatus(200);
        $response->assertSee('id="btn-convert-lead"', false);
        $response->assertDontSee('id="btn-convert-lead-disabled-invariant"', false);
        $response->assertDontSee('id="btn-convert-lead-disabled-auth"', false);
    }

    public function test_delegated_staff_cannot_trigger_conversion(): void
    {
        // 1. In UI, button is disabled with auth message
        $response = $this->actingAs($this->staff, 'web')
            ->get(route('crm.leads.show', [
                'workspace' => 'ws-emk-dhaka',
                'lead' => $this->approvedLead->id,
            ]));

        $response->assertStatus(200);
        $response->assertSee('Conversion requires Workspace Manager authority.');
        $response->assertSee('id="btn-convert-lead-disabled-auth"', false);

        // 2. Direct attempt to open conversion modal on LeadProfile throws 403
        $this->actingAs($this->staff, 'web');
        Livewire::test(LeadProfile::class, [
            'workspace' => 'ws-emk-dhaka',
            'lead' => $this->approvedLead,
        ])->call('openConversionModal')
          ->assertStatus(403);

        // 3. Direct attempt to open modal on ConvertLeadModal throws 403
        Livewire::test(ConvertLeadModal::class, [
            'workspace' => 'ws-emk-dhaka',
            'lead' => $this->approvedLead,
        ])->call('openModal')
          ->assertStatus(403);

        // 4. Direct attempt to execute conversion on ConvertLeadModal throws 403
        Livewire::test(ConvertLeadModal::class, [
            'workspace' => 'ws-emk-dhaka',
            'lead' => $this->approvedLead,
        ])->call('executeConversion')
          ->assertStatus(403);
    }

    public function test_workspace_manager_can_open_modal_select_term_and_execute_conversion(): void
    {
        $this->actingAs($this->manager, 'web');

        // Verify terms are loaded in modal
        Livewire::test(ConvertLeadModal::class, [
            'workspace' => 'ws-emk-dhaka',
            'lead' => $this->approvedLead,
        ])
            ->call('openModal')
            ->assertSet('showModal', true)
            ->assertSee('Standard 1-Year Membership')
            ->assertSee('BDT 500.00')
            ->set('selectedTermId', $this->term->id)
            ->call('executeConversion')
            ->assertSet('showModal', false)
            ->assertDispatched('conversionCompleted')
            ->assertDispatched('leadUpdated');

        // Check lead status transitioned to converted
        $this->approvedLead->refresh();
        $this->assertEquals('converted', $this->approvedLead->status);
        $this->assertNotNull($this->approvedLead->converted_member_id);

        // Check Member record was created
        $member = Member::where('lead_id', $this->approvedLead->id)->first();
        $this->assertNotNull($member);
        $this->assertStringStartsWith('EMK-', $member->member_id);
        $this->assertEquals('Tanvir', $member->first_name);
        $this->assertEquals($this->term->id, $member->membership_term_id);
    }

    public function test_profile_reflects_converted_state_with_member_id_link_after_conversion(): void
    {
        $this->actingAs($this->manager, 'web');

        // Execute conversion via modal
        Livewire::test(ConvertLeadModal::class, [
            'workspace' => 'ws-emk-dhaka',
            'lead' => $this->approvedLead,
        ])
            ->set('selectedTermId', $this->term->id)
            ->call('executeConversion');

        $this->approvedLead->refresh();
        $member = Member::where('lead_id', $this->approvedLead->id)->firstOrFail();

        // Check profile view now displays converted badge with member ID
        $response = $this->get(route('crm.leads.show', [
            'workspace' => 'ws-emk-dhaka',
            'lead' => $this->approvedLead->id,
        ]));

        $response->assertStatus(200);
        $response->assertSee('Converted to Member: ' . $member->member_id);
        $response->assertSee('id="badge-converted-member"', false);
        $response->assertDontSee('id="btn-convert-lead"', false);
    }

    public function test_modal_blocks_conversion_when_invariant_is_unmet(): void
    {
        $this->actingAs($this->manager, 'web');

        // Attempt direct executeConversion on pendingLead
        Livewire::test(ConvertLeadModal::class, [
            'workspace' => 'ws-emk-dhaka',
            'lead' => $this->pendingLead,
        ])
            ->set('selectedTermId', $this->term->id)
            ->call('executeConversion')
            ->assertHasErrors(['conversion'])
            ->assertNotDispatched('conversionCompleted');

        // Lead remains under_review
        $this->pendingLead->refresh();
        $this->assertEquals('under_review', $this->pendingLead->status);
        $this->assertNull($this->pendingLead->converted_member_id);
    }
}
