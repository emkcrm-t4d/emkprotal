<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\AccessControl\Exceptions\TenancyBoundaryException;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Reports\Services\MetricsAggregationService;
use App\Domain\Workspaces\Models\Workspace;
use App\Livewire\Reports\OperationalDashboard;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalDashboardTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected User $managerDhaka;
    protected User $managerChattogram;
    protected User $superAdmin;
    protected MembershipTerm $termDhaka;
    protected MembershipTerm $termChattogram;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        // Ensure Chattogram workspace exists
        $ctgSpace = DB::table('spaces')->where('code', 'CR-CTG')->first();
        Workspace::firstOrCreate(
            ['id' => 'ws-chattogram'],
            [
                'space_id' => $ctgSpace ? $ctgSpace->id : 1,
                'name' => 'Chattogram American Corner',
                'is_active' => true,
            ]
        );

        // 1. Manager in ws-emk-dhaka (seeded by RolesAndPermissionsSeeder)
        $this->managerDhaka = User::where('email', 'manager.dhaka@emk.test')->first()
            ?? User::create([
                'workspace_id' => 'ws-emk-dhaka',
                'name' => 'Dhaka Manager',
                'email' => 'manager.dhaka@emk.test',
                'password' => bcrypt('secret123'),
                'guard' => 'web',
                'is_active' => true,
            ])->assignRole('workspace_manager');

        // 2. Manager in ws-chattogram
        $this->managerChattogram = User::firstOrCreate(
            ['email' => 'manager.ctg@emk.test'],
            [
                'workspace_id' => 'ws-chattogram',
                'name' => 'Chattogram Manager',
                'password' => bcrypt('secret123'),
                'guard' => 'web',
                'is_active' => true,
            ]
        );
        $this->managerChattogram->assignRole('workspace_manager');

        // 3. Superadmin (cross-workspace authority)
        $this->superAdmin = User::where('email', 'admin@emk.test')->first()
            ?? User::create([
                'workspace_id' => null,
                'name' => 'Global Superadmin',
                'email' => 'admin@emk.test',
                'password' => bcrypt('secret123'),
                'guard' => 'web',
                'is_active' => true,
            ])->assignRole('superadmin');

        // Membership terms
        $this->termDhaka = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-emk-dhaka', 'code' => 'term_dhaka_1yr'],
            [
                'name' => 'Dhaka 1-Year',
                'duration_months' => 12,
                'fee_bdt' => 1000.00,
                'is_active' => true,
            ]
        );

        $this->termChattogram = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-chattogram', 'code' => 'term_ctg_1yr'],
            [
                'name' => 'Chattogram 1-Year',
                'duration_months' => 12,
                'fee_bdt' => 800.00,
                'is_active' => true,
            ]
        );
    }

    public function test_workspace_manager_cannot_access_other_workspace_reports_route(): void
    {
        // Manager in Dhaka attempting to view Chattogram reports receives 403 Forbidden
        $response = $this->actingAs($this->managerDhaka)
            ->get('/crm/ws-chattogram/reports');

        $response->assertStatus(403);
    }

    public function test_workspace_manager_cannot_toggle_global_view(): void
    {
        Livewire::actingAs($this->managerDhaka)
            ->test(OperationalDashboard::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('toggleGlobalView', true)
            ->assertStatus(403);
    }

    public function test_workspace_manager_cannot_switch_workspace_scope(): void
    {
        Livewire::actingAs($this->managerDhaka)
            ->test(OperationalDashboard::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('switchWorkspaceScope', 'ws-chattogram')
            ->assertStatus(403);
    }

    public function test_workspace_manager_cannot_tamper_selected_workspace_scope_on_hydration(): void
    {
        Livewire::actingAs($this->managerDhaka)
            ->test(OperationalDashboard::class, ['workspace' => 'ws-emk-dhaka'])
            ->set('selectedWorkspaceScope', 'ws-chattogram')
            ->assertStatus(403);
    }

    public function test_workspace_manager_cannot_tamper_is_global_view_on_hydration(): void
    {
        Livewire::actingAs($this->managerDhaka)
            ->test(OperationalDashboard::class, ['workspace' => 'ws-emk-dhaka'])
            ->set('isGlobalView', true)
            ->assertStatus(403);
    }

    public function test_unauthorized_user_invoking_get_global_metrics_throws_exception(): void
    {
        $this->expectException(TenancyBoundaryException::class);
        $this->expectExceptionMessage('Unauthorized: Multi-workspace aggregation requires Superadmin privileges.');

        $this->actingAs($this->managerDhaka);

        /** @var MetricsAggregationService $service */
        $service = app(MetricsAggregationService::class);
        $service->getGlobalMetrics();
    }

    public function test_superadmin_can_access_any_workspace_dashboard_and_toggle_global_view(): void
    {
        // Superadmin accessing ws-emk-dhaka
        $response1 = $this->actingAs($this->superAdmin)
            ->get('/crm/ws-emk-dhaka/reports');
        $response1->assertStatus(200);

        // Superadmin accessing ws-chattogram
        $response2 = $this->actingAs($this->superAdmin)
            ->get('/crm/ws-chattogram/reports');
        $response2->assertStatus(200);

        // Superadmin can toggle global view via Livewire
        Livewire::actingAs($this->superAdmin)
            ->test(OperationalDashboard::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertSet('canSwitchScope', true)
            ->assertSee('All Spaces (National)')
            ->call('toggleGlobalView', true)
            ->assertSet('isGlobalView', true)
            ->assertSee('National American Spaces Overview');
    }

    public function test_superadmin_can_switch_workspace_scope(): void
    {
        Livewire::actingAs($this->superAdmin)
            ->test(OperationalDashboard::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('switchWorkspaceScope', 'ws-chattogram')
            ->assertSet('selectedWorkspaceScope', 'ws-chattogram')
            ->assertSet('isGlobalView', false);
    }

    public function test_global_metrics_accurately_aggregates_across_multiple_workspaces_without_data_corruption(): void
    {
        // 1. Seed Dhaka data: 2 members, 1 extra lead, 1 paid invoice (1000 BDT)
        $this->seedMember('ws-emk-dhaka', $this->termDhaka->id, 'active');
        $this->seedMember('ws-emk-dhaka', $this->termDhaka->id, 'active');
        $this->seedLead('ws-emk-dhaka', 'under_review');
        $this->seedInvoice('ws-emk-dhaka', 'paid', 1000.00);

        // 2. Seed Chattogram data: 1 member, 1 extra lead, 1 paid invoice (800 BDT)
        $this->seedMember('ws-chattogram', $this->termChattogram->id, 'active');
        $this->seedLead('ws-chattogram', 'submitted');
        $this->seedInvoice('ws-chattogram', 'paid', 800.00);

        $this->actingAs($this->superAdmin);

        /** @var MetricsAggregationService $service */
        $service = app(MetricsAggregationService::class);
        $global = $service->getGlobalMetrics();

        // Dhaka: 2 members, (2 converted + 1 under_review) = 3 leads, 1000.00 revenue
        // Chattogram: 1 member, (1 converted + 1 submitted) = 2 leads, 800.00 revenue
        // Total: 3 members, 5 leads, 3 converted (60% conv rate), 1800.00 revenue, 2 paid invoices
        $this->assertEquals(3, $global['total_members']);
        $this->assertEquals(5, $global['total_leads']);
        $this->assertEquals(60.0, $global['conversion_rate']);
        $this->assertEquals(1800.00, $global['total_revenue_bdt']);
        $this->assertEquals(2, $global['paid_invoices_count']);

        $breakdown = $global['spaces_breakdown'];
        $this->assertArrayHasKey('EMK-DHK', $breakdown);
        $this->assertArrayHasKey('CR-CTG', $breakdown);

        $this->assertEquals(2, $breakdown['EMK-DHK']['total_members']);
        $this->assertEquals(3, $breakdown['EMK-DHK']['total_leads']);
        $this->assertEquals(1000.00, $breakdown['EMK-DHK']['total_revenue_bdt']);

        $this->assertEquals(1, $breakdown['CR-CTG']['total_members']);
        $this->assertEquals(2, $breakdown['CR-CTG']['total_leads']);
        $this->assertEquals(800.00, $breakdown['CR-CTG']['total_revenue_bdt']);
    }

    protected function seedMember(string $workspaceId, int $termId, string $status): Member
    {
        $portalUser = User::create([
            'workspace_id' => $workspaceId,
            'name' => 'Member User ' . Str::random(4),
            'email' => 'user.' . Str::random(6) . '@example.com',
            'password' => bcrypt('secret123'),
            'guard' => 'member',
            'is_active' => true,
        ]);

        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'target_space_code' => $workspaceId === 'ws-emk-dhaka' ? 'EMK-DHK' : 'CR-CTG',
            'status' => 'converted',
            'bkash_txid' => 'TX' . strtoupper(Str::random(8)),
            'first_name' => 'First',
            'last_name' => 'Last',
            'email' => $portalUser->email,
            'contact_number' => '+88017' . rand(10000000, 99999999),
            'date_of_birth' => '1998-05-15',
            'age_at_submission' => 28,
            'sex' => 'male',
            'address_house_no' => '12',
            'address_area' => 'Area',
            'address_zip_code' => '1209',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Professional',
            'institution_study' => 'University',
            'highest_certification' => 'Masters',
            'area_of_subject' => 'Business',
            'has_parental_consent' => false,
            'terms_agreed' => true,
            'submitted_at' => Carbon::now(),
        ]);

        return Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => ($workspaceId === 'ws-emk-dhaka' ? 'EMK' : 'CTG') . '-2026-' . str_pad((string) rand(1, 9999), 5, '0', STR_PAD_LEFT),
            'workspace_id' => $workspaceId,
            'lead_id' => $lead->id,
            'user_id' => $portalUser->id,
            'membership_term_id' => $termId,
            'status' => $status,
            'first_name' => 'First',
            'last_name' => 'Last',
            'email' => $portalUser->email,
            'contact_number' => '+88017' . rand(10000000, 99999999),
            'date_of_birth' => '1998-05-15',
            'sex' => 'male',
            'address_house_no' => '12',
            'address_area' => 'Area',
            'address_zip_code' => '1209',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Professional',
            'institution_study' => 'University',
            'highest_certification' => 'Masters',
            'area_of_subject' => 'Business',
            'start_date' => Carbon::now()->subMonths(1)->toDateString(),
            'expiry_date' => Carbon::now()->addMonths(11)->toDateString(),
        ]);
    }

    protected function seedLead(string $workspaceId, string $status): Lead
    {
        return Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'target_space_code' => $workspaceId === 'ws-emk-dhaka' ? 'EMK-DHK' : 'CR-CTG',
            'status' => $status,
            'bkash_txid' => 'TX' . strtoupper(Str::random(8)),
            'first_name' => 'ExtraLead',
            'last_name' => 'Applicant',
            'email' => 'extralead.' . Str::random(5) . '@example.com',
            'contact_number' => '+88018' . rand(10000000, 99999999),
            'date_of_birth' => '2000-01-01',
            'age_at_submission' => 26,
            'sex' => 'female',
            'address_house_no' => '44',
            'address_area' => 'Area',
            'address_zip_code' => '1213',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Student',
            'institution_study' => 'University',
            'highest_certification' => 'HSC',
            'area_of_subject' => 'BBA',
            'has_parental_consent' => false,
            'terms_agreed' => true,
            'submitted_at' => Carbon::now(),
        ]);
    }

    protected function seedInvoice(string $workspaceId, string $status, float $amount): Invoice
    {
        return Invoice::create([
            'workspace_id' => $workspaceId,
            'member_id' => 1,
            'invoice_number' => 'INV-2026-' . str_pad((string) rand(1, 9999), 5, '0', STR_PAD_LEFT),
            'amount_bdt' => $amount,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'BK' . strtoupper(Str::random(8)),
            'status' => $status,
            'paid_at' => Carbon::now(),
        ]);
    }
}
