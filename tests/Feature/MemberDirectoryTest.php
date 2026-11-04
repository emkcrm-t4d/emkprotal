<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Workspaces\Models\Workspace;
use App\Livewire\Members\MemberDirectory;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MemberDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;
    protected User $otherStaff;
    protected MembershipTerm $term;
    protected Member $memberActive;
    protected Member $memberInactive;
    protected Member $memberRestricted;
    protected Member $memberRevoked;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        // Ensure other workspace exists for cross-workspace tests
        $ctgSpace = \Illuminate\Support\Facades\DB::table('spaces')->where('code', 'CR-CTG')->first();
        Workspace::firstOrCreate(
            ['id' => 'ws-chattogram'],
            [
                'space_id' => $ctgSpace ? $ctgSpace->id : 1,
                'name' => 'Chattogram American Corner',
                'is_active' => true,
            ]
        );

        $this->staff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Admissions Officer',
            'email' => 'officer.dir@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staff->assignRole('delegated_staff');

        $this->otherStaff = User::create([
            'workspace_id' => 'ws-chattogram',
            'name' => 'Chattogram Officer',
            'email' => 'ctg.officer@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->otherStaff->assignRole('delegated_staff');

        $this->term = MembershipTerm::first();

        // Seed 4 members with distinct statuses and searchable fields
        $this->memberActive = $this->createMember([
            'member_id' => 'EMK-2026-00001',
            'first_name' => 'Rahim',
            'last_name' => 'Chowdhury',
            'email' => 'rahim.chowdhury@example.com',
            'contact_number' => '+8801711111111',
            'status' => 'active',
            'membership_term_id' => $this->term->id,
        ]);

        $this->memberInactive = $this->createMember([
            'member_id' => 'EMK-2026-00002',
            'first_name' => 'Fatima',
            'last_name' => 'Zahra',
            'email' => 'fatima.zahra@example.com',
            'contact_number' => '+8801722222222',
            'status' => 'inactive',
            'membership_term_id' => $this->term->id,
        ]);

        $this->memberRestricted = $this->createMember([
            'member_id' => 'EMK-2026-00003',
            'first_name' => 'Tariq',
            'last_name' => 'Hasan',
            'email' => 'tariq.hasan@example.com',
            'contact_number' => '+8801733333333',
            'status' => 'restricted',
            'membership_term_id' => $this->term->id,
        ]);

        $this->memberRevoked = $this->createMember([
            'member_id' => 'EMK-2026-00004',
            'first_name' => 'Kamal',
            'last_name' => 'Hossain',
            'email' => 'kamal.hossain@example.com',
            'contact_number' => '+8801744444444',
            'status' => 'revoked',
            'membership_term_id' => $this->term->id,
        ]);
    }

    protected function createMember(array $overrides = []): Member
    {
        $uuid = Str::uuid()->toString();
        $lead = Lead::create([
            'uuid' => Str::uuid()->toString(),
            'workspace_id' => $overrides['workspace_id'] ?? 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'converted',
            'bkash_txid' => 'TXID' . strtoupper(Str::random(6)),
            'first_name' => $overrides['first_name'] ?? 'Test',
            'last_name' => $overrides['last_name'] ?? 'User',
            'email' => $overrides['email'] ?? "test.{$uuid}@example.com",
            'contact_number' => $overrides['contact_number'] ?? '+8801700000000',
            'date_of_birth' => '1998-01-01',
            'age_at_submission' => 28,
            'sex' => 'male',
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

        $user = User::create([
            'name' => ($overrides['first_name'] ?? 'Test') . ' ' . ($overrides['last_name'] ?? 'User'),
            'email' => $overrides['email'] ?? "test.{$uuid}@example.com",
            'password' => bcrypt('secret123'),
            'workspace_id' => $overrides['workspace_id'] ?? 'ws-emk-dhaka',
            'guard' => 'member',
            'is_active' => true,
        ]);

        $termId = $overrides['membership_term_id'] ?? MembershipTerm::first()->id;

        return Member::create(array_merge([
            'uuid' => $uuid,
            'member_id' => 'EMK-2026-' . strtoupper(Str::random(5)),
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'membership_term_id' => $termId,
            'status' => 'active',
            'first_name' => 'Test',
            'middle_name' => null,
            'last_name' => 'User',
            'email' => "test.{$uuid}@example.com",
            'contact_number' => '+8801700000000',
            'date_of_birth' => '1998-01-01',
            'sex' => 'male',
            'address_house_no' => '10',
            'address_road_no' => 'Road 1',
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
        ], $overrides));
    }

    public function test_unauthenticated_requests_redirect_to_login(): void
    {
        $response = $this->get('/crm/ws-emk-dhaka/members');
        $response->assertRedirect('/login');
    }

    public function test_authorized_staff_can_view_member_directory_route(): void
    {
        $response = $this->actingAs($this->staff, 'web')
            ->get('/crm/ws-emk-dhaka/members');

        $response->assertStatus(200);
        $response->assertSeeLivewire(MemberDirectory::class);
        $response->assertSee('Rahim Chowdhury');
        $response->assertSee('EMK-2026-00001');
    }

    public function test_cross_workspace_staff_receives_403_tenancy_defense(): void
    {
        // Chattogram staff accessing Dhaka members
        $response = $this->actingAs($this->otherStaff, 'web')
            ->get('/crm/ws-emk-dhaka/members');

        $response->assertStatus(403);
    }

    public function test_search_filtering_by_member_id_name_email_and_contact(): void
    {
        $component = Livewire::actingAs($this->staff, 'web')
            ->test(MemberDirectory::class, ['workspace' => 'ws-emk-dhaka']);

        // Initially all 4 members visible
        $component->assertSee('Rahim Chowdhury');
        $component->assertSee('Fatima Zahra');
        $component->assertSee('Tariq Hasan');
        $component->assertSee('Kamal Hossain');

        // 1. Search by member ID
        $component->set('search', 'EMK-2026-00001');
        $component->assertSee('Rahim Chowdhury');
        $component->assertDontSee('Fatima Zahra');
        $component->assertDontSee('Tariq Hasan');
        $component->assertDontSee('Kamal Hossain');

        // 2. Search by name (Fatima)
        $component->set('search', 'Fatima');
        $component->assertSee('Fatima Zahra');
        $component->assertDontSee('Rahim Chowdhury');
        $component->assertDontSee('Tariq Hasan');
        $component->assertDontSee('Kamal Hossain');

        // 3. Search by email (tariq.hasan@example.com)
        $component->set('search', 'tariq.hasan@example.com');
        $component->assertSee('Tariq Hasan');
        $component->assertDontSee('Rahim Chowdhury');
        $component->assertDontSee('Fatima Zahra');
        $component->assertDontSee('Kamal Hossain');

        // 4. Search by contact number (+8801744444444)
        $component->set('search', '+8801744444444');
        $component->assertSee('Kamal Hossain');
        $component->assertDontSee('Rahim Chowdhury');
        $component->assertDontSee('Fatima Zahra');
        $component->assertDontSee('Tariq Hasan');
    }

    public function test_status_filtering_isolates_active_inactive_restricted_and_revoked(): void
    {
        $component = Livewire::actingAs($this->staff, 'web')
            ->test(MemberDirectory::class, ['workspace' => 'ws-emk-dhaka']);

        // Filter: active
        $component->set('statusFilter', 'active');
        $component->assertSee('Rahim Chowdhury');
        $component->assertDontSee('Fatima Zahra');
        $component->assertDontSee('Tariq Hasan');
        $component->assertDontSee('Kamal Hossain');

        // Filter: inactive
        $component->set('statusFilter', 'inactive');
        $component->assertSee('Fatima Zahra');
        $component->assertDontSee('Rahim Chowdhury');
        $component->assertDontSee('Tariq Hasan');
        $component->assertDontSee('Kamal Hossain');

        // Filter: restricted
        $component->set('statusFilter', 'restricted');
        $component->assertSee('Tariq Hasan');
        $component->assertDontSee('Rahim Chowdhury');
        $component->assertDontSee('Fatima Zahra');
        $component->assertDontSee('Kamal Hossain');

        // Filter: revoked
        $component->set('statusFilter', 'revoked');
        $component->assertSee('Kamal Hossain');
        $component->assertDontSee('Rahim Chowdhury');
        $component->assertDontSee('Fatima Zahra');
        $component->assertDontSee('Tariq Hasan');
    }

    public function test_sorting_and_reset_filters(): void
    {
        $component = Livewire::actingAs($this->staff, 'web')
            ->test(MemberDirectory::class, ['workspace' => 'ws-emk-dhaka']);

        // Sort by first_name asc
        $component->call('sortBy', 'first_name');
        $component->assertSet('sortField', 'first_name');
        $component->assertSet('sortDirection', 'asc');

        // Sort by first_name desc
        $component->call('sortBy', 'first_name');
        $component->assertSet('sortDirection', 'desc');

        // Apply filters and reset
        $component->set('search', 'Rahim');
        $component->set('statusFilter', 'active');
        $component->call('resetFilters');

        $component->assertSet('search', '');
        $component->assertSet('statusFilter', '');
        $component->assertSet('sortField', 'created_at');
        $component->assertSet('sortDirection', 'desc');
    }
}
