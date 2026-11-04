<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Services\MemberStatusService;
use App\Livewire\Members\MemberProfile;
use App\Livewire\Members\MemberStatusModal;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class MemberStatusManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $admin;
    private Member $member;
    private User $portalUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->staff = $this->makeUser('desk@emk.test', 'delegated_staff', 'web');
        $this->admin = $this->makeUser('gadmin@emk.test', 'global_admin', 'web');

        $term = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-emk-dhaka', 'code' => 'term_standard_1yr'],
            ['name' => 'Standard', 'duration_months' => 12, 'fee_bdt' => 1000.00, 'is_active' => true]
        );

        $this->portalUser = $this->makeUser('member201@example.com', null, 'member');
        $this->member = $this->makeMember($term, $this->portalUser);
    }

    private function makeUser(string $email, ?string $role, string $guard): User
    {
        $u = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('secret123'),
            'guard' => $guard,
            'is_active' => true,
        ]);
        if ($role) {
            $u->assignRole($role);
        }

        return $u;
    }

    private function makeMember(MembershipTerm $term, User $portalUser): Member
    {
        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'converted',
            'bkash_txid' => 'TXSTATUS01',
            'first_name' => 'Sara',
            'last_name' => 'Khan',
            'email' => 'member201@example.com',
            'contact_number' => '+8801711111111',
            'date_of_birth' => '1998-02-20',
            'age_at_submission' => 28,
            'sex' => 'female',
            'address_house_no' => '5',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Engineer',
            'institution_study' => 'BUET',
            'highest_certification' => 'Bachelor',
            'area_of_subject' => 'CSE',
            'has_parental_consent' => false,
            'terms_agreed' => true,
            'submitted_at' => Carbon::now(),
        ]);

        return Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => 'EMK-2026-00201',
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'user_id' => $portalUser->id,
            'membership_term_id' => $term->id,
            'status' => 'active',
            'first_name' => 'Sara',
            'last_name' => 'Khan',
            'email' => 'member201@example.com',
            'contact_number' => '+8801711111111',
            'date_of_birth' => '1998-02-20',
            'sex' => 'female',
            'address_house_no' => '5',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Engineer',
            'institution_study' => 'BUET',
            'highest_certification' => 'Bachelor',
            'area_of_subject' => 'CSE',
            'start_date' => Carbon::now()->subMonths(2)->toDateString(),
            'expiry_date' => Carbon::now()->addMonths(10)->toDateString(),
        ]);
    }

    public function test_regular_staff_cannot_restrict_or_revoke(): void
    {
        $svc = app(MemberStatusService::class);
        $reason = 'Valid rationale longer than twenty characters.';

        foreach (['restricted', 'revoked'] as $target) {
            try {
                $svc->transitionStatus($this->member, $target, 'DIR-1', $reason, $this->staff);
                $this->fail('Expected AuthorizationException for ' . $target);
            } catch (AuthorizationException) {
                $this->assertSame('active', $this->member->fresh()->status);
            }
        }
    }

    public function test_staff_cannot_open_modal_and_profile_hides_button(): void
    {
        $this->actingAs($this->staff, 'web');

        Livewire::test(MemberStatusModal::class, ['workspace' => 'ws-emk-dhaka', 'member' => $this->member])
            ->call('openModal')
            ->assertForbidden();

        Livewire::test(MemberProfile::class, ['workspace' => 'ws-emk-dhaka', 'member' => $this->member])
            ->assertDontSee('btn-change-status');
    }

    public function test_admin_sees_button_and_can_restrict_via_modal(): void
    {
        $this->actingAs($this->admin, 'web');

        Livewire::test(MemberProfile::class, ['workspace' => 'ws-emk-dhaka', 'member' => $this->member])
            ->assertSee('btn-change-status');

        Livewire::test(MemberStatusModal::class, ['workspace' => 'ws-emk-dhaka', 'member' => $this->member])
            ->call('openModal')
            ->set('targetStatus', 'restricted')
            ->set('directiveRef', 'DIR-2026-EMK-042')
            ->set('rationale', 'Repeated facility policy violations documented.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertDispatched('member-status-updated');

        $this->assertSame('restricted', $this->member->fresh()->status);
    }

    public function test_short_rationale_or_missing_directive_fails_validation(): void
    {
        $svc = app(MemberStatusService::class);

        try {
            $svc->transitionStatus($this->member, 'restricted', 'DIR-1', 'too short', $this->admin);
            $this->fail('Expected ValidationException for short rationale');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('rationale', $e->errors());
        }

        try {
            $svc->transitionStatus($this->member, 'restricted', '  ', 'A sufficiently long rationale text here.', $this->admin);
            $this->fail('Expected ValidationException for missing directive');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('directiveRef', $e->errors());
        }

        $this->actingAs($this->admin, 'web');
        Livewire::test(MemberStatusModal::class, ['workspace' => 'ws-emk-dhaka', 'member' => $this->member])
            ->call('openModal')
            ->set('directiveRef', '')
            ->set('rationale', 'short')
            ->call('submit')
            ->assertHasErrors(['directiveRef', 'rationale']);

        $this->assertSame('active', $this->member->fresh()->status);
    }

    public function test_revoking_disables_portal_login(): void
    {
        $this->portalUser->remember_token = 'tok123';
        $this->portalUser->save();

        app(MemberStatusService::class)->transitionStatus(
            $this->member,
            'revoked',
            'DIR-2026-EMK-099',
            'Membership terminated following conduct review board decision.',
            $this->admin
        );

        $this->assertSame('revoked', $this->member->fresh()->status);
        $portal = $this->portalUser->fresh();
        $this->assertFalse($portal->is_active);
        $this->assertNull($portal->remember_token);
    }

    public function test_invalid_transition_from_revoked_is_rejected(): void
    {
        $this->member->update(['status' => 'revoked']);

        $this->expectException(\LogicException::class);
        app(MemberStatusService::class)->transitionStatus(
            $this->member->fresh(),
            'active',
            'DIR-1',
            'Attempt to reinstate a revoked membership record.',
            $this->admin
        );
    }

    public function test_status_change_writes_audit_event_with_metadata(): void
    {
        app(MemberStatusService::class)->transitionStatus(
            $this->member,
            'restricted',
            'DIR-2026-EMK-042',
            'Repeated facility policy violations documented.',
            $this->admin
        );

        $log = AuditLog::where('event_type', 'member_status_changed')->first();
        $this->assertNotNull($log);
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame($this->member->id, (int) $log->auditable_id);
        $this->assertSame('active', $log->old_values['status']);
        $this->assertSame('restricted', $log->new_values['status']);
        $this->assertSame('DIR-2026-EMK-042', $log->new_values['directive_ref']);
        $this->assertSame('Repeated facility policy violations documented.', $log->new_values['rationale']);
    }
}
