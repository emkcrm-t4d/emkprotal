<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Reports\Services\WorkspaceDataExportService;
use App\Domain\Workspaces\Models\Workspace;
use App\Livewire\Reports\DataExportCenter;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class WorkspaceDataExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;
    protected User $staffWithoutPermission;
    protected User $unprivilegedStaff;
    protected User $superAdmin;
    protected MembershipTerm $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->manager = User::where('email', 'manager.dhaka@emk.test')->first()
            ?? User::create([
                'workspace_id' => 'ws-emk-dhaka',
                'name' => 'Dhaka Center Manager',
                'email' => 'manager.export.test@emk.test',
                'password' => bcrypt('secret123'),
                'guard' => 'web',
                'is_active' => true,
                'must_reset_password' => false,
            ]);
        if (!$this->manager->hasRole('workspace_manager')) {
            $this->manager->assignRole('workspace_manager');
        }

        // Delegated staff without export permission (has leads.view, members.view, but not members.export_csv or invoices.export)
        $this->staffWithoutPermission = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Delegated Staff',
            'email' => 'staff.noexport.' . Str::random(6) . '@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $this->staffWithoutPermission->assignRole('delegated_staff');

        // Unprivileged user without dashboard_reports.view_dashboard
        $this->unprivilegedStaff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Unprivileged User',
            'email' => 'staff.unprivileged.' . Str::random(6) . '@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
            'must_reset_password' => false,
        ]);

        // Superadmin
        $this->superAdmin = User::where('email', 'admin@emk.test')->first()
            ?? User::create([
                'workspace_id' => 'ws-emk-dhaka',
                'name' => 'Global Admin',
                'email' => 'admin.global.test@emk.test',
                'password' => bcrypt('secret123'),
                'guard' => 'web',
                'is_active' => true,
                'must_reset_password' => false,
            ]);
        if (!$this->superAdmin->hasRole('superadmin')) {
            $this->superAdmin->assignRole('superadmin');
        }

        $this->term = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-emk-dhaka', 'code' => 'term_std_1yr'],
            [
                'name' => 'Standard 1-Year Membership',
                'duration_months' => 12,
                'fee_bdt' => 1000.00,
                'is_active' => true,
            ]
        );
    }

    public function test_workspace_manager_can_export_members_csv(): void
    {
        $this->seedMember('active', 'Anisur', 'Rahman', 'anisur@example.com');
        $this->seedMember('inactive', 'Sadia', 'Islam', 'sadia@example.com');

        $response = $this->actingAs($this->manager)
            ->get('/crm/ws-emk-dhaka/exports/members');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename="members-export-ws-emk-dhaka-', $response->headers->get('content-disposition') ?? '');

        $content = $response->streamedContent();

        // UTF-8 BOM
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Header columns
        $this->assertStringContainsString('"Member ID"', $content);
        $this->assertStringContainsString('"First Name"', $content);
        $this->assertStringContainsString('"Last Name"', $content);
        $this->assertStringContainsString('Email', $content);
        $this->assertStringContainsString('"Contact Number"', $content);
        $this->assertStringContainsString('"Created At"', $content);

        // Data rows
        $this->assertStringContainsString('Anisur', $content);
        $this->assertStringContainsString('Rahman', $content);
        $this->assertStringContainsString('anisur@example.com', $content);
        $this->assertStringContainsString('Sadia', $content);
        $this->assertStringContainsString('Islam', $content);
        $this->assertStringContainsString('sadia@example.com', $content);
    }

    public function test_formula_injection_is_sanitized_in_export(): void
    {
        // Member with dangerous characters =, +, -, @
        $this->seedMember(
            'active',
            '=1+1',
            '@SUM(A1:A10)',
            '+8801712345678@example.com',
            '-InjectedOccupation'
        );

        $response = $this->actingAs($this->manager)
            ->get('/crm/ws-emk-dhaka/exports/members');

        $response->assertStatus(200);
        $content = $response->streamedContent();

        // Must prepend single quote
        $this->assertStringContainsString("'=1+1", $content);
        $this->assertStringContainsString("'@SUM(A1:A10)", $content);
        $this->assertStringContainsString("'+8801712345678@example.com", $content);
        $this->assertStringContainsString("'-InjectedOccupation", $content);
    }

    public function test_sanitize_csv_cell_direct_neutralization(): void
    {
        /** @var WorkspaceDataExportService $service */
        $service = app(WorkspaceDataExportService::class);

        $this->assertSame('', $service->sanitizeCsvCell(null));
        $this->assertSame('Yes', $service->sanitizeCsvCell(true));
        $this->assertSame('No', $service->sanitizeCsvCell(false));
        $this->assertSame('Normal Text', $service->sanitizeCsvCell('Normal Text'));
        $this->assertSame("'=cmd|' /C calc'!A0", $service->sanitizeCsvCell("=cmd|' /C calc'!A0"));
        $this->assertSame("'+8801700000000", $service->sanitizeCsvCell('+8801700000000'));
        $this->assertSame("'-500", $service->sanitizeCsvCell('-500'));
        $this->assertSame("'@evil.server", $service->sanitizeCsvCell('@evil.server'));
    }

    public function test_unauthorized_staff_without_export_permission_receives_403(): void
    {
        $response = $this->actingAs($this->staffWithoutPermission)
            ->get('/crm/ws-emk-dhaka/exports/members');

        $response->assertStatus(403);
    }

    public function test_cross_workspace_export_attempt_is_rejected_with_403(): void
    {
        // Manager belongs to ws-emk-dhaka, attempts export on ws-corner-sylhet
        $response = $this->actingAs($this->manager)
            ->get('/crm/ws-corner-sylhet/exports/members');

        $response->assertStatus(403);
    }

    public function test_workspace_manager_can_export_leads_csv(): void
    {
        $this->seedLead('submitted', 'Farhan', 'Kabir', 'farhan@example.com', 'TX12345678');

        $response = $this->actingAs($this->manager)
            ->get('/crm/ws-emk-dhaka/exports/leads');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename="leads-export-ws-emk-dhaka-', $response->headers->get('content-disposition') ?? '');

        $content = $response->streamedContent();

        $this->assertStringContainsString('"Lead ID"', $content);
        $this->assertStringContainsString('Status', $content);
        $this->assertStringContainsString('"Target Space"', $content);
        $this->assertStringContainsString('"bKash TxID"', $content);
        $this->assertStringContainsString('Farhan', $content);
        $this->assertStringContainsString('Kabir', $content);
        $this->assertStringContainsString('farhan@example.com', $content);
        $this->assertStringContainsString('TX12345678', $content);
    }

    public function test_workspace_manager_can_export_invoices_csv(): void
    {
        $member = $this->seedMember('active', 'Tahmid', 'Hasan', 'tahmid@example.com');
        $this->seedInvoice($member->id, 'INV-2026-0001', 1200.00, 'paid');

        $response = $this->actingAs($this->manager)
            ->get('/crm/ws-emk-dhaka/exports/invoices');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename="invoices-export-ws-emk-dhaka-', $response->headers->get('content-disposition') ?? '');

        $content = $response->streamedContent();

        $this->assertStringContainsString('"Invoice Number"', $content);
        $this->assertStringContainsString('"Member ID"', $content);
        $this->assertStringContainsString('"Amount (BDT)"', $content);
        $this->assertStringContainsString('INV-2026-0001', $content);
        $this->assertStringContainsString('1200.00', $content);
        $this->assertStringContainsString('paid', $content);
        $this->assertStringContainsString($member->member_id, $content);
    }

    public function test_workspace_manager_can_export_metrics_csv(): void
    {
        $member = $this->seedMember('active', 'Nusrat', 'Jahan', 'nusrat@example.com');
        $this->seedInvoice($member->id, 'INV-2026-0002', 1000.00, 'paid');
        $this->seedLead('submitted', 'Tanvir', 'Ahmed', 'tanvir@example.com', 'TX87654321');

        $response = $this->actingAs($this->manager)
            ->get('/crm/ws-emk-dhaka/exports/metrics');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename="metrics-export-ws-emk-dhaka-', $response->headers->get('content-disposition') ?? '');

        $content = $response->streamedContent();

        $this->assertStringContainsString('"Metric Category"', $content);
        $this->assertStringContainsString('"Metric Name"', $content);
        $this->assertStringContainsString('Value', $content);
        $this->assertStringContainsString('"Total Members"', $content);
        $this->assertStringContainsString('"Total Leads"', $content);
        $this->assertStringContainsString('"Total Revenue (BDT)"', $content);
        $this->assertStringContainsString('ws-emk-dhaka', $content);
    }

    public function test_member_export_writes_immutable_audit_log_per_dec_11(): void
    {
        $this->seedMember('active', 'AuditTestFirst', 'AuditTestLast', 'audit.member@example.com');

        $this->actingAs($this->manager)
            ->get('/crm/ws-emk-dhaka/exports/members?status=active');

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->manager->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_MEMBERS_EXPORTED,
            'auditable_type' => Member::class,
        ]);

        $log = AuditLog::where('event_type', AuditLog::EVENT_MEMBERS_EXPORTED)->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals(1, $log->new_values['exported_rows']);
        $this->assertStringContainsString('members-export-ws-emk-dhaka-', $log->new_values['filename']);
        $this->assertSame('active', $log->new_values['filters']['status'] ?? null);
        $this->assertNotEmpty($log->ip_address);
        $this->assertStringContainsString('Exported 1 records from workspace ws-emk-dhaka', $log->reason);
    }

    public function test_lead_export_writes_immutable_audit_log_per_dec_11(): void
    {
        $this->seedLead('submitted', 'LeadAuditFirst', 'LeadAuditLast', 'audit.lead@example.com', 'TX99887766');

        $this->actingAs($this->manager)
            ->get('/crm/ws-emk-dhaka/exports/leads');

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->manager->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_LEADS_EXPORTED,
            'auditable_type' => Lead::class,
        ]);

        $log = AuditLog::where('event_type', AuditLog::EVENT_LEADS_EXPORTED)->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals(1, $log->new_values['exported_rows']);
        $this->assertStringContainsString('leads-export-ws-emk-dhaka-', $log->new_values['filename']);
    }

    public function test_invoice_export_writes_immutable_audit_log_per_dec_11(): void
    {
        $member = $this->seedMember('active', 'InvAuditFirst', 'InvAuditLast', 'audit.inv@example.com');
        $this->seedInvoice($member->id, 'INV-2026-AUDIT', 2500.00, 'paid');

        $this->actingAs($this->manager)
            ->get('/crm/ws-emk-dhaka/exports/invoices');

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->manager->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_INVOICES_EXPORTED,
            'auditable_type' => Invoice::class,
        ]);

        $log = AuditLog::where('event_type', AuditLog::EVENT_INVOICES_EXPORTED)->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals(1, $log->new_values['exported_rows']);
        $this->assertStringContainsString('invoices-export-ws-emk-dhaka-', $log->new_values['filename']);
    }

    public function test_metrics_export_writes_immutable_audit_log_per_dec_11(): void
    {
        $this->actingAs($this->manager)
            ->get('/crm/ws-emk-dhaka/exports/metrics');

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->manager->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_METRICS_EXPORTED,
            'auditable_type' => Workspace::class,
        ]);
    }

    public function test_data_export_center_component_renders_and_updates_estimated_counts(): void
    {
        $this->seedMember('active', 'MemberOne', 'Active', 'm1@example.com');
        $this->seedMember('active', 'MemberTwo', 'Active', 'm2@example.com');
        $this->seedMember('inactive', 'MemberThree', 'Inactive', 'm3@example.com');

        $this->seedLead('submitted', 'LeadOne', 'Sub', 'l1@example.com', 'TX11111111');
        $this->seedLead('submitted', 'LeadTwo', 'Sub', 'l2@example.com', 'TX22222222');
        $this->seedLead('approved', 'LeadThree', 'App', 'l3@example.com', 'TX33333333');

        $member = Member::where('email', 'm1@example.com')->first();
        $this->seedInvoice($member->id, 'INV-TEST-01', 500.00, 'paid');

        Livewire::actingAs($this->manager)
            ->test(DataExportCenter::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertSee('Data Export Center')
            ->assertSee('All export operations are logged to the immutable system audit trail per DEC-11.')
            ->assertSee('Records matching criteria:')
            // Default entity: members, all statuses -> 3 members
            ->assertSet('entity', 'members')
            ->assertSet('estimatedCount', 3)
            // Filter by active -> 2 members
            ->set('statusFilter', 'active')
            ->assertSet('estimatedCount', 2)
            // Switch to leads -> 6 leads total (3 converted from members + 3 newly created)
            ->set('entity', 'leads')
            ->assertSet('statusFilter', 'all')
            ->assertSet('estimatedCount', 6)
            // Filter leads by approved -> 1 lead
            ->set('statusFilter', 'approved')
            ->assertSet('estimatedCount', 1)
            // Switch to invoices -> 1 invoice
            ->set('entity', 'invoices')
            ->assertSet('estimatedCount', 1)
            // Switch to metrics -> 1 summary report
            ->set('entity', 'metrics')
            ->assertSet('estimatedCount', 1);
    }

    public function test_data_export_center_trigger_export_dispatches_download(): void
    {
        Livewire::actingAs($this->manager)
            ->test(DataExportCenter::class, ['workspace' => 'ws-emk-dhaka'])
            ->set('entity', 'members')
            ->call('triggerExport')
            ->assertDispatched('start-export-download');
    }

    public function test_unprivileged_staff_cannot_access_data_export_screen(): void
    {
        $response = $this->actingAs($this->unprivilegedStaff)
            ->get('/crm/ws-emk-dhaka/data-export');

        $response->assertStatus(403);
    }

    public function test_delegated_staff_cannot_trigger_member_export_in_export_center(): void
    {
        Livewire::actingAs($this->staffWithoutPermission)
            ->test(DataExportCenter::class, ['workspace' => 'ws-emk-dhaka'])
            ->set('entity', 'members')
            ->call('triggerExport')
            ->assertForbidden();
    }

    protected function seedMember(
        string $status,
        string $first,
        string $last,
        string $email,
        string $occupation = 'Software Engineer'
    ): Member {
        $portalUser = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => "{$first} {$last}",
            'email' => $email,
            'password' => bcrypt('secret123'),
            'guard' => 'member',
            'is_active' => true,
        ]);
        $portalUser->assignRole('member');

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
            'occupation' => $occupation,
            'institution_study' => 'University of Dhaka',
            'highest_certification' => 'Masters',
            'area_of_subject' => 'Business',
            'has_parental_consent' => false,
            'terms_agreed' => true,
            'submitted_at' => Carbon::now(),
            'created_at' => Carbon::now(),
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
            'occupation' => $occupation,
            'institution_study' => 'University of Dhaka',
            'highest_certification' => 'Masters',
            'area_of_subject' => 'Business',
            'start_date' => Carbon::now()->subMonths(1)->toDateString(),
            'expiry_date' => Carbon::now()->addMonths(11)->toDateString(),
            'created_at' => Carbon::now(),
        ]);
    }

    protected function seedLead(
        string $status,
        string $first,
        string $last,
        string $email,
        string $txId
    ): Lead {
        return Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => $status,
            'bkash_txid' => $txId,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
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

    protected function seedInvoice(
        int $memberId,
        string $invoiceNumber,
        float $amount,
        string $status
    ): Invoice {
        return Invoice::create([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $memberId,
            'invoice_number' => $invoiceNumber,
            'amount_bdt' => $amount,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'BK' . strtoupper(Str::random(8)),
            'status' => $status,
            'paid_at' => Carbon::now(),
            'created_at' => Carbon::now(),
        ]);
    }
}
