<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Members\Models\Member;
use App\Domain\Migration\DTOs\ReconciliationReportDTO;
use App\Domain\Migration\Models\ZohoImportBatch;
use App\Domain\Migration\Models\ZohoStagingRecord;
use App\Domain\Migration\Services\ZohoReconciliationService;
use App\Domain\Workspaces\Models\Workspace;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ZohoReconciliationReportTest extends TestCase
{
    use RefreshDatabase;

    protected ZohoReconciliationService $reconciliationService;
    protected string $workspaceId = 'ws-emk-dhaka';
    protected MembershipTerm $term;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->reconciliationService = app(ZohoReconciliationService::class);
        Storage::fake('private');

        $this->term = MembershipTerm::where('workspace_id', $this->workspaceId)->first()
            ?? MembershipTerm::create([
                'workspace_id' => $this->workspaceId,
                'name' => '1 Year General',
                'duration_months' => 12,
                'fee_bdt' => 1000.00,
                'is_active' => true,
            ]);
    }

    protected function createMemberWithLead(
        string $zohoId,
        string $email,
        string $status = 'active',
        string $dob = '1995-05-15',
        string $startDate = '2024-01-01',
        string $expiryDate = '2025-01-01'
    ): array {
        $user = User::where('email', $email)->first() ?? User::create([
            'workspace_id' => $this->workspaceId,
            'name' => 'Test User ' . $zohoId,
            'email' => $email,
            'password' => bcrypt('Secret123!'),
            'guard' => 'member',
            'is_active' => true,
            'zoho_id' => $zohoId,
        ]);

        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspaceId,
            'target_space_code' => 'EMK-DHK',
            'status' => 'converted',
            'bkash_txid' => 'TXID' . strtoupper(substr(md5($zohoId), 0, 6)),
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $email,
            'contact_number' => '+8801700000001',
            'date_of_birth' => $dob,
            'age_at_submission' => 28,
            'sex' => 'male',
            'address_house_no' => '12',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'DU',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'CS',
            'terms_agreed' => true,
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'zoho_id' => $zohoId,
            'submitted_at' => Carbon::parse($startDate),
        ]);

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => 'EMK-' . rand(1000, 9999),
            'workspace_id' => $this->workspaceId,
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'membership_term_id' => $this->term->id,
            'status' => $status,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $email,
            'contact_number' => '+8801700000001',
            'date_of_birth' => $dob,
            'sex' => 'male',
            'address_house_no' => '12',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'student',
            'institution_study' => 'DU',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'CS',
            'start_date' => $startDate,
            'expiry_date' => $expiryDate,
            'zoho_id' => $zohoId,
        ]);

        $lead->update(['converted_member_id' => $member->id]);

        return ['member' => $member, 'lead' => $lead, 'user' => $user];
    }

    public function test_reconciliation_on_100_percent_matching_dataset_returns_reconciled(): void
    {
        $batch = ZohoImportBatch::create([
            'batch_uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspaceId,
            'source_type' => 'contacts_members',
            'source_filename' => 'perfect_members.csv',
            'total_rows' => 2,
            'processed_rows' => 2,
            'successful_rows' => 2,
            'status' => 'completed',
        ]);

        $r1 = $this->createMemberWithLead('ZC-101', 'member1@example.com', 'active', '1995-01-01', '2024-01-01', '2025-01-01');
        $r2 = $this->createMemberWithLead('ZC-102', 'member2@example.com', 'active', '1996-02-02', '2024-02-01', '2025-02-01');

        ZohoStagingRecord::create([
            'batch_id' => $batch->id,
            'workspace_id' => $this->workspaceId,
            'zoho_id' => 'ZC-101',
            'source_type' => 'contacts_members',
            'raw_payload' => ['Status' => 'Active', 'Email' => 'member1@example.com'],
            'mapped_payload' => [
                'email' => 'member1@example.com',
                'status' => 'active',
                'date_of_birth' => '1995-01-01',
                'start_date' => '2024-01-01',
                'expiry_date' => '2025-01-01',
            ],
            'imported_entity_type' => Member::class,
            'imported_entity_id' => $r1['member']->id,
            'status' => 'imported',
        ]);

        ZohoStagingRecord::create([
            'batch_id' => $batch->id,
            'workspace_id' => $this->workspaceId,
            'zoho_id' => 'ZC-102',
            'source_type' => 'contacts_members',
            'raw_payload' => ['Status' => 'Active', 'Email' => 'member2@example.com'],
            'mapped_payload' => [
                'email' => 'member2@example.com',
                'status' => 'active',
                'date_of_birth' => '1996-02-02',
                'start_date' => '2024-02-01',
                'expiry_date' => '2025-02-01',
            ],
            'imported_entity_type' => Member::class,
            'imported_entity_id' => $r2['member']->id,
            'status' => 'imported',
        ]);

        $report = $this->reconciliationService->reconcileBatch($batch);

        $this->assertInstanceOf(ReconciliationReportDTO::class, $report);
        $this->assertTrue($report->isReconciled());
        $this->assertEquals(100.0, $report->matchPercentage());
        $this->assertEquals(0, $report->discrepancyCount);
        $this->assertEquals(2, $report->sourceRecordCount);
        $this->assertEquals(2, $report->importedRecordCount);
        $this->assertEquals(2, $report->sourceUniqueEmails);
        $this->assertEquals(2, $report->importedUniqueEmails);
        $this->assertEmpty($report->duplicateEmails);

        // Verify DEC-11 immutable audit log creation
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $this->workspaceId,
            'event_type' => AuditLog::EVENT_ZOHO_RECONCILIATION_RUN,
            'auditable_type' => ZohoImportBatch::class,
            'auditable_id' => $batch->id,
        ]);
    }

    public function test_detection_of_count_mismatch_when_records_are_omitted(): void
    {
        $batch = ZohoImportBatch::create([
            'batch_uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspaceId,
            'source_type' => 'contacts_members',
            'source_filename' => 'missing_records.csv',
            'total_rows' => 2,
            'processed_rows' => 2,
            'status' => 'completed',
        ]);

        $r1 = $this->createMemberWithLead('ZC-201', 'imported@example.com');

        ZohoStagingRecord::create([
            'batch_id' => $batch->id,
            'workspace_id' => $this->workspaceId,
            'zoho_id' => 'ZC-201',
            'source_type' => 'contacts_members',
            'raw_payload' => ['Email' => 'imported@example.com'],
            'mapped_payload' => ['email' => 'imported@example.com', 'status' => 'active'],
            'imported_entity_type' => Member::class,
            'imported_entity_id' => $r1['member']->id,
            'status' => 'imported',
        ]);

        // Staging record for record that was never persisted
        ZohoStagingRecord::create([
            'batch_id' => $batch->id,
            'workspace_id' => $this->workspaceId,
            'zoho_id' => 'ZC-202_OMITTED',
            'source_type' => 'contacts_members',
            'raw_payload' => ['Email' => 'omitted@example.com'],
            'mapped_payload' => ['email' => 'omitted@example.com', 'status' => 'active'],
            'status' => 'failed',
        ]);

        $report = $this->reconciliationService->reconcileBatch($batch);

        $this->assertFalse($report->isReconciled());
        $this->assertLessThan(100.0, $report->matchPercentage());
        $this->assertEquals(1, $report->discrepancyCount);
        $this->assertEquals(2, $report->sourceRecordCount);
        $this->assertEquals(1, $report->importedRecordCount);

        $discrepancy = $report->discrepancies[0];
        $this->assertEquals('ZC-202_OMITTED', $discrepancy['zoho_id']);
        $this->assertEquals('existence', $discrepancy['field']);
        $this->assertEquals('missing', $discrepancy['actual']);
        $this->assertEquals('critical', $discrepancy['severity']);
    }

    public function test_detection_of_email_duplication_and_formatting_divergence(): void
    {
        $batch = ZohoImportBatch::create([
            'batch_uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspaceId,
            'source_type' => 'contacts_members',
            'source_filename' => 'email_issues.csv',
            'total_rows' => 3,
            'status' => 'completed',
        ]);

        $r1 = $this->createMemberWithLead('ZC-301', 'duplicate@example.com');
        $r3 = $this->createMemberWithLead('ZC-303', 'user303@example.com');

        ZohoStagingRecord::create([
            'batch_id' => $batch->id,
            'workspace_id' => $this->workspaceId,
            'zoho_id' => 'ZC-301',
            'source_type' => 'contacts_members',
            'raw_payload' => ['Email' => 'duplicate@example.com'],
            'mapped_payload' => ['email' => 'duplicate@example.com', 'status' => 'active'],
            'imported_entity_type' => Member::class,
            'imported_entity_id' => $r1['member']->id,
            'status' => 'imported',
        ]);

        // Duplicate email in source that was skipped or failed
        ZohoStagingRecord::create([
            'batch_id' => $batch->id,
            'workspace_id' => $this->workspaceId,
            'zoho_id' => 'ZC-302',
            'source_type' => 'contacts_members',
            'raw_payload' => ['Email' => 'duplicate@example.com'],
            'mapped_payload' => ['email' => 'duplicate@example.com', 'status' => 'active'],
            'status' => 'skipped',
        ]);

        ZohoStagingRecord::create([
            'batch_id' => $batch->id,
            'workspace_id' => $this->workspaceId,
            'zoho_id' => 'ZC-303',
            'source_type' => 'contacts_members',
            'raw_payload' => ['Email' => 'invalid-email-format'],
            'mapped_payload' => ['email' => 'invalid-email-format', 'status' => 'active'],
            'imported_entity_type' => Member::class,
            'imported_entity_id' => $r3['member']->id,
            'status' => 'imported',
        ]);

        $report = $this->reconciliationService->reconcileBatch($batch);

        $this->assertFalse($report->isReconciled());
        $this->assertContains('duplicate@example.com', $report->duplicateEmails);

        $fields = array_column($report->discrepancies, 'field');
        $this->assertContains('email', $fields);

        // Verify severity rating
        $criticals = array_filter($report->discrepancies, fn ($d) => $d['severity'] === 'critical');
        $this->assertNotEmpty($criticals);
    }

    public function test_detection_of_date_discrepancies(): void
    {
        $batch = ZohoImportBatch::create([
            'batch_uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspaceId,
            'source_type' => 'contacts_members',
            'source_filename' => 'date_divergence.csv',
            'total_rows' => 1,
            'status' => 'completed',
        ]);

        // Member has DOB 1995-01-01
        $r1 = $this->createMemberWithLead('ZC-401', 'user401@example.com', 'active', '1995-01-01');

        // Staging record expects DOB 1990-10-10
        ZohoStagingRecord::create([
            'batch_id' => $batch->id,
            'workspace_id' => $this->workspaceId,
            'zoho_id' => 'ZC-401',
            'source_type' => 'contacts_members',
            'raw_payload' => ['Email' => 'user401@example.com'],
            'mapped_payload' => [
                'email' => 'user401@example.com',
                'status' => 'active',
                'date_of_birth' => '1990-10-10',
                'start_date' => '2024-01-01',
                'expiry_date' => '2025-01-01',
            ],
            'imported_entity_type' => Member::class,
            'imported_entity_id' => $r1['member']->id,
            'status' => 'imported',
        ]);

        $report = $this->reconciliationService->reconcileBatch($batch);

        $this->assertFalse($report->isReconciled());
        $this->assertEquals(1, $report->dateMismatchCount);

        $dateDiscrepancy = collect($report->discrepancies)->firstWhere('field', 'date_of_birth');
        $this->assertNotNull($dateDiscrepancy);
        $this->assertEquals('1990-10-10', $dateDiscrepancy['expected']);
        $this->assertEquals('1995-01-01', $dateDiscrepancy['actual']);
        $this->assertEquals('warning', $dateDiscrepancy['severity']);
    }

    public function test_detection_of_missing_attachments_on_private_disk(): void
    {
        $batch = ZohoImportBatch::create([
            'batch_uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspaceId,
            'source_type' => 'contacts_members',
            'source_filename' => 'attachments_check.csv',
            'total_rows' => 1,
            'status' => 'completed',
        ]);

        $r1 = $this->createMemberWithLead('ZC-501', 'user501@example.com');

        $storagePath = 'documents/ws-emk-dhaka/leads/' . $r1['lead']->uuid . '/doc123.pdf';

        LeadAttachment::create([
            'workspace_id' => $this->workspaceId,
            'lead_id' => $r1['lead']->id,
            'document_type' => 'identity_document',
            'storage_path' => $storagePath,
            'original_filename' => 'passport.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'file_hash' => hash('sha256', 'sample'),
            'youth_protection' => false,
        ]);

        ZohoStagingRecord::create([
            'batch_id' => $batch->id,
            'workspace_id' => $this->workspaceId,
            'zoho_id' => 'ZC-501',
            'source_type' => 'contacts_members',
            'raw_payload' => ['Email' => 'user501@example.com'],
            'mapped_payload' => [
                'email' => 'user501@example.com',
                'status' => 'active',
                'date_of_birth' => '1995-05-15',
                'start_date' => '2024-01-01',
                'expiry_date' => '2025-01-01',
            ],
            'imported_entity_type' => Member::class,
            'imported_entity_id' => $r1['member']->id,
            'status' => 'imported',
        ]);

        // File is NOT placed on private disk
        $report = $this->reconciliationService->reconcileBatch($batch);

        $this->assertFalse($report->isReconciled());
        $this->assertEquals(1, $report->expectedAttachments);
        $this->assertEquals(0, $report->actualAttachments);
        $this->assertEquals(1, $report->missingAttachments);

        $attDiscrepancy = collect($report->discrepancies)->firstWhere('field', 'attachment');
        $this->assertNotNull($attDiscrepancy);
        $this->assertEquals('critical', $attDiscrepancy['severity']);
        $this->assertStringContainsString('missing from private storage', $attDiscrepancy['message']);

        // Now place the file on the private disk and re-test
        Storage::disk('private')->put($storagePath, 'Fake PDF content');

        $reportResolved = $this->reconciliationService->reconcileBatch($batch);
        $this->assertEquals(1, $reportResolved->expectedAttachments);
        $this->assertEquals(1, $reportResolved->actualAttachments);
        $this->assertEquals(0, $reportResolved->missingAttachments);
        $this->assertTrue($reportResolved->isReconciled());
    }

    public function test_workspace_level_reconciliation_evaluates_all_workspace_records(): void
    {
        $batch = ZohoImportBatch::create([
            'batch_uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspaceId,
            'source_type' => 'contacts_members',
            'source_filename' => 'workspace_all.csv',
            'total_rows' => 2,
            'status' => 'completed',
        ]);

        $r1 = $this->createMemberWithLead('ZC-601', 'ws1@example.com');
        $r2 = $this->createMemberWithLead('ZC-602', 'ws2@example.com');

        ZohoStagingRecord::create([
            'batch_id' => $batch->id,
            'workspace_id' => $this->workspaceId,
            'zoho_id' => 'ZC-601',
            'source_type' => 'contacts_members',
            'raw_payload' => ['Email' => 'ws1@example.com'],
            'mapped_payload' => [
                'email' => 'ws1@example.com',
                'status' => 'active',
                'date_of_birth' => '1995-05-15',
                'start_date' => '2024-01-01',
                'expiry_date' => '2025-01-01',
            ],
            'imported_entity_type' => Member::class,
            'imported_entity_id' => $r1['member']->id,
            'status' => 'imported',
        ]);

        ZohoStagingRecord::create([
            'batch_id' => $batch->id,
            'workspace_id' => $this->workspaceId,
            'zoho_id' => 'ZC-602',
            'source_type' => 'contacts_members',
            'raw_payload' => ['Email' => 'ws2@example.com'],
            'mapped_payload' => [
                'email' => 'ws2@example.com',
                'status' => 'active',
                'date_of_birth' => '1995-05-15',
                'start_date' => '2024-01-01',
                'expiry_date' => '2025-01-01',
            ],
            'imported_entity_type' => Member::class,
            'imported_entity_id' => $r2['member']->id,
            'status' => 'imported',
        ]);

        $report = $this->reconciliationService->reconcileWorkspace($this->workspaceId);

        $this->assertTrue($report->isReconciled());
        $this->assertEquals(2, $report->sourceRecordCount);
        $this->assertEquals(2, $report->importedRecordCount);
        $this->assertEquals('workspace-' . $this->workspaceId, $report->batchUuid);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => $this->workspaceId,
            'event_type' => AuditLog::EVENT_ZOHO_RECONCILIATION_RUN,
            'auditable_type' => Workspace::class,
        ]);
    }
}
