<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Platform\Models\PlatformSetting;
use App\Domain\Platform\Services\BackupService;
use App\Domain\Reports\DTOs\OperationalMetricsDTO;
use App\Domain\Reports\Services\MetricsAggregationService;
use App\Domain\Workspaces\Models\Workspace;
use App\Livewire\Platform\PlatformSettingsHub;
use App\Livewire\Reports\OperationalDashboard;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

/**
 * Phase 10 Holistic End-to-End Operational Lifecycle & Tenancy Defense Test Suite.
 *
 * Verifies all 5 core Phase 10 requirements:
 * - DASH-01: Operational KPI Dashboard & Metrics Aggregation
 * - DASH-02: Tenancy Scope Enforcement & Superadmin National Consolidation
 * - DASH-03: Scoped Data Export & Immutable Audit Logging with CSV Formula Sanitization
 * - DASH-04: Platform Settings Hub, Parameter Encryption & Secret Masking
 * - DASH-05: Encrypted Backup Archive Engine, SHA-256 Manifest & Disaster Recovery Drills
 */
class EndToEndDashboardAndPlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected Workspace $workspaceDhaka;
    protected Workspace $otherWorkspace;
    protected User $manager;
    protected User $superAdmin;
    protected User $delegatedStaff;
    protected MembershipTerm $term;
    protected string $backupDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            \Database\Seeders\SpaceAndWorkspaceSeeder::class,
            \Database\Seeders\MembershipTermSeeder::class,
            \Database\Seeders\RolesAndPermissionsSeeder::class,
            \Database\Seeders\DefaultMembershipFormSeeder::class,
            \Database\Seeders\EmailTemplateSeeder::class,
            \Database\Seeders\WorkflowRuleSeeder::class,
        ]);

        // 1. Primary workspace: EMK Center Dhaka
        $this->workspaceDhaka = Workspace::firstOrCreate(
            ['id' => 'ws-emk-dhaka'],
            [
                'space_id' => 1,
                'name' => 'EMK Center Dhaka',
                'is_active' => true,
            ]
        );

        // 2. Secondary workspace: Other American Space for tenancy isolation testing
        $this->otherWorkspace = Workspace::firstOrCreate(
            ['id' => 'ws-other'],
            [
                'space_id' => 1,
                'name' => 'Other American Space',
                'is_active' => true,
            ]
        );

        // 3. Workspace Manager for ws-emk-dhaka
        $this->manager = User::where('email', 'manager.dhaka@emk.test')->first()
            ?? User::create([
                'workspace_id' => 'ws-emk-dhaka',
                'name' => 'Dhaka Center Manager',
                'email' => 'manager.dhaka@emk.test',
                'password' => bcrypt('secret123'),
                'guard' => 'web',
                'is_active' => true,
            ]);
        if (! $this->manager->hasRole('workspace_manager')) {
            $this->manager->assignRole('workspace_manager');
        }

        // 4. Global Superadmin
        $this->superAdmin = User::where('email', 'admin@emk.test')->first()
            ?? User::create([
                'workspace_id' => null,
                'name' => 'Global Superadmin',
                'email' => 'admin@emk.test',
                'password' => bcrypt('secret123'),
                'guard' => 'web',
                'is_active' => true,
            ]);
        if (! $this->superAdmin->hasRole('superadmin')) {
            $this->superAdmin->assignRole('superadmin');
        }

        // 5. Delegated Staff (lacks platform_services.manage_settings)
        $this->delegatedStaff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Front Desk Staff',
            'email' => 'staff.delegated.' . Str::random(5) . '@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->delegatedStaff->assignRole('delegated_staff');

        // 6. Membership Term
        $this->term = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-emk-dhaka', 'code' => 'term_std_1yr'],
            [
                'name' => 'Standard 1-Year Membership',
                'duration_months' => 12,
                'fee_bdt' => 1000.00,
                'is_active' => true,
            ]
        );

        // 7. Backup Directory in storage/app/backups
        $this->backupDir = storage_path('app/backups');
        if (! is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
        config(['backup.path' => $this->backupDir]);
        config(['backup.retention_days' => 30]);
    }

    protected function tearDown(): void
    {
        // Clean up any test backup archives created in the storage folder
        if (is_dir($this->backupDir)) {
            $files = glob($this->backupDir . '/*');
            if (is_array($files)) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }

        parent::tearDown();
    }

    /**
     * Scenario 1 (DASH-01 - Dashboard Monitoring):
     * - Seed workspace ws-emk-dhaka with Members, Leads across multiple statuses, ID cards, and paid Invoices.
     * - Authenticate as Workspace Manager.
     * - Request /crm/ws-emk-dhaka/reports.
     * - Assert 200 OK.
     * - Assert response contains accurate counts: total members, lead conversion rate, card statuses, and total collected revenue in BDT.
     * - Filter by 30-day date range and assert metrics reflect the filtered dataset.
     */
    public function test_scenario_1_dashboard_monitoring_metrics_and_filtering(): void
    {
        $now = Carbon::now();

        // Seed 2 active members within 30-day window (each creates a converted lead in ws-emk-dhaka)
        $member1 = $this->createMemberRecord('active', 'Rafiqul', 'Islam', $now->copy()->subDays(10)->toDateString());
        $member2 = $this->createMemberRecord('active', 'Tasnim', 'Jahan', $now->copy()->subDays(5)->toDateString());

        // Seed an older member outside 30 days (60 days ago) to verify date range isolation
        $olderMember = $this->createMemberRecord('active', 'Older', 'Member', $now->copy()->subDays(60)->toDateString());

        // Seed additional leads within 30 days: 1 under_review, 1 submitted
        // Within 30 days: 2 converted leads + 1 under_review + 1 submitted = 4 leads -> 2/4 = 50% conversion rate
        $this->createLeadRecord('under_review', $now->copy()->subDays(8)->toDateString());
        $this->createLeadRecord('submitted', $now->copy()->subDays(3)->toDateString());

        // Seed ID cards: 1 card_printed_ready_for_pickup, 1 delivered
        $this->createIdCardRecord($member1->id, 'card_printed_ready_for_pickup');
        $this->createIdCardRecord($member2->id, 'delivered');

        // Seed paid invoices: 1000.00 and 1500.00 BDT within 30 days (Total: 2500.00 BDT, 2 paid invoices)
        $this->createInvoiceRecord($member1->id, 'paid', 1000.00, $now->copy()->subDays(7)->toDateString());
        $this->createInvoiceRecord($member2->id, 'paid', 1500.00, $now->copy()->subDays(2)->toDateString());
        $this->createInvoiceRecord($member2->id, 'voided', 500.00, $now->copy()->subDays(1)->toDateString());
        // Older paid invoice outside 30 days
        $this->createInvoiceRecord($olderMember->id, 'paid', 800.00, $now->copy()->subDays(60)->toDateString());

        // 1. Authenticate as Workspace Manager and access /reports
        $response = $this->actingAs($this->manager)
            ->get('/crm/ws-emk-dhaka/reports');

        $response->assertStatus(200);
        $response->assertSee('Operational Metrics & Analytics');
        $response->assertSee('Active Members');
        $response->assertSee('Pipeline Leads');
        $response->assertSee('Collected Revenue');

        // 2. Direct service metric calculation asserting 30-day filtered dataset
        /** @var MetricsAggregationService $metricsService */
        $metricsService = app(MetricsAggregationService::class);
        $dateFrom30 = $now->copy()->subDays(30)->toDateString();
        $dateToNow = $now->toDateString();

        $dtoFiltered = $metricsService->getMetrics('ws-emk-dhaka', $dateFrom30, $dateToNow, false);

        $this->assertInstanceOf(OperationalMetricsDTO::class, $dtoFiltered);
        $this->assertSame(2, $dtoFiltered->totalMembers); // 2 members within 30 days (older excluded)
        $this->assertSame(2, $dtoFiltered->memberStatusBreakdown['active'] ?? 0);
        $this->assertSame(4, $dtoFiltered->totalLeads); // 4 leads within 30 days (older lead excluded)
        $this->assertSame(2, $dtoFiltered->leadPipelineBreakdown['converted'] ?? 0);
        $this->assertSame(1, $dtoFiltered->leadPipelineBreakdown['under_review'] ?? 0);
        $this->assertSame(1, $dtoFiltered->leadPipelineBreakdown['submitted'] ?? 0);
        $this->assertSame(50.0, $dtoFiltered->conversionRate);
        $this->assertSame(2, $dtoFiltered->totalIdCards);
        $this->assertSame(1, $dtoFiltered->idCardStatusBreakdown['card_printed_ready_for_pickup'] ?? 0);
        $this->assertSame(1, $dtoFiltered->idCardStatusBreakdown['delivered'] ?? 0);
        $this->assertSame(2500.00, $dtoFiltered->totalRevenueBdt); // 2500 BDT within 30 days
        $this->assertSame(2, $dtoFiltered->paidInvoicesCount);

        // 3. Livewire component rendering and date filtering
        Livewire::actingAs($this->manager)
            ->test(OperationalDashboard::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertStatus(200)
            ->assertSet('datePreset', '30_days')
            ->assertSee('2,500.00')
            ->assertSee('50% Conv. Rate')
            ->call('applyDatePreset', 'all')
            ->assertSet('datePreset', 'all')
            ->assertSet('dateFrom', null)
            ->assertSet('dateTo', null)
            ->assertSee('3,300.00'); // includes older 800 BDT invoice
    }

    /**
     * Scenario 2 (DASH-02 - Cross-Workspace Defense):
     * - Workspace Manager of ws-emk-dhaka attempts to access /crm/ws-other/reports,
     *   /crm/ws-other/exports/members, /crm/ws-other/settings, and /crm/ws-other/settings/backups.
     * - Assert all requests fail closed with HTTP 403 Forbidden.
     */
    public function test_scenario_2_cross_workspace_defense_enforces_http_403(): void
    {
        $this->actingAs($this->manager);

        // 1. Cross-workspace reports access rejected with 403
        $reportsResponse = $this->get('/crm/ws-other/reports');
        $reportsResponse->assertStatus(403);

        // 2. Cross-workspace member CSV export rejected with 403
        $exportResponse = $this->get('/crm/ws-other/exports/members');
        $exportResponse->assertStatus(403);

        // 3. Cross-workspace platform settings rejected with 403
        $settingsResponse = $this->get('/crm/ws-other/settings');
        $settingsResponse->assertStatus(403);

        // 4. Cross-workspace backups console rejected with 403
        $backupsResponse = $this->get('/crm/ws-other/settings/backups');
        $backupsResponse->assertStatus(403);
    }

    /**
     * Scenario 3 (DASH-02 - Superadmin Global View):
     * - Authenticate as Superadmin (admin@emk.test).
     * - Access dashboard and toggle $isGlobalView = true.
     * - Assert response compiles metrics across all workspaces without failing tenancy scope checks.
     */
    public function test_scenario_3_superadmin_global_view_aggregates_across_workspaces(): void
    {
        // 1. Seed Dhaka workspace: 2 members, 1 extra lead, 1 paid invoice (1000 BDT)
        $dhakaMember1 = $this->createMemberRecord('active', 'Dhaka1', 'User', '2026-01-01');
        $dhakaMember2 = $this->createMemberRecord('active', 'Dhaka2', 'User', '2026-01-02');
        $this->createLeadRecord('under_review', '2026-01-03');
        $this->createInvoiceRecord($dhakaMember1->id, 'paid', 1000.00, '2026-01-04');

        // 2. Seed Other workspace: 1 member, 1 extra lead, 1 paid invoice (500 BDT)
        $termOther = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-other', 'code' => 'term_other_1yr'],
            [
                'name' => 'Other 1-Year',
                'duration_months' => 12,
                'fee_bdt' => 500.00,
                'is_active' => true,
            ]
        );
        $otherMember = $this->createMemberRecord('active', 'Other1', 'User', '2026-01-01', 'ws-other', $termOther->id);
        $this->createLeadRecord('submitted', '2026-01-02', 'ws-other');
        $this->createInvoiceRecord($otherMember->id, 'paid', 500.00, '2026-01-03', 'ws-other');

        // 3. Superadmin can access dashboard and toggle global consolidated view
        $this->actingAs($this->superAdmin);

        $response = $this->get('/crm/ws-emk-dhaka/reports');
        $response->assertStatus(200);

        Livewire::actingAs($this->superAdmin)
            ->test(OperationalDashboard::class, ['workspace' => 'ws-emk-dhaka'])
            ->assertSet('canSwitchScope', true)
            ->call('toggleGlobalView', true)
            ->assertSet('isGlobalView', true)
            ->assertSee('National American Spaces Overview');

        // 4. Validate aggregated metrics across all American Spaces
        /** @var MetricsAggregationService $metricsService */
        $metricsService = app(MetricsAggregationService::class);
        $global = $metricsService->getGlobalMetrics();

        // 3 total members, 5 leads (3 converted + 1 under_review + 1 submitted), 60% conversion rate, 1500 BDT revenue
        $this->assertSame(3, $global['total_members']);
        $this->assertSame(5, $global['total_leads']);
        $this->assertSame(60.0, $global['conversion_rate']);
        $this->assertSame(1500.00, $global['total_revenue_bdt']);
        $this->assertSame(2, $global['paid_invoices_count']);
    }

    /**
     * Scenario 4 (DASH-03 - Scoped Data Export & Audit Log):
     * - Authenticate as Workspace Manager.
     * - Initiate Member CSV export for ws-emk-dhaka with status filter active.
     * - Assert streamed CSV contains UTF-8 BOM, sanitized headers, and escaped cells (' prepended to formulas).
     * - Query audit_logs table and assert an immutable record exists with event_type = 'members_exported',
     *   actor_id = $manager->id, row count, and IP address.
     */
    public function test_scenario_4_scoped_data_export_and_immutable_audit_log(): void
    {
        // Seed an active member with formula injection payload to test cell neutralization
        $injectedMember = $this->createMemberRecord(
            'active',
            '=1+1',
            '@SUM(A1:B1)',
            '2026-01-10'
        );

        $response = $this->actingAs($this->manager)
            ->get('/crm/ws-emk-dhaka/exports/members?status=active');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        // 1. Assert UTF-8 BOM is prepended (\xEF\xBB\xBF)
        $this->assertTrue(str_starts_with($content, "\xEF\xBB\xBF"));

        // 2. Assert sanitized headers
        $this->assertStringContainsString('"Member ID"', $content);
        $this->assertStringContainsString('"First Name"', $content);
        $this->assertStringContainsString('"Last Name"', $content);
        $this->assertStringContainsString('Email', $content);

        // 3. Assert formula injection neutralization (prepended with single quote ')
        $this->assertStringContainsString("'=1+1", $content);
        $this->assertStringContainsString("'@SUM(A1:B1)", $content);

        // 4. Assert immutable audit log entry
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->manager->id,
            'actor_guard' => 'web',
            'event_type' => AuditLog::EVENT_MEMBERS_EXPORTED,
            'auditable_type' => Member::class,
        ]);

        $auditLog = AuditLog::where('event_type', AuditLog::EVENT_MEMBERS_EXPORTED)
            ->where('workspace_id', 'ws-emk-dhaka')
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($this->manager->id, $auditLog->actor_id);
        $this->assertNotEmpty($auditLog->ip_address);
        $this->assertGreaterThanOrEqual(1, $auditLog->new_values['exported_rows'] ?? 0);
        $this->assertSame('active', $auditLog->new_values['filters']['status'] ?? null);
    }

    /**
     * Scenario 5 (DASH-04 - Platform Settings & Sensitive Masking):
     * - Authenticate as Workspace Manager.
     * - Open /crm/ws-emk-dhaka/settings.
     * - Update system parameter payments.manual_bkash_merchant_number and a secret parameter.
     * - Assert parameter is persisted encrypted.
     * - Assert HTML view displays '••••••••' for the secret parameter.
     * - Query audit_logs and assert the parameter update is recorded with sensitive tokens scrubbed to '[REDACTED]'.
     * - Verify delegated staff user without platform_services.manage_settings visiting settings receives HTTP 403.
     */
    public function test_scenario_5_platform_settings_and_sensitive_masking(): void
    {
        $this->actingAs($this->manager);

        // 1. Manager can open settings page
        $viewResponse = $this->get('/crm/ws-emk-dhaka/settings');
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Platform Settings Hub');

        // 2. Update system parameter and secret parameter via Livewire
        Livewire::test(PlatformSettingsHub::class, ['workspace' => 'ws-emk-dhaka'])
            ->set('settings.payments.manual_bkash_merchant_number', '01899998877')
            ->set('settings.payments.bkash_secret', 'SuperSecretGatewayKeyLive2026!')
            ->call('saveSettings')
            ->assertHasNoErrors()
            ->assertSee('Platform settings updated successfully.');

        // 3. Assert parameter is persisted encrypted in database
        $secretSetting = PlatformSetting::where('workspace_id', 'ws-emk-dhaka')
            ->where('key', 'payments.bkash_secret')
            ->first();

        $this->assertNotNull($secretSetting);
        $this->assertSame('SuperSecretGatewayKeyLive2026!', $secretSetting->value);
        $this->assertSame('••••••••', $secretSetting->masked_value);

        $rawCipher = DB::table('platform_settings')
            ->where('id', $secretSetting->id)
            ->value('value');
        $this->assertNotSame('SuperSecretGatewayKeyLive2026!', $rawCipher);
        $this->assertSame('SuperSecretGatewayKeyLive2026!', Crypt::decryptString($rawCipher));

        // 4. Assert HTML view renders '••••••••' masked view for secret
        $htmlResponse = $this->get('/crm/ws-emk-dhaka/settings?tab=payments');
        $htmlResponse->assertStatus(200);
        $htmlResponse->assertSee('••••••••');

        // Also assert masked view in Livewire component
        Livewire::test(PlatformSettingsHub::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('switchTab', 'payments')
            ->assertSee('••••••••');

        // 5. Query audit_logs and assert sensitive values scrubbed to '[REDACTED]'
        $auditLog = AuditLog::where('auditable_id', $secretSetting->id)
            ->where('event_type', AuditLog::EVENT_PLATFORM_SETTING_UPDATED)
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('[REDACTED]', $auditLog->new_values['value']);
        $this->assertNotSame('SuperSecretGatewayKeyLive2026!', $auditLog->new_values['value']);

        // 6. Verify delegated staff user without platform_services.manage_settings visiting settings receives 403
        $unauthorizedResponse = $this->actingAs($this->delegatedStaff)
            ->get('/crm/ws-emk-dhaka/settings');
        $unauthorizedResponse->assertStatus(403);
    }

    /**
     * Scenario 6 (DASH-05 - Encrypted Backup & Integrity Check):
     * - Trigger on-demand backup execution.
     * - Assert backup archive file exists in storage/app/backups/ and is AES-256 encrypted.
     * - Assert .sha256 manifest exists.
     * - Execute integrity verification drill and assert PASS status.
     * - Mutate archive content and assert verification fails with checksum corruption error.
     */
    public function test_scenario_6_encrypted_backup_and_integrity_check(): void
    {
        $this->actingAs($this->manager);

        /** @var BackupService $backupService */
        $backupService = app(BackupService::class);

        // 1. Trigger on-demand encrypted backup
        $result = $backupService->createBackup(includeDocuments: true, encrypt: true);

        // 2. Assert backup archive file exists in backup path and is AES-256 encrypted
        $this->assertFileExists($result['path']);
        $this->assertStringEndsWith('.zip.enc', $result['filename']);
        $this->assertTrue($result['encrypted']);
        $this->assertGreaterThan(0, filesize($result['path']));

        // Assert file cannot be opened as a raw zip archive without decryption
        $zip = new ZipArchive();
        $this->assertNotTrue($zip->open($result['path']));

        // 3. Assert .sha256 manifest exists with matching checksum
        $manifestPath = $result['path'] . '.sha256';
        $this->assertFileExists($manifestPath);
        $expectedHash = trim((string) file_get_contents($manifestPath));
        $actualHash = hash_file('sha256', $result['path']);
        $this->assertSame($expectedHash, $actualHash);

        // 4. Execute integrity verification drill and assert PASS status
        $verification = $backupService->verifyArchive($result['filename']);
        $this->assertTrue($verification['valid']);

        // 5. Mutate archive content and assert verification fails with checksum corruption error
        $archiveBytes = file_get_contents($result['path']);
        $archiveBytes[10] = chr(ord($archiveBytes[10]) ^ 0xFF); // mutate a single byte
        file_put_contents($result['path'], $archiveBytes);

        $corruptedVerification = $backupService->verifyArchive($result['filename']);
        $this->assertFalse($corruptedVerification['valid']);
        $this->assertStringContainsString('Checksum verification failed', $corruptedVerification['error']);
    }

    // --- Helper Fixture Factories ---

    protected function createMemberRecord(
        string $status,
        string $firstName,
        string $lastName,
        string $date,
        string $workspaceId = 'ws-emk-dhaka',
        ?int $termId = null
    ): Member {
        $user = User::create([
            'workspace_id' => $workspaceId,
            'name' => "{$firstName} {$lastName}",
            'email' => strtolower("{$firstName}.{$lastName}." . Str::random(4) . "@example.com"),
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
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $user->email,
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
            'submitted_at' => Carbon::parse($date),
            'created_at' => Carbon::parse($date),
        ]);

        return Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => 'EMK-2026-' . str_pad((string) rand(1, 9999), 5, '0', STR_PAD_LEFT),
            'workspace_id' => $workspaceId,
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'membership_term_id' => $termId ?? $this->term->id,
            'status' => $status,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $user->email,
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
            'start_date' => Carbon::parse($date)->toDateString(),
            'expiry_date' => Carbon::parse($date)->addYear()->toDateString(),
            'created_at' => Carbon::parse($date),
        ]);
    }

    protected function createLeadRecord(string $status, string $date, string $workspaceId = 'ws-emk-dhaka'): Lead
    {
        return Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'target_space_code' => $workspaceId === 'ws-emk-dhaka' ? 'EMK-DHK' : 'CR-CTG',
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
            'submitted_at' => Carbon::parse($date),
            'created_at' => Carbon::parse($date),
        ]);
    }

    protected function createIdCardRecord(int $memberId, string $status, string $workspaceId = 'ws-emk-dhaka'): IdCard
    {
        return IdCard::create([
            'workspace_id' => $workspaceId,
            'member_id' => $memberId,
            'card_number' => 'CARD-' . strtoupper(Str::random(8)),
            'status' => $status,
        ]);
    }

    protected function createInvoiceRecord(
        int $memberId,
        string $status,
        float $amount,
        string $date,
        string $workspaceId = 'ws-emk-dhaka'
    ): Invoice {
        return Invoice::create([
            'workspace_id' => $workspaceId,
            'member_id' => $memberId,
            'invoice_number' => 'INV-2026-' . str_pad((string) rand(1, 9999), 5, '0', STR_PAD_LEFT),
            'amount_bdt' => $amount,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'BK' . strtoupper(Str::random(8)),
            'status' => $status,
            'paid_at' => Carbon::parse($date),
            'created_at' => Carbon::parse($date),
        ]);
    }
}

