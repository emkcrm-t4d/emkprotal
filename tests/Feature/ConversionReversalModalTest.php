<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Services\LeadConversionService;
use App\Domain\Members\Models\Member;
use App\Livewire\Leads\LeadProfile;
use App\Livewire\Members\Modals\ConversionReversalModal;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ConversionReversalModalTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $staff;
    protected MembershipTerm $term;
    protected Lead $convertedLead;
    protected Member $member;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        Queue::fake();

        // 1. Workspace Manager with leads.view, leads.convert, leads.reverse_conversion
        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Manager User',
            'email' => 'mgr.revmodal@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');
        $this->manager->givePermissionTo(['leads.view', 'leads.convert', 'leads.reverse_conversion']);

        // 2. Delegated Staff with leads.view but NO leads.reverse_conversion
        $this->staff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Staff User',
            'email' => 'staff.revmodal@emk.test',
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

        // 4. Converted Lead and Member
        $this->convertedLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'approved',
            'bkash_txid' => 'BKASHREV01',
            'first_name' => 'Khadija',
            'last_name' => 'Akter',
            'email' => 'khadija.rev@example.com',
            'contact_number' => '+8801733333333',
            'date_of_birth' => '1999-04-10',
            'age_at_submission' => 27,
            'sex' => 'female',
            'address_house_no' => 'Flat 4B',
            'address_area' => 'Mirpur',
            'address_zip_code' => '1216',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Law',
            'has_parental_consent' => false,
            'terms_agreed' => true,
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'submitted_at' => now()->subDays(3),
        ]);

        $conversionService = app(LeadConversionService::class);
        $this->member = $conversionService->convert($this->convertedLead, $this->term->id, $this->manager);
        $this->convertedLead->refresh();
    }

    public function test_reverse_conversion_button_appears_for_workspace_manager_within_30_days(): void
    {
        $response = $this->actingAs($this->manager, 'web')
            ->get(route('crm.leads.show', [
                'workspace' => 'ws-emk-dhaka',
                'lead' => $this->convertedLead->id,
            ]));

        $response->assertStatus(200);
        $response->assertSee('id="badge-converted-member"', false);
        $response->assertSee('id="btn-reverse-conversion"', false);
        $response->assertSee('Reverse Conversion');
    }

    public function test_delegated_staff_cannot_see_or_trigger_reversal_modal(): void
    {
        // 1. Reversal button not displayed in UI for staff
        $response = $this->actingAs($this->staff, 'web')
            ->get(route('crm.leads.show', [
                'workspace' => 'ws-emk-dhaka',
                'lead' => $this->convertedLead->id,
            ]));

        $response->assertStatus(200);
        $response->assertDontSee('id="btn-reverse-conversion"', false);

        // 2. Direct call to openReversalModal on LeadProfile throws 403
        $this->actingAs($this->staff, 'web');
        Livewire::test(LeadProfile::class, [
            'workspace' => 'ws-emk-dhaka',
            'lead' => $this->convertedLead,
        ])->call('openReversalModal')
          ->assertStatus(403);

        // 3. Direct call to openModal on ConversionReversalModal throws 403
        Livewire::test(ConversionReversalModal::class, [
            'workspace' => 'ws-emk-dhaka',
            'memberId' => $this->member->id,
            'lead' => $this->convertedLead,
        ])->call('openModal')
          ->assertStatus(403);

        // 4. Direct call to executeReversal on ConversionReversalModal throws 403
        Livewire::test(ConversionReversalModal::class, [
            'workspace' => 'ws-emk-dhaka',
            'memberId' => $this->member->id,
            'lead' => $this->convertedLead,
        ])->call('executeReversal')
          ->assertStatus(403);
    }

    public function test_modal_rejects_justification_under_20_characters(): void
    {
        $this->actingAs($this->manager, 'web');

        Livewire::test(ConversionReversalModal::class, [
            'workspace' => 'ws-emk-dhaka',
            'memberId' => $this->member->id,
            'lead' => $this->convertedLead,
        ])
            ->call('openModal')
            ->set('justification', 'Short reason') // 12 chars < 20
            ->set('password', 'secret123')
            ->call('executeReversal')
            ->assertHasErrors(['justification' => 'min']);

        // Assert member and lead are untouched
        $this->member->refresh();
        $this->convertedLead->refresh();
        $this->assertSame('active', $this->member->status);
        $this->assertSame('converted', $this->convertedLead->status);
    }

    public function test_modal_rejects_incorrect_administrative_password(): void
    {
        $this->actingAs($this->manager, 'web');

        Livewire::test(ConversionReversalModal::class, [
            'workspace' => 'ws-emk-dhaka',
            'memberId' => $this->member->id,
            'lead' => $this->convertedLead,
        ])
            ->call('openModal')
            ->set('justification', 'Legitimate justification of more than twenty characters.')
            ->set('password', 'incorrect-password')
            ->call('executeReversal')
            ->assertHasErrors(['password'])
            ->assertSee('Administrative password confirmation failed.');

        // Assert member and lead are untouched
        $this->member->refresh();
        $this->convertedLead->refresh();
        $this->assertSame('active', $this->member->status);
        $this->assertSame('converted', $this->convertedLead->status);
    }

    public function test_valid_submission_executes_reversal_redirects_and_updates_lead_to_under_review(): void
    {
        $this->actingAs($this->manager, 'web');

        Livewire::test(ConversionReversalModal::class, [
            'workspace' => 'ws-emk-dhaka',
            'memberId' => $this->member->id,
            'lead' => $this->convertedLead,
        ])
            ->call('openModal')
            ->set('justification', 'Legitimate operational mistake in bKash transaction reconciliation.')
            ->set('password', 'secret123')
            ->call('executeReversal')
            ->assertHasNoErrors()
            ->assertDispatched('conversionReversed')
            ->assertRedirect(route('crm.leads.show', [
                'workspace' => 'ws-emk-dhaka',
                'lead' => $this->convertedLead->id,
            ]));

        // Assert member transitioned to archived_reversed
        $this->member->refresh();
        $this->assertSame('archived_reversed', $this->member->status);

        // Assert lead restored to under_review and converted_member_id is null
        $this->convertedLead->refresh();
        $this->assertSame('under_review', $this->convertedLead->status);
        $this->assertNull($this->convertedLead->converted_member_id);
    }
}
