<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Reports\DTOs\OperationalMetricsDTO;
use App\Domain\Reports\Services\MetricsAggregationService;
use App\Domain\Workspaces\Models\Workspace;
use App\Livewire\Reports\OperationalDashboard;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $staffWithoutPermission;
    protected MembershipTerm $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        // Workspace Manager for ws-emk-dhaka
        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Center Manager',
            'email' => 'manager.op@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');

        // Staff without dashboard_reports permission
        $this->staffWithoutPermission = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Front Desk Staff',
            'email' => 'staff.nodash@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        // No roles or permissions assigned

        $this->term = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-emk-dhaka', 'code' => 'term_standard_1yr'],
            [
                'name' => 'Standard 1-Year Membership',
                'duration_months' => 12,
                'fee_bdt' => 1000.00,
                'is_active' => true,
            ]
        );
    }

    public function test_unauthenticated_request_is_redirected(): void
    {
        $response = $this->get('/crm/ws-emk-dhaka/reports');
        $response->assertRedirect('/login');
    }

    public function test_staff_without_dashboard_permission_receives_403(): void
    {
        $response = $this->actingAs($this->staffWithoutPermission)
            ->get('/crm/ws-emk-dhaka/reports');

        $response->assertStatus(403);
    }

    public function test_workspace_manager_can_access_dashboard_and_aliases(): void
    {
        // 1. /reports
        $response1 = $this->actingAs($this->manager)
            ->get('/crm/ws-emk-dhaka/reports');
        $response1->assertStatus(200);
        $response1->assertSee('Operational Metrics & Analytics');
        $response1->assertSee('Active Members');
        $response1->assertSee('Pipeline Leads');
        $response1->assertSee('Collected Revenue');

        // 2. /dashboard alias
        $response2 = $this->actingAs($this->manager)
            ->get('/crm/ws-emk-dhaka/dashboard');
        $response2->assertStatus(200);

        // 3. /dashboard_reports alias
        $response3 = $this->actingAs($this->manager)
            ->get('/crm/ws-emk-dhaka/dashboard_reports');
        $response3->assertStatus(200);
    }

    public function test_dashboard_displays_accurate_metrics_and_status_breakdowns(): void
    {
        // Seed 2 active members and 1 inactive member (each creates a converted lead in ws-emk-dhaka)
        $this->seedTestMember('active', 'Rahim', 'Uddin');
        $this->seedTestMember('active', 'Karim', 'Uddin');
        $this->seedTestMember('inactive', 'Fatima', 'Begum');

        // Seed additional leads: 1 under_review, 1 submitted (Total: 3 converted + 1 under_review + 1 submitted = 5 leads, 60% conversion rate)
        $this->seedTestLead('under_review');
        $this->seedTestLead('submitted');

        // Seed ID cards: 2 card_printed_ready_for_pickup, 1 delivered
        $this->seedTestIdCard('card_printed_ready_for_pickup');
        $this->seedTestIdCard('card_printed_ready_for_pickup');
        $this->seedTestIdCard('delivered');

        // Seed paid invoices: 2 paid invoices of 1000.00 and 1500.00 BDT
        $this->seedTestInvoice('paid', 1000.00);
        $this->seedTestInvoice('paid', 1500.00);
        $this->seedTestInvoice('voided', 500.00);

        /** @var MetricsAggregationService $service */
        $service = app(MetricsAggregationService::class);
        $dto = $service->getMetrics('ws-emk-dhaka', null, null, false);

        $this->assertInstanceOf(OperationalMetricsDTO::class, $dto);
        $this->assertEquals(3, $dto->totalMembers);
        $this->assertEquals(2, $dto->memberStatusBreakdown['active']);
        $this->assertEquals(1, $dto->memberStatusBreakdown['inactive']);
        $this->assertEquals(5, $dto->totalLeads);
        $this->assertEquals(3, $dto->leadPipelineBreakdown['converted']);
        $this->assertEquals(1, $dto->leadPipelineBreakdown['under_review']);
        $this->assertEquals(1, $dto->leadPipelineBreakdown['submitted']);
        $this->assertEquals(60.0, $dto->conversionRate);
        $this->assertEquals(3, $dto->totalIdCards);
        $this->assertEquals(2, $dto->idCardStatusBreakdown['card_printed_ready_for_pickup']);
        $this->assertEquals(1, $dto->idCardStatusBreakdown['delivered']);
        $this->assertEquals(2500.00, $dto->totalRevenueBdt);
        $this->assertEquals(2, $dto->paidInvoicesCount);

        // Test Livewire component rendering
        Livewire::actingAs($this->manager)
            ->test(OperationalDashboard::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertSee('2,500.00')
            ->assertSee('60% Conv. Rate');
    }

    public function test_conversion_rate_safely_handles_zero_leads_without_division_by_zero(): void
    {
        /** @var MetricsAggregationService $service */
        $service = app(MetricsAggregationService::class);
        $dto = $service->getMetrics('ws-emk-dhaka', null, null, false);

        $this->assertEquals(0, $dto->totalLeads);
        $this->assertSame(0.0, $dto->conversionRate);

        Livewire::actingAs($this->manager)
            ->test(OperationalDashboard::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertSet('datePreset', '30_days')
            ->assertSee('0% Conv. Rate');
    }

    public function test_date_presets_recalculate_metrics_accurately(): void
    {
        Livewire::actingAs($this->manager)
            ->test(OperationalDashboard::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('applyDatePreset', '7_days')
            ->assertSet('datePreset', '7_days')
            ->assertSet('dateFrom', Carbon::now()->subDays(7)->toDateString())
            ->assertSet('dateTo', Carbon::now()->toDateString())
            ->call('applyDatePreset', 'year')
            ->assertSet('datePreset', 'year')
            ->assertSet('dateFrom', Carbon::now()->startOfYear()->toDateString())
            ->assertSet('dateTo', Carbon::now()->toDateString())
            ->call('applyDatePreset', 'all')
            ->assertSet('datePreset', 'all')
            ->assertSet('dateFrom', null)
            ->assertSet('dateTo', null);
    }

    public function test_metrics_caching_and_refresh_invalidation(): void
    {
        /** @var MetricsAggregationService $service */
        $service = app(MetricsAggregationService::class);

        // Initial compute caches result
        $dto1 = $service->getMetrics('ws-emk-dhaka', '2026-01-01', '2026-01-31', true);
        $this->assertEquals(0, $dto1->totalMembers);

        // Add a new member after cache is written
        $this->seedTestMember('active', 'New', 'Member', '2026-01-15');

        // Read again with cache enabled -> still 0 (demonstrates caching)
        $dtoCached = $service->getMetrics('ws-emk-dhaka', '2026-01-01', '2026-01-31', true);
        $this->assertEquals(0, $dtoCached->totalMembers);

        // Invalidate cache
        $service->clearMetricsCache('ws-emk-dhaka');

        // Read again -> now sees 1 member
        $dtoFresh = $service->getMetrics('ws-emk-dhaka', '2026-01-01', '2026-01-31', true);
        $this->assertEquals(1, $dtoFresh->totalMembers);

        // Test Livewire refreshMetrics action
        Livewire::actingAs($this->manager)
            ->test(OperationalDashboard::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('refreshMetrics')
            ->assertStatus(200);
    }

    protected function seedTestMember(string $status, string $first, string $last, ?string $createdDate = null): Member
    {
        $portalUser = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => "{$first} {$last}",
            'email' => strtolower("{$first}.{$last}." . Str::random(4) . "@example.com"),
            'password' => bcrypt('secret123'),
            'guard' => 'member',
            'is_active' => true,
        ]);

        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'converted',
            'bkash_txid' => 'TX' . strtoupper(Str::random(8)),
            'first_name' => $first,
            'last_name' => $last,
            'email' => $portalUser->email,
            'contact_number' => '+88017' . rand(10000000, 99999999),
            'date_of_birth' => '1998-05-15',
            'age_at_submission' => 28,
            'sex' => 'male',
            'address_house_no' => '12',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Professional',
            'institution_study' => 'University of Dhaka',
            'highest_certification' => 'Masters',
            'area_of_subject' => 'Business',
            'has_parental_consent' => false,
            'terms_agreed' => true,
            'submitted_at' => $createdDate ? Carbon::parse($createdDate) : Carbon::now(),
            'created_at' => $createdDate ? Carbon::parse($createdDate) : Carbon::now(),
        ]);

        return Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => 'EMK-2026-' . str_pad((string) rand(1, 9999), 5, '0', STR_PAD_LEFT),
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'user_id' => $portalUser->id,
            'membership_term_id' => $this->term->id,
            'status' => $status,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $portalUser->email,
            'contact_number' => '+88017' . rand(10000000, 99999999),
            'date_of_birth' => '1998-05-15',
            'sex' => 'male',
            'address_house_no' => '12',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Professional',
            'institution_study' => 'University of Dhaka',
            'highest_certification' => 'Masters',
            'area_of_subject' => 'Business',
            'start_date' => Carbon::now()->subMonths(1)->toDateString(),
            'expiry_date' => Carbon::now()->addMonths(11)->toDateString(),
            'created_at' => $createdDate ? Carbon::parse($createdDate) : Carbon::now(),
        ]);
    }

    protected function seedTestLead(string $status): Lead
    {
        return Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => $status,
            'bkash_txid' => 'TX' . strtoupper(Str::random(8)),
            'first_name' => 'LeadFirst',
            'last_name' => 'LeadLast',
            'email' => 'lead.' . Str::random(5) . '@example.com',
            'contact_number' => '+88018' . rand(10000000, 99999999),
            'date_of_birth' => '2000-01-01',
            'age_at_submission' => 26,
            'sex' => 'female',
            'address_house_no' => '44',
            'address_area' => 'Banani',
            'address_zip_code' => '1213',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Student',
            'institution_study' => 'BRAC University',
            'highest_certification' => 'HSC',
            'area_of_subject' => 'BBA',
            'has_parental_consent' => false,
            'terms_agreed' => true,
            'submitted_at' => Carbon::now(),
        ]);
    }

    protected function seedTestIdCard(string $status): IdCard
    {
        return IdCard::create([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => 1,
            'card_number' => 'CARD-' . strtoupper(Str::random(8)),
            'status' => $status,
        ]);
    }

    protected function seedTestInvoice(string $status, float $amount): Invoice
    {
        return Invoice::create([
            'workspace_id' => 'ws-emk-dhaka',
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
