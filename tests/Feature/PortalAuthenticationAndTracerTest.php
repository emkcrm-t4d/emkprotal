<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Livewire\Portal\PortalLogin;
use App\Models\MemberUser;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpaceAndWorkspaceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class PortalAuthenticationAndTracerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SpaceAndWorkspaceSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function createMemberFixture(
        string $email,
        string $memberId,
        string $firstName = 'Test',
        string $lastName = 'Member',
        string $password = 'MemberPass123!',
        bool $isActive = true,
        bool $mustResetPassword = false
    ): array {
        $term = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-emk-dhaka', 'code' => 'REG-1YR'],
            [
                'name' => '1 Year Regular',
                'duration_months' => 12,
                'fee_bdt' => 500.00,
                'is_active' => true,
            ]
        );

        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'converted',
            'bkash_txid' => 'TXID' . strtoupper(Str::random(6)),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'contact_number' => '+8801700000000',
            'date_of_birth' => '1998-01-01',
            'age_at_submission' => 28,
            'sex' => 'female',
            'address_house_no' => '10',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Physics',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'submitted_at' => now(),
        ]);

        $memberUser = MemberUser::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => "{$firstName} {$lastName}",
            'email' => $email,
            'password' => Hash::make($password),
            'guard' => 'member',
            'is_active' => $isActive,
            'must_reset_password' => $mustResetPassword,
        ]);
        $memberUser->assignRole('member');

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => $memberId,
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'user_id' => $memberUser->id,
            'membership_term_id' => $term->id,
            'status' => $isActive ? 'active' : 'inactive',
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'contact_number' => '+8801700000000',
            'date_of_birth' => '1998-01-01',
            'sex' => 'female',
            'address_house_no' => '10',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Physics',
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
        ]);

        return [$memberUser, $member, $lead, $term];
    }

    public function test_login_screen_renders_successfully(): void
    {
        $response = $this->get(route('portal.login'));

        $response->assertOk();
        $response->assertSee('Member Portal Login');
        $response->assertSee('Email or Member ID');
    }

    public function test_authentication_succeeds_using_valid_email_and_password(): void
    {
        [$memberUser, $member] = $this->createMemberFixture(
            email: 'alice.member@emk.test',
            memberId: 'EMK-2026-00001',
            firstName: 'Alice',
            lastName: 'Member',
            password: 'MemberPass123!'
        );

        Livewire::test(PortalLogin::class)
            ->set('identifier', 'alice.member@emk.test')
            ->set('password', 'MemberPass123!')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('portal.profile'));

        $this->assertTrue(Auth::guard('member')->check());
        $this->assertSame($memberUser->id, Auth::guard('member')->id());

        // Assert audit log was recorded
        $this->assertDatabaseHas('audit_logs', [
            'event_type' => 'portal_member_login',
            'actor_id' => $memberUser->id,
            'actor_guard' => 'member',
        ]);
    }

    public function test_authentication_succeeds_using_valid_member_id_and_password(): void
    {
        [$memberUser, $member] = $this->createMemberFixture(
            email: 'bob.member@emk.test',
            memberId: 'EMK-2026-00002',
            firstName: 'Bob',
            lastName: 'Member',
            password: 'BobSecret123!'
        );

        Livewire::test(PortalLogin::class)
            ->set('identifier', 'EMK-2026-00002')
            ->set('password', 'BobSecret123!')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('portal.profile'));

        $this->assertTrue(Auth::guard('member')->check());
        $this->assertSame($memberUser->id, Auth::guard('member')->id());
    }

    public function test_authentication_fails_with_invalid_password_or_nonexistent_member_id(): void
    {
        [$memberUser, $member] = $this->createMemberFixture(
            email: 'charlie@emk.test',
            memberId: 'EMK-2026-00003',
            firstName: 'Charlie',
            lastName: 'Member',
            password: 'CorrectPassword123!'
        );

        // Wrong password with valid Member ID
        Livewire::test(PortalLogin::class)
            ->set('identifier', 'EMK-2026-00003')
            ->set('password', 'IncorrectPassword')
            ->call('login')
            ->assertHasErrors(['identifier']);

        $this->assertFalse(Auth::guard('member')->check());

        // Nonexistent Member ID
        Livewire::test(PortalLogin::class)
            ->set('identifier', 'EMK-2026-99999')
            ->set('password', 'AnyPassword123!')
            ->call('login')
            ->assertHasErrors(['identifier']);

        $this->assertFalse(Auth::guard('member')->check());
    }

    public function test_deactivated_member_user_cannot_log_in(): void
    {
        [$deactivatedUser, $member] = $this->createMemberFixture(
            email: 'deactivated@emk.test',
            memberId: 'EMK-2026-00004',
            firstName: 'Deactivated',
            lastName: 'Member',
            password: 'Pass123456!',
            isActive: false
        );

        // Attempt login with Member ID
        Livewire::test(PortalLogin::class)
            ->set('identifier', 'EMK-2026-00004')
            ->set('password', 'Pass123456!')
            ->call('login')
            ->assertHasErrors(['identifier'])
            ->assertSee('This membership portal account has been deactivated. Please contact the front desk.');

        $this->assertFalse(Auth::guard('member')->check());

        // Attempt login with email
        Livewire::test(PortalLogin::class)
            ->set('identifier', 'deactivated@emk.test')
            ->set('password', 'Pass123456!')
            ->call('login')
            ->assertHasErrors(['identifier'])
            ->assertSee('This membership portal account has been deactivated. Please contact the front desk.');

        $this->assertFalse(Auth::guard('member')->check());
    }

    public function test_staff_credentials_cannot_authenticate_under_member_portal(): void
    {
        $staffUser = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Staff Only',
            'email' => 'staff@emk.test',
            'password' => Hash::make('StaffPass123!'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);

        Livewire::test(PortalLogin::class)
            ->set('identifier', 'staff@emk.test')
            ->set('password', 'StaffPass123!')
            ->call('login')
            ->assertHasErrors(['identifier']);

        $this->assertFalse(Auth::guard('member')->check());
    }

    public function test_rate_limiting_throttles_excessive_attempts(): void
    {
        $component = Livewire::test(PortalLogin::class)
            ->set('identifier', 'bruteforce@emk.test')
            ->set('password', 'wrong-pass');

        // Fail 5 times
        for ($i = 0; $i < 5; $i++) {
            $component->call('login');
        }

        // 6th attempt must be throttled
        $component->call('login')
            ->assertHasErrors(['identifier']);
    }

    public function test_portal_logout_clears_session(): void
    {
        $memberUser = MemberUser::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Logout Tester',
            'email' => 'logout@emk.test',
            'password' => Hash::make('Pass123456!'),
            'guard' => 'member',
            'is_active' => true,
            'must_reset_password' => false,
        ]);

        $response = $this->actingAs($memberUser, 'member')
            ->post(route('portal.logout'));

        $response->assertRedirect(route('portal.login'));
        $this->assertFalse(Auth::guard('member')->check());
    }
}
