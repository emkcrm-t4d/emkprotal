<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Members\Models\Member;
use App\Domain\Migration\Commands\ZohoImportAttachmentsCommand;
use App\Domain\Migration\Models\ZohoImportBatch;
use App\Domain\Migration\Models\ZohoStagingRecord;
use App\Domain\Migration\Services\HubDeltaExportService;
use App\Domain\Migration\Services\PortalAccountProvisioningService;
use App\Domain\Migration\Services\ReconciliationReportGenerator;
use App\Domain\Migration\Services\ZohoAttachmentMigrationService;
use App\Domain\Migration\Services\ZohoDataIngestionService;
use App\Domain\Migration\Services\ZohoDryRunEngine;
use App\Domain\Migration\Services\ZohoFieldMappingService;
use App\Domain\Migration\Services\ZohoReconciliationService;
use App\Domain\Workspaces\Models\Workspace;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

class EndToEndZohoMigrationAndReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected ZohoDryRunEngine $dryRunEngine;
    protected ZohoDataIngestionService $ingestionService;
    protected ZohoFieldMappingService $mappingService;
    protected ZohoAttachmentMigrationService $attachmentService;
    protected ZohoReconciliationService $reconciliationService;
    protected ReconciliationReportGenerator $reportGenerator;
    protected PortalAccountProvisioningService $provisioningService;
    protected HubDeltaExportService $deltaExportService;

    protected string $workspaceId = 'ws-emk-dhaka';
    protected MembershipTerm $term;
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->dryRunEngine = app(ZohoDryRunEngine::class);
        $this->ingestionService = app(ZohoDataIngestionService::class);
        $this->mappingService = app(ZohoFieldMappingService::class);
        $this->attachmentService = app(ZohoAttachmentMigrationService::class);
        $this->reconciliationService = app(ZohoReconciliationService::class);
        $this->reportGenerator = app(ReconciliationReportGenerator::class);
        $this->provisioningService = app(PortalAccountProvisioningService::class);
        $this->deltaExportService = app(HubDeltaExportService::class);

        Storage::fake('private');

        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'zoho_e2e_' . uniqid();
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }

        $this->term = MembershipTerm::where('workspace_id', $this->workspaceId)->first()
            ?? MembershipTerm::create([
                'workspace_id' => $this->workspaceId,
                'name' => '1 Year General',
                'duration_months' => 12,
                'fee_bdt' => 1000.00,
                'is_active' => true,
            ]);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }

        parent::tearDown();
    }

    /**
     * Create a realistic minimal 10x10 JPEG image file.
     */
    protected function createSampleJpeg(string $filename): string
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . $filename;
        $im = imagecreatetruecolor(10, 10);
        $bg = imagecolorallocate($im, 0, 120, 200);
        imagefilledrectangle($im, 0, 0, 9, 9, $bg);
        imagejpeg($im, $path, 90);
        imagedestroy($im);

        return $path;
    }

    /**
     * Create a realistic minimal valid PDF document file.
     */
    protected function createSamplePdf(string $filename, string $content = 'Identity Document Verification'): string
    {
        $path = $this->tempDir . DIRECTORY_SEPARATOR . $filename;
        $pdfContent = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n" .
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n" .
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n" .
            "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream\nendobj\n" .
            "xref\n0 5\n0000000000 65535 f \n0000000010 00000 n \n0000000060 00000 n \n0000000117 00000 n \n0000000188 00000 n \n" .
            "trailer\n<< /Size 5 /Root 1 0 R >>\nstartxref\n300\n%%EOF";

        file_put_contents($path, $pdfContent);

        return $path;
    }

    /**
     * Generate contacts CSV payload (50 records).
     */
    protected function generateContactsCsv(int $count = 50): string
    {
        $csvPath = $this->tempDir . DIRECTORY_SEPARATOR . 'contacts_50.csv';
        $handle = fopen($csvPath, 'w');

        fputcsv($handle, [
            'CONTACTID',
            'First Name',
            'Last Name',
            'Email',
            'Mobile',
            'Date of Birth',
            'Gender',
            'House No',
            'Road No',
            'Area',
            'Postal Code',
            'Division',
            'District',
            'Membership Type',
            'Start Date',
            'Expiry Date',
            'Status',
            'Occupation',
            'Institution',
            'Highest Certification',
            'Area of Subject',
        ]);

        for ($i = 1; $i <= $count; $i++) {
            $num = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            // Minor (< 18 in 2026) for record 1 and 2
            $dob = ($i <= 2) ? '2010-06-15' : '1995-04-10';
            $gender = ($i % 2 === 0) ? 'Female' : 'Male';

            fputcsv($handle, [
                "ZCONT-{$num}",
                "MemberFirst{$num}",
                "MemberLast{$num}",
                "member{$num}@example.com",
                "01710000{$num}",
                $dob,
                $gender,
                "House {$i}",
                "Road {$i}",
                'Dhanmondi',
                '1209',
                'Dhaka',
                'Dhaka',
                '1 Year Membership',
                '2024-01-01',
                '2025-01-01',
                'Active',
                'Student',
                'Dhaka University',
                'Undergraduate Degree',
                'Computer Science',
            ]);
        }

        fclose($handle);

        return $csvPath;
    }

    /**
     * Generate leads CSV payload (20 records).
     */
    protected function generateLeadsCsv(int $count = 20): string
    {
        $csvPath = $this->tempDir . DIRECTORY_SEPARATOR . 'leads_20.csv';
        $handle = fopen($csvPath, 'w');

        fputcsv($handle, [
            'LEADID',
            'First Name',
            'Last Name',
            'Email',
            'Phone',
            'DOB',
            'Gender',
            'bKash Transaction ID',
            'Status',
            'House No',
            'Area',
            'Postal Code',
            'Division',
            'District',
            'Occupation',
            'Institution',
            'Highest Certification',
            'Area of Subject',
        ]);

        for ($i = 1; $i <= $count; $i++) {
            $num = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            // Minor for lead 1
            $dob = ($i === 1) ? '2011-03-20' : '1998-05-12';
            $gender = ($i % 2 === 0) ? 'Female' : 'Male';
            $status = ($i % 2 === 0) ? 'In Review' : 'Submitted';

            fputcsv($handle, [
                "ZLEAD-{$num}",
                "LeadFirst{$num}",
                "LeadLast{$num}",
                "lead{$num}@example.com",
                "01810000{$num}",
                $dob,
                $gender,
                "bk7x01a{$num}", // Lowercase to verify 10-char uppercase normalization
                $status,
                "Block {$i}",
                'Uttara',
                '1230',
                'Dhaka',
                'Dhaka',
                'Artist',
                'Fine Arts',
                'Bachelor of Fine Arts',
                'Visual Art',
            ]);
        }

        fclose($handle);

        return $csvPath;
    }

    /**
     * Holistic Phase 11 simulation exercising all 7 operational scenarios in sequence.
     */
    public function test_holistic_phase_11_migration_reconciliation_and_cutover_simulation(): void
    {
        // =========================================================================
        // SCENARIO 1: Dry-Run Verification (MIGR-05, T-11-10)
        // =========================================================================
        $dryRunContactsCsv = $this->generateContactsCsv(5);
        $dryRunLeadsCsv = $this->generateLeadsCsv(3);

        $tablesToMonitor = [
            'users',
            'leads',
            'lead_attachments',
            'members',
            'invoices',
            'id_cards',
            'zoho_import_batches',
            'zoho_staging_records',
            'audit_logs',
        ];

        $countsBefore = [];
        foreach ($tablesToMonitor as $table) {
            $countsBefore[$table] = DB::table($table)->count();
        }

        $dryRunReportContacts = $this->dryRunEngine->executeDryRun(
            filePath: $dryRunContactsCsv,
            workspaceId: $this->workspaceId,
            sourceType: 'contacts_members'
        );

        $dryRunReportLeads = $this->dryRunEngine->executeDryRun(
            filePath: $dryRunLeadsCsv,
            workspaceId: $this->workspaceId,
            sourceType: 'leads'
        );

        // Assert dry run reports confirm rollback and zero database mutations
        $this->assertTrue($dryRunReportContacts['rolled_back']);
        $this->assertEquals(0, $dryRunReportContacts['database_mutations_persisted']);
        $this->assertEquals(5, $dryRunReportContacts['staged_count']);
        $this->assertEquals(5, $dryRunReportContacts['transformed_count']);
        $this->assertEquals(0, $dryRunReportContacts['failed_count']);
        $this->assertEquals('reconciled', $dryRunReportContacts['reconciliation_status']);
        $this->assertEquals(100.0, $dryRunReportContacts['reconciliation_match_rate']);

        $this->assertTrue($dryRunReportLeads['rolled_back']);
        $this->assertEquals(0, $dryRunReportLeads['database_mutations_persisted']);
        $this->assertEquals(3, $dryRunReportLeads['staged_count']);
        $this->assertEquals(3, $dryRunReportLeads['transformed_count']);
        $this->assertEquals(0, $dryRunReportLeads['failed_count']);
        $this->assertEquals('reconciled', $dryRunReportLeads['reconciliation_status']);
        $this->assertEquals(100.0, $dryRunReportLeads['reconciliation_match_rate']);

        // Assert database state is completely unchanged (zero writes persisted)
        foreach ($tablesToMonitor as $table) {
            $this->assertEquals(
                $countsBefore[$table],
                DB::table($table)->count(),
                "Table [{$table}] was mutated during dry-run simulation."
            );
        }

        // =========================================================================
        // SCENARIO 2: Live Ingestion & Field Mapping (MIGR-01, MIGR-02)
        // =========================================================================
        $liveContactsCsv = $this->generateContactsCsv(50);
        $liveLeadsCsv = $this->generateLeadsCsv(20);

        $batchContacts = $this->ingestionService->ingestFile(
            filePath: $liveContactsCsv,
            workspaceId: $this->workspaceId,
            sourceType: 'contacts_members',
            chunkSize: 15
        );

        $batchLeads = $this->ingestionService->ingestFile(
            filePath: $liveLeadsCsv,
            workspaceId: $this->workspaceId,
            sourceType: 'leads',
            chunkSize: 10
        );

        $this->assertEquals('completed', $batchContacts->status);
        $this->assertEquals(50, $batchContacts->total_rows);
        $this->assertEquals(50, $batchContacts->successful_rows);
        $this->assertEquals(0, $batchContacts->failed_rows);

        $this->assertEquals('completed', $batchLeads->status);
        $this->assertEquals(20, $batchLeads->total_rows);
        $this->assertEquals(20, $batchLeads->successful_rows);
        $this->assertEquals(0, $batchLeads->failed_rows);

        // Assert staging records record all 70 rows with status imported
        $this->assertEquals(70, ZohoStagingRecord::where('workspace_id', $this->workspaceId)->where('status', 'imported')->count());

        // Assert 50 rows created in members table
        $this->assertEquals(50, Member::where('workspace_id', $this->workspaceId)->count());

        // Assert leads table contains 70 rows (50 converted leads associated with members + 20 uncoverted leads)
        $this->assertEquals(70, Lead::where('workspace_id', $this->workspaceId)->count());
        $this->assertEquals(50, Lead::where('workspace_id', $this->workspaceId)->where('status', 'converted')->count());
        $this->assertEquals(20, Lead::where('workspace_id', $this->workspaceId)->whereIn('status', ['submitted', 'under_review'])->count());

        // Assert zoho_id is populated on every record and indexed
        $membersWithoutZohoId = Member::where('workspace_id', $this->workspaceId)->whereNull('zoho_id')->count();
        $this->assertEquals(0, $membersWithoutZohoId);

        $leadsWithoutZohoId = Lead::where('workspace_id', $this->workspaceId)->whereNull('zoho_id')->count();
        $this->assertEquals(0, $leadsWithoutZohoId);

        // Assert field mappings fidelity
        $sampleMember = Member::where('zoho_id', 'ZCONT-001')->first();
        $this->assertNotNull($sampleMember);
        $this->assertEquals('MemberFirst001', $sampleMember->first_name);
        $this->assertEquals('MemberLast001', $sampleMember->last_name);
        $this->assertEquals('member001@example.com', $sampleMember->email);
        $this->assertEquals('2010-06-15', $sampleMember->date_of_birth ? $sampleMember->date_of_birth->format('Y-m-d') : null);
        $this->assertEquals('male', $sampleMember->sex);
        $this->assertEquals('active', $sampleMember->status);
        $this->assertEquals('2024-01-01', $sampleMember->start_date ? $sampleMember->start_date->format('Y-m-d') : null);
        $this->assertEquals('2025-01-01', $sampleMember->expiry_date ? $sampleMember->expiry_date->format('Y-m-d') : null);

        $sampleLead = Lead::where('zoho_id', 'ZLEAD-001')->first();
        $this->assertNotNull($sampleLead);
        $this->assertEquals('BK7X01A001', $sampleLead->bkash_txid); // 10-char uppercase normalized per DEC-05
        $this->assertEquals('submitted', $sampleLead->status);
        $this->assertEquals('male', $sampleLead->sex);
        $this->assertEquals('2011-03-20', $sampleLead->date_of_birth ? $sampleLead->date_of_birth->format('Y-m-d') : null);

        // =========================================================================
        // SCENARIO 3: Attachment Migration & Secure Storage (MIGR-03)
        // =========================================================================
        $attachmentsDir = $this->tempDir . DIRECTORY_SEPARATOR . 'attachments';
        if (!is_dir($attachmentsDir)) {
            mkdir($attachmentsDir, 0777, true);
        }

        // Prepare sample files
        $pdfFile1 = $this->createSamplePdf('zcont_001_id.pdf', 'Minor Applicant Passport Scan');
        $jpgFile1 = $this->createSampleJpeg('zcont_001_photo.jpg');
        $pdfFile2 = $this->createSamplePdf('zlead_001_id.pdf', 'Minor Lead Identity Document');
        $jpgFile2 = $this->createSampleJpeg('zlead_002_photo.jpg');

        copy($pdfFile1, $attachmentsDir . DIRECTORY_SEPARATOR . 'zcont_001_id.pdf');
        copy($jpgFile1, $attachmentsDir . DIRECTORY_SEPARATOR . 'zcont_001_photo.jpg');
        copy($pdfFile2, $attachmentsDir . DIRECTORY_SEPARATOR . 'zlead_001_id.pdf');
        copy($jpgFile2, $attachmentsDir . DIRECTORY_SEPARATOR . 'zlead_002_photo.jpg');

        $manifestCsv = $this->tempDir . DIRECTORY_SEPARATOR . 'attachments_manifest.csv';
        $mHandle = fopen($manifestCsv, 'w');
        fputcsv($mHandle, ['zoho_id', 'filename', 'document_type']);
        fputcsv($mHandle, ['ZCONT-001', 'zcont_001_id.pdf', 'identity_document']);
        fputcsv($mHandle, ['ZCONT-001', 'zcont_001_photo.jpg', 'studio_photo']);
        fputcsv($mHandle, ['ZLEAD-001', 'zlead_001_id.pdf', 'identity_document']);
        fputcsv($mHandle, ['ZLEAD-002', 'zlead_002_photo.jpg', 'studio_photo']);
        fclose($mHandle);

        // Execute batch import command
        $this->artisan('zoho:import-attachments', [
            'directory' => $attachmentsDir,
            '--manifest' => $manifestCsv,
            '--workspace' => $this->workspaceId,
        ])
            ->expectsOutputToContain('EMK Portal - Legacy Zoho Attachment Import Processor')
            ->assertExitCode(0);

        // Also test direct service invocation for adult record
        $adultJpg = $this->createSampleJpeg('zcont_003_photo.jpg');
        $directAttachment = $this->attachmentService->migrateAttachment(
            sourceFilePath: $adultJpg,
            zohoId: 'ZCONT-003',
            documentType: 'studio_photo',
            workspaceId: $this->workspaceId
        );
        $this->assertNotNull($directAttachment);

        // Assert files are stored with UUID v4 filenames on private disk
        $allAttachments = LeadAttachment::where('workspace_id', $this->workspaceId)->get();
        $this->assertEquals(5, $allAttachments->count());

        foreach ($allAttachments as $att) {
            $this->assertTrue(
                Storage::disk('private')->exists($att->storage_path),
                "Encrypted file [{$att->storage_path}] missing on private disk."
            );

            $fileUuid = pathinfo($att->storage_path, PATHINFO_FILENAME);
            $this->assertTrue(
                Uuid::isValid($fileUuid),
                "Physical filename [{$fileUuid}] is not a valid UUID v4."
            );

            // Assert file is encrypted with AES-256 and unreadable directly from disk without decryption
            $storedRaw = Storage::disk('private')->get($att->storage_path);
            $this->assertNotNull($storedRaw);
            $this->assertNotEmpty($storedRaw);

            $decrypted = Crypt::decryptString($storedRaw);
            $this->assertNotNull($decrypted);
            $this->assertEquals($att->file_hash, hash('sha256', $decrypted));
        }

        // Assert youth_protection = true is assigned to applicants < 18 years
        // ZCONT-001 (DOB 2010-06-15) and ZLEAD-001 (DOB 2011-03-20) are minors
        $minorMember = Member::where('zoho_id', 'ZCONT-001')->first();
        $minorLead = Lead::where('zoho_id', 'ZLEAD-001')->first();
        $adultMember = Member::where('zoho_id', 'ZCONT-003')->first();

        $minorMemberAttachments = LeadAttachment::where('lead_id', $minorMember->lead_id)->get();
        $this->assertGreaterThan(0, $minorMemberAttachments->count());
        foreach ($minorMemberAttachments as $att) {
            $this->assertTrue($att->youth_protection, 'Minor member attachment failed youth_protection assertion.');
        }

        $minorLeadAttachments = LeadAttachment::where('lead_id', $minorLead->id)->get();
        $this->assertGreaterThan(0, $minorLeadAttachments->count());
        foreach ($minorLeadAttachments as $att) {
            $this->assertTrue($att->youth_protection, 'Minor lead attachment failed youth_protection assertion.');
        }

        $adultMemberAttachments = LeadAttachment::where('lead_id', $adultMember->lead_id)->get();
        $this->assertGreaterThan(0, $adultMemberAttachments->count());
        foreach ($adultMemberAttachments as $att) {
            $this->assertFalse($att->youth_protection, 'Adult member attachment incorrectly assigned youth_protection.');
        }

        // =========================================================================
        // SCENARIO 4: Automated Reconciliation Reporting (MIGR-04)
        // =========================================================================
        $reconReport = $this->reconciliationService->reconcileWorkspace($this->workspaceId);

        // Assert 100% match rate and zero discrepancies
        $this->assertTrue($reconReport->isReconciled(), 'Reconciliation detected unexpected discrepancies.');
        $this->assertEquals(100.0, $reconReport->matchPercentage());
        $this->assertEquals(0, $reconReport->discrepancyCount);
        $this->assertEquals(70, $reconReport->sourceRecordCount);
        $this->assertEquals(70, $reconReport->importedRecordCount);
        $this->assertEquals(70, $reconReport->sourceUniqueEmails);
        $this->assertEquals(70, $reconReport->importedUniqueEmails);
        $this->assertEmpty($reconReport->duplicateEmails);
        $this->assertEquals(0, $reconReport->dateMismatchCount);
        $this->assertEquals(0, $reconReport->missingAttachments);
        $this->assertEquals(5, $reconReport->actualAttachments);

        // Generate CSV and HTML reports via ReconciliationReportGenerator
        $csvReport = $this->reportGenerator->toCsv($reconReport);
        $this->assertStringContainsString('zoho_id,entity,field,expected_value,actual_value,severity,message', $csvReport);

        $htmlReport = $this->reportGenerator->toHtml($reconReport);
        $this->assertStringContainsString('Reconciliation Audit Report', $htmlReport);
        $this->assertStringContainsString('100.00%', $htmlReport);
        $this->assertStringContainsString('Zero discrepancies detected', $htmlReport);
        $this->assertStringContainsString('ws-emk-dhaka', $htmlReport);

        // =========================================================================
        // SCENARIO 5: Inactive Member Portal Account Provisioning (MIGR-05, DEC-06)
        // =========================================================================
        $provisionResult = $this->provisioningService->provisionInactiveAccounts($this->workspaceId);

        $this->assertEquals(50, $provisionResult['staged_count']);
        $this->assertCount(50, $provisionResult['user_ids']);
        $this->assertCount(50, $provisionResult['member_ids']);

        // Assert user records created with guard='member', is_active=false, password=null
        $stagedUsers = User::whereIn('id', $provisionResult['user_ids'])->get();
        $this->assertEquals(50, $stagedUsers->count());

        foreach ($stagedUsers as $stagedUser) {
            $this->assertEquals('member', $stagedUser->guard);
            $this->assertFalse($stagedUser->is_active);
            $this->assertTrue($stagedUser->must_reset_password);
            $this->assertNull($stagedUser->password);
            $this->assertNotNull($stagedUser->zoho_id);

            // Assert account cannot authenticate
            $cannotLogin = $this->provisioningService->assertAccountCannotLogin($stagedUser);
            $this->assertTrue($cannotLogin, "Staged account [{$stagedUser->email}] did not fail login assertion.");

            // Verify authentication rejection under member guard
            $attempt = Auth::guard('member')->attempt([
                'email' => $stagedUser->email,
                'password' => 'AttemptedClaimSecret2026!',
                'is_active' => true,
            ]);
            $this->assertFalse($attempt);
        }

        // =========================================================================
        // SCENARIO 6: Simulated Rollback Delta Export (MIGR-05, T-11-12)
        // =========================================================================
        $cutoverCutoff = Carbon::parse('2026-11-05 12:00:00');

        // Lead submitted after cutoff with formula injection triggers
        $newPostCutoverLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspaceId,
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'TXNEWPOST1',
            'first_name' => '=SUM(1,2)', // Formula injection trigger
            'last_name' => 'Applicant',
            'email' => 'post.cutover@example.com',
            'contact_number' => '+8801700999999',
            'date_of_birth' => '1999-01-01',
            'age_at_submission' => 27,
            'sex' => 'female',
            'address_house_no' => '99',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => '@engineer', // Formula injection trigger
            'institution_study' => 'BUET',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => '+CSE', // Formula injection trigger
            'submitted_at' => Carbon::parse('2026-11-05 14:30:00'),
        ]);

        $deltaCsv = $this->deltaExportService->exportDeltaSince($cutoverCutoff, $this->workspaceId);

        // Assert CSV header matches legacy Zoho format
        $this->assertStringContainsString('"Lead UUID","Zoho ID","First Name"', $deltaCsv);
        $this->assertStringContainsString('bKash Transaction ID', $deltaCsv);

        // Assert new lead is included
        $this->assertStringContainsString('post.cutover@example.com', $deltaCsv);
        $this->assertStringContainsString('TXNEWPOST1', $deltaCsv);

        // Assert formula injection sanitization prepends single quote
        $this->assertStringContainsString("'=SUM(1,2)", $deltaCsv);
        $this->assertStringContainsString("'@engineer", $deltaCsv);
        $this->assertStringContainsString("'+CSE", $deltaCsv);

        // =========================================================================
        // SCENARIO 7: Audit Log Completeness (DEC-11, T-11-15)
        // =========================================================================
        // 1. zoho_batch_imported
        $batchAuditLogs = AuditLog::where('event_type', AuditLog::EVENT_ZOHO_BATCH_IMPORTED)
            ->where('workspace_id', $this->workspaceId)
            ->get();
        $this->assertGreaterThanOrEqual(2, $batchAuditLogs->count());

        // 2. zoho_attachment_migrated
        $attachmentAuditLogs = AuditLog::where('event_type', AuditLog::EVENT_ZOHO_ATTACHMENT_MIGRATED)
            ->where('workspace_id', $this->workspaceId)
            ->get();
        $this->assertEquals(5, $attachmentAuditLogs->count());

        // 3. zoho_reconciliation_run
        $reconAuditLogs = AuditLog::where('event_type', AuditLog::EVENT_ZOHO_RECONCILIATION_RUN)
            ->where('workspace_id', $this->workspaceId)
            ->get();
        $this->assertGreaterThanOrEqual(1, $reconAuditLogs->count());
        $latestReconLog = $reconAuditLogs->last();
        $this->assertEquals(100.0, $latestReconLog->new_values['match_percentage']);
        $this->assertEquals(0, $latestReconLog->new_values['discrepancy_count']);

        // 4. portal_account_staged_inactive
        $portalAuditLogs = AuditLog::where('event_type', AuditLog::EVENT_PORTAL_ACCOUNT_STAGED_INACTIVE)
            ->where('workspace_id', $this->workspaceId)
            ->get();
        $this->assertEquals(50, $portalAuditLogs->count());

        // 5. hub_delta_exported
        $deltaAuditLogs = AuditLog::where('event_type', AuditLog::EVENT_HUB_DELTA_EXPORTED)
            ->where('workspace_id', $this->workspaceId)
            ->get();
        $this->assertGreaterThanOrEqual(1, $deltaAuditLogs->count());
        $latestDeltaLog = $deltaAuditLogs->last();
        $this->assertEquals(1, $latestDeltaLog->new_values['exported_records_count']);
    }

    /**
     * Individual Scenario 1 verification: Dry-Run Engine.
     */
    public function test_scenario_1_dry_run_guarantees_zero_persistent_database_mutations(): void
    {
        $csvPath = $this->generateContactsCsv(10);

        $tables = [
            'users',
            'leads',
            'lead_attachments',
            'members',
            'invoices',
            'id_cards',
            'zoho_import_batches',
            'zoho_staging_records',
            'audit_logs',
        ];

        $countsBefore = [];
        foreach ($tables as $t) {
            $countsBefore[$t] = DB::table($t)->count();
        }

        $report = $this->dryRunEngine->executeDryRun($csvPath, $this->workspaceId, 'contacts_members');

        $this->assertTrue($report['rolled_back']);
        $this->assertEquals(0, $report['database_mutations_persisted']);
        $this->assertEquals(10, $report['staged_count']);
        $this->assertEquals('reconciled', $report['reconciliation_status']);

        foreach ($tables as $t) {
            $this->assertEquals($countsBefore[$t], DB::table($t)->count());
        }
    }

    /**
     * Individual Scenario 2 verification: Live Ingestion & Field Mapping.
     */
    public function test_scenario_2_live_ingestion_and_field_mapping_populates_staging_and_domain_tables(): void
    {
        $contactsCsv = $this->generateContactsCsv(10);
        $leadsCsv = $this->generateLeadsCsv(5);

        $batchContacts = $this->ingestionService->ingestFile($contactsCsv, $this->workspaceId, 'contacts_members');
        $batchLeads = $this->ingestionService->ingestFile($leadsCsv, $this->workspaceId, 'leads');

        $this->assertEquals('completed', $batchContacts->status);
        $this->assertEquals(10, $batchContacts->successful_rows);

        $this->assertEquals('completed', $batchLeads->status);
        $this->assertEquals(5, $batchLeads->successful_rows);

        $this->assertEquals(15, ZohoStagingRecord::where('workspace_id', $this->workspaceId)->count());
        $this->assertEquals(10, Member::where('workspace_id', $this->workspaceId)->count());
        $this->assertEquals(15, Lead::where('workspace_id', $this->workspaceId)->count());
    }

    /**
     * Individual Scenario 3 verification: Attachment Migration & Secure Storage.
     */
    public function test_scenario_3_attachment_migration_encrypts_and_applies_youth_protection(): void
    {
        $contactsCsv = $this->generateContactsCsv(2);
        $this->ingestionService->ingestFile($contactsCsv, $this->workspaceId, 'contacts_members');

        $minorMember = Member::where('zoho_id', 'ZCONT-001')->first();
        $this->assertNotNull($minorMember);

        $pdf = $this->createSamplePdf('minor_doc.pdf');
        $rawBytes = file_get_contents($pdf);

        $att = $this->attachmentService->migrateAttachment($pdf, 'ZCONT-001', 'identity_document', $this->workspaceId);

        $this->assertNotNull($att);
        $this->assertTrue($att->youth_protection);
        $this->assertTrue(Storage::disk('private')->exists($att->storage_path));

        $cipher = Storage::disk('private')->get($att->storage_path);
        $this->assertNotEquals($rawBytes, $cipher);
        $this->assertEquals($rawBytes, Crypt::decryptString($cipher));
        $this->assertEquals(hash('sha256', $rawBytes), $att->file_hash);
    }

    /**
     * Individual Scenario 4 verification: Automated Reconciliation.
     */
    public function test_scenario_4_automated_reconciliation_reports_100_percent_match(): void
    {
        $contactsCsv = $this->generateContactsCsv(5);
        $this->ingestionService->ingestFile($contactsCsv, $this->workspaceId, 'contacts_members');

        $report = $this->reconciliationService->reconcileWorkspace($this->workspaceId);

        $this->assertTrue($report->isReconciled());
        $this->assertEquals(100.0, $report->matchPercentage());
        $this->assertEquals(0, $report->discrepancyCount);

        $csv = $this->reportGenerator->toCsv($report);
        $this->assertStringContainsString('zoho_id,entity,field', $csv);

        $html = $this->reportGenerator->toHtml($report);
        $this->assertStringContainsString('100.00%', $html);
    }

    /**
     * Individual Scenario 5 verification: Inactive Portal Account Provisioning.
     */
    public function test_scenario_5_inactive_member_portal_account_provisioning_and_fail_closed_authentication(): void
    {
        $contactsCsv = $this->generateContactsCsv(5);
        $this->ingestionService->ingestFile($contactsCsv, $this->workspaceId, 'contacts_members');

        $result = $this->provisioningService->provisionInactiveAccounts($this->workspaceId);

        $this->assertEquals(5, $result['staged_count']);
        $this->assertCount(5, $result['user_ids']);

        foreach ($result['user_ids'] as $uid) {
            $user = User::find($uid);
            $this->assertEquals('member', $user->guard);
            $this->assertFalse($user->is_active);
            $this->assertNull($user->password);
            $this->assertTrue($this->provisioningService->assertAccountCannotLogin($user));
        }
    }

    /**
     * Individual Scenario 6 verification: Hub Delta Export with Formula Sanitization.
     */
    public function test_scenario_6_simulated_rollback_delta_export_with_formula_sanitization(): void
    {
        $cutoff = Carbon::parse('2026-11-05 10:00:00');

        Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $this->workspaceId,
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'TXDELTATEST',
            'first_name' => '=1+1',
            'last_name' => 'Test',
            'email' => 'delta.test@example.com',
            'contact_number' => '+8801700112233',
            'date_of_birth' => '1996-01-01',
            'age_at_submission' => 30,
            'sex' => 'male',
            'address_house_no' => '1',
            'address_area' => 'Mirpur',
            'address_zip_code' => '1216',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => '-attacker',
            'institution_study' => 'JU',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => '@math',
            'submitted_at' => Carbon::parse('2026-11-05 11:00:00'),
        ]);

        $csv = $this->deltaExportService->exportDeltaSince($cutoff, $this->workspaceId);

        $this->assertStringContainsString('delta.test@example.com', $csv);
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString("'-attacker", $csv);
        $this->assertStringContainsString("'@math", $csv);
    }

    /**
     * Individual Scenario 7 verification: Audit Log Completeness.
     */
    public function test_scenario_7_audit_log_completeness_across_all_migration_lifecycle_events(): void
    {
        $contactsCsv = $this->generateContactsCsv(2);
        $this->ingestionService->ingestFile($contactsCsv, $this->workspaceId, 'contacts_members');

        $pdf = $this->createSamplePdf('sample.pdf');
        $this->attachmentService->migrateAttachment($pdf, 'ZCONT-001', 'identity_document', $this->workspaceId);

        $this->reconciliationService->reconcileWorkspace($this->workspaceId);
        $this->provisioningService->provisionInactiveAccounts($this->workspaceId);
        $this->deltaExportService->exportDeltaSince(Carbon::now()->subHour(), $this->workspaceId);

        $events = [
            AuditLog::EVENT_ZOHO_BATCH_IMPORTED,
            AuditLog::EVENT_ZOHO_ATTACHMENT_MIGRATED,
            AuditLog::EVENT_ZOHO_RECONCILIATION_RUN,
            AuditLog::EVENT_PORTAL_ACCOUNT_STAGED_INACTIVE,
            AuditLog::EVENT_HUB_DELTA_EXPORTED,
        ];

        foreach ($events as $event) {
            $count = AuditLog::where('event_type', $event)->where('workspace_id', $this->workspaceId)->count();
            $this->assertGreaterThan(0, $count, "Lifecycle event [{$event}] was not recorded in audit_logs.");
        }
    }
}
