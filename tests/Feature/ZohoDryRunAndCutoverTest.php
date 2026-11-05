<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Members\Models\Member;
use App\Domain\Migration\Commands\ZohoDryRunCommand;
use App\Domain\Migration\Commands\ZohoProvisionPortalAccountsCommand;
use App\Domain\Migration\Models\ZohoImportBatch;
use App\Domain\Migration\Services\PortalAccountProvisioningService;
use App\Domain\Migration\Services\ZohoDryRunEngine;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ZohoDryRunAndCutoverTest extends TestCase
{
    use RefreshDatabase;

    protected ZohoDryRunEngine $dryRunEngine;
    protected PortalAccountProvisioningService $provisioningService;
    protected string $workspaceId = 'ws-emk-dhaka';
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->dryRunEngine = app(ZohoDryRunEngine::class);
        $this->provisioningService = app(PortalAccountProvisioningService::class);

        $this->tempDir = sys_get_temp_dir() . '/zoho_cutover_test_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    public function test_dry_run_engine_simulates_ingestion_and_guarantees_zero_database_mutations(): void
    {
        $csvPath = $this->tempDir . '/valid_contacts.csv';
        $content = "CONTACTID,First Name,Last Name,Email,Mobile,Date of Birth,Gender,House No,Road No,Area,Postal Code,Division,District,Membership Type,Start Date,Expiry Date,Status\n" .
            "ZCONT-901,Nabila,Rahman,nabila.rahman@example.com,01711998877,1995-07-12,Female,45,2,Dhanmondi,1209,Dhaka,Dhaka,1 Year,2024-01-01,2025-01-01,Active\n" .
            "ZCONT-902,Tanvir,Ahmed,tanvir.ahmed@example.com,01822334411,1993-03-22,Male,12,8,Gulshan,1212,Dhaka,Dhaka,1 Year,2024-02-01,2025-02-01,Active\n";
        file_put_contents($csvPath, $content);

        // Record counts before simulation
        $membersBefore = DB::table('members')->count();
        $leadsBefore = DB::table('leads')->count();
        $usersBefore = DB::table('users')->count();
        $batchesBefore = DB::table('zoho_import_batches')->count();
        $stagingBefore = DB::table('zoho_staging_records')->count();
        $auditBefore = DB::table('audit_logs')->count();

        $report = $this->dryRunEngine->executeDryRun(
            filePath: $csvPath,
            workspaceId: $this->workspaceId,
            sourceType: 'contacts_members'
        );

        // Verify simulation report metrics
        $this->assertTrue($report['rolled_back']);
        $this->assertEquals(0, $report['database_mutations_persisted']);
        $this->assertEquals(2, $report['staged_count']);
        $this->assertEquals(2, $report['transformed_count']);
        $this->assertEquals(0, $report['failed_count']);
        $this->assertEquals('reconciled', $report['reconciliation_status']);
        $this->assertEquals(100.0, $report['reconciliation_match_rate']);

        // Assert database state is completely unchanged (zero writes persisted per T-11-10)
        $this->assertEquals($membersBefore, DB::table('members')->count());
        $this->assertEquals($leadsBefore, DB::table('leads')->count());
        $this->assertEquals($usersBefore, DB::table('users')->count());
        $this->assertEquals($batchesBefore, DB::table('zoho_import_batches')->count());
        $this->assertEquals($stagingBefore, DB::table('zoho_staging_records')->count());
        $this->assertEquals($auditBefore, DB::table('audit_logs')->count());

        $this->assertNull(Member::where('zoho_id', 'ZCONT-901')->first());
        $this->assertNull(User::where('email', 'nabila.rahman@example.com')->first());
    }

    public function test_dry_run_engine_detects_validation_errors_without_persisting(): void
    {
        $csvPath = $this->tempDir . '/invalid_contacts.csv';
        // Missing required First Name and invalid email
        $content = "CONTACTID,First Name,Last Name,Email,Mobile,Date of Birth,Gender,House No,Road No,Area,Postal Code,Division,District,Membership Type,Start Date,Expiry Date,Status\n" .
            "ZCONT-ERR1,,Hassan,not-an-email,01700000000,1990-01-01,Male,1,2,Area,1200,Dhaka,Dhaka,1 Year,2024-01-01,2025-01-01,Active\n";
        file_put_contents($csvPath, $content);

        $report = $this->dryRunEngine->executeDryRun(
            filePath: $csvPath,
            workspaceId: $this->workspaceId,
            sourceType: 'contacts_members'
        );

        $this->assertTrue($report['rolled_back']);
        $this->assertEquals(1, $report['failed_count']);
        $this->assertNotEmpty($report['validation_errors']);

        // Database still unchanged
        $this->assertNull(Member::where('zoho_id', 'ZCONT-ERR1')->first());
    }

    public function test_portal_account_provisioning_stages_inactive_passwordless_accounts(): void
    {
        // Setup existing Zoho-imported member lacking a portal user
        $lead = \App\Domain\Leads\Models\Lead::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => $this->workspaceId,
            'target_space_code' => 'EMK-DHK',
            'status' => 'converted',
            'bkash_txid' => 'TXBKASH999',
            'first_name' => 'Farhana',
            'last_name' => 'Sultana',
            'email' => 'farhana.sultana@example.com',
            'contact_number' => '+8801711002233',
            'date_of_birth' => '1996-08-14',
            'age_at_submission' => 30,
            'sex' => 'female',
            'address_house_no' => '12',
            'address_area' => 'Mohakhali',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'researcher',
            'institution_study' => 'BRAC University',
            'highest_certification' => 'masters_degree',
            'area_of_subject' => 'Public Health',
            'terms_agreed' => true,
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'submitted_at' => now(),
            'zoho_id' => 'ZOHO-LEAD-888',
        ]);

        $term = \App\Domain\Forms\Models\MembershipTerm::where('workspace_id', $this->workspaceId)->first();

        $member = Member::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'member_id' => 'EMK-2026-99999',
            'workspace_id' => $this->workspaceId,
            'lead_id' => $lead->id,
            'user_id' => null, // Unprovisioned portal user
            'membership_term_id' => $term->id,
            'status' => 'active',
            'first_name' => 'Farhana',
            'last_name' => 'Sultana',
            'email' => 'farhana.sultana@example.com',
            'contact_number' => '+8801711002233',
            'date_of_birth' => '1996-08-14',
            'sex' => 'female',
            'address_house_no' => '12',
            'address_area' => 'Mohakhali',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'researcher',
            'institution_study' => 'BRAC University',
            'highest_certification' => 'masters_degree',
            'area_of_subject' => 'Public Health',
            'start_date' => '2024-01-01',
            'expiry_date' => '2025-01-01',
            'zoho_id' => 'ZCONT-888',
        ]);

        $result = $this->provisioningService->provisionInactiveAccounts($this->workspaceId);

        $this->assertEquals(1, $result['staged_count']);
        $this->assertContains((int) $member->id, $result['member_ids']);

        // Assert User properties per DEC-06 (T-11-11)
        $member->refresh();
        $this->assertNotNull($member->user_id);
        $user = User::find($member->user_id);

        $this->assertNotNull($user);
        $this->assertEquals('member', $user->guard);
        $this->assertFalse($user->is_active);
        $this->assertTrue($user->must_reset_password);
        $this->assertNull($user->password);
        $this->assertEquals('ZCONT-888', $user->zoho_id);
        $this->assertEquals('farhana.sultana@example.com', $user->email);

        // Assert audit log entry created per DEC-11
        $auditLog = AuditLog::where('event_type', AuditLog::EVENT_PORTAL_ACCOUNT_STAGED_INACTIVE)
            ->where('auditable_id', $user->id)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals($this->workspaceId, $auditLog->workspace_id);

        // Assert account cannot authenticate
        $cannotLogin = $this->provisioningService->assertAccountCannotLogin($user);
        $this->assertTrue($cannotLogin);

        // Double check authentication rejection under member guard
        $attempt = Auth::guard('member')->attempt([
            'email' => $user->email,
            'password' => 'AnyPassword123!',
            'is_active' => true,
        ]);
        $this->assertFalse($attempt);
    }

    public function test_artisan_zoho_dry_run_command_executes_successfully(): void
    {
        $csvPath = $this->tempDir . '/dry_run_cmd.csv';
        $content = "CONTACTID,First Name,Last Name,Email,Mobile,Date of Birth,Gender,House No,Road No,Area,Postal Code,Division,District,Membership Type,Start Date,Expiry Date,Status\n" .
            "ZCONT-CMD1,Sadia,Islam,sadia.cmd@example.com,01733445566,1994-09-18,Female,10,4,Uttara,1230,Dhaka,Dhaka,1 Year,2024-01-01,2025-01-01,Active\n";
        file_put_contents($csvPath, $content);

        $this->artisan('zoho:dry-run', [
            'file' => $csvPath,
            '--workspace' => $this->workspaceId,
            '--type' => 'contacts_members',
        ])
            ->expectsOutputToContain('EMK Portal - Transactional Zoho Migration Dry-Run')
            ->expectsOutputToContain('Atomic DB::beginTransaction() with mandatory DB::rollBack()')
            ->expectsOutputToContain('CONFIRMED (Zero writes)')
            ->assertExitCode(0);
    }

    public function test_artisan_zoho_provision_portal_accounts_command_executes_successfully(): void
    {
        $this->artisan('zoho:provision-portal-accounts', [
            '--workspace' => $this->workspaceId,
        ])
            ->expectsOutputToContain('EMK Portal - Inactive Portal Account Provisioning Tool')
            ->expectsOutputToContain('guard=member, is_active=false, password=null (DEC-06)')
            ->assertExitCode(0);
    }

    public function test_hub_delta_export_service_extracts_records_submitted_after_cutoff(): void
    {
        $cutoff = \Carbon\Carbon::parse('2026-11-05 10:00:00');

        // Lead 1: Submitted before cutoff (should NOT be exported)
        \App\Domain\Leads\Models\Lead::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => $this->workspaceId,
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'TXBEFORE01',
            'first_name' => 'Old',
            'last_name' => 'Lead',
            'email' => 'old.lead@example.com',
            'contact_number' => '+8801700000001',
            'date_of_birth' => '1995-01-01',
            'age_at_submission' => 31,
            'sex' => 'male',
            'address_house_no' => '1',
            'address_area' => 'Mirpur',
            'address_zip_code' => '1216',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'DU',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Arts',
            'submitted_at' => \Carbon\Carbon::parse('2026-11-05 08:00:00'),
        ]);

        // Lead 2: Submitted after cutoff (MUST be exported)
        \App\Domain\Leads\Models\Lead::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => $this->workspaceId,
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'TXAFTER001',
            'first_name' => 'New',
            'last_name' => 'Applicant',
            'email' => 'new.applicant@example.com',
            'contact_number' => '+8801700000002',
            'date_of_birth' => '1998-05-12',
            'age_at_submission' => 28,
            'sex' => 'female',
            'address_house_no' => '42',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'engineer',
            'institution_study' => 'BUET',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'CSE',
            'submitted_at' => \Carbon\Carbon::parse('2026-11-05 12:30:00'),
        ]);

        /** @var \App\Domain\Migration\Services\HubDeltaExportService $exportService */
        $exportService = app(\App\Domain\Migration\Services\HubDeltaExportService::class);
        $csv = $exportService->exportDeltaSince($cutoff, $this->workspaceId);

        // Verify CSV header
        $this->assertStringContainsString('"Lead UUID","Zoho ID","First Name"', $csv);

        // Verify new applicant is in CSV, old lead is excluded
        $this->assertStringContainsString('new.applicant@example.com', $csv);
        $this->assertStringContainsString('TXAFTER001', $csv);
        $this->assertStringNotContainsString('old.lead@example.com', $csv);
        $this->assertStringNotContainsString('TXBEFORE01', $csv);

        // Verify audit log entry created per DEC-11
        $auditLog = AuditLog::where('event_type', AuditLog::EVENT_HUB_DELTA_EXPORTED)
            ->where('workspace_id', $this->workspaceId)
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals(1, $auditLog->new_values['exported_records_count']);
    }

    public function test_hub_delta_export_service_sanitizes_csv_formula_injection(): void
    {
        /** @var \App\Domain\Migration\Services\HubDeltaExportService $exportService */
        $exportService = app(\App\Domain\Migration\Services\HubDeltaExportService::class);

        // Assert single quote prepended to formula injection triggers per T-11-12
        $this->assertEquals("'=SUM(A1:A10)", $exportService->sanitizeCell('=SUM(A1:A10)'));
        $this->assertEquals("'+123456789", $exportService->sanitizeCell('+123456789'));
        $this->assertEquals("'-100", $exportService->sanitizeCell('-100'));
        $this->assertEquals("'@attacker.com", $exportService->sanitizeCell('@attacker.com'));

        // Normal cells unaffected
        $this->assertEquals('John Doe', $exportService->sanitizeCell('John Doe'));
        $this->assertEquals('BKASH12345', $exportService->sanitizeCell('BKASH12345'));
    }

    public function test_artisan_zoho_export_hub_delta_command_writes_sanitized_csv_and_logs_audit(): void
    {
        $outputPath = $this->tempDir . '/delta_export.csv';

        $this->artisan('zoho:export-hub-delta', [
            '--since' => '2026-11-05 00:00:00',
            '--workspace' => $this->workspaceId,
            '--output' => $outputPath,
        ])
            ->expectsOutputToContain('EMK Portal - Cutover Delta Exporter & Rollback Tool')
            ->expectsOutputToContain('Formula injection sanitized (T-11-12)')
            ->assertExitCode(0);

        $this->assertFileExists($outputPath);
        $csvContent = file_get_contents($outputPath);
        $this->assertStringContainsString('"Lead UUID","Zoho ID","First Name"', $csvContent);
    }

    public function test_cutover_runbook_codifies_seven_steps_and_rollback_triggers(): void
    {
        $runbookPath = base_path('.planning/phases/11-zoho-migration-and-reconciliation/CUTOVER-RUNBOOK.md');
        $this->assertFileExists($runbookPath);

        $content = file_get_contents($runbookPath);

        // Assert 7 cutover steps codified per GAP-P1-06
        $this->assertStringContainsString('Step 1: Pre-Cutover Staging Dry Run', $content);
        $this->assertStringContainsString('Step 2: Staff Training Sign-Off', $content);
        $this->assertStringContainsString('Step 3: Form Freeze at Cutoff', $content);
        $this->assertStringContainsString('Step 4: Final Delta Ingestion', $content);
        $this->assertStringContainsString('Step 5: DNS & Web Server Switch', $content);
        $this->assertStringContainsString('Step 6: Queue & Worker Ignition', $content);
        $this->assertStringContainsString('Step 7: Live Production Smoke Test', $content);

        // Assert quantifiable rollback triggers TR-01 through TR-05 codified
        $this->assertStringContainsString('TR-01', $content);
        $this->assertStringContainsString('TR-02', $content);
        $this->assertStringContainsString('TR-03', $content);
        $this->assertStringContainsString('TR-04', $content);
        $this->assertStringContainsString('TR-05', $content);

        // Assert 30-minute emergency rollback sequence and delta export command
        $this->assertStringContainsString('30-Minute Emergency Rollback Sequence', $content);
        $this->assertStringContainsString('zoho:export-hub-delta', $content);
    }
}
