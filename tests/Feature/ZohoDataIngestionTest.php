<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Migration\Models\ZohoImportBatch;
use App\Domain\Migration\Models\ZohoStagingRecord;
use App\Domain\Migration\Services\ZohoDataIngestionService;
use App\Domain\Migration\Services\ZohoFieldMappingService;
use App\Domain\Workspaces\Models\Workspace;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ZohoDataIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected ZohoDataIngestionService $ingestionService;
    protected ZohoFieldMappingService $mappingService;
    protected string $workspaceId = 'ws-emk-dhaka';
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->mappingService = app(ZohoFieldMappingService::class);
        $this->ingestionService = app(ZohoDataIngestionService::class);

        $this->tempDir = sys_get_temp_dir() . '/zoho_test_' . uniqid();
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

    public function test_staging_tables_and_models_have_bidirectional_relationships(): void
    {
        $batch = ZohoImportBatch::create([
            'batch_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'workspace_id' => $this->workspaceId,
            'source_type' => 'contacts_members',
            'source_filename' => 'contacts_test.csv',
            'status' => 'pending',
        ]);

        $record = ZohoStagingRecord::create([
            'batch_id' => $batch->id,
            'workspace_id' => $this->workspaceId,
            'zoho_id' => 'ZC-998877',
            'source_type' => 'contacts_members',
            'raw_payload' => ['Name' => 'John Doe', 'Email' => 'john@example.com'],
            'mapped_payload' => ['first_name' => 'John', 'last_name' => 'Doe'],
            'validation_status' => 'valid',
            'status' => 'staged',
        ]);

        $this->assertTrue($batch->records()->where('id', $record->id)->exists());
        $this->assertEquals($batch->id, $record->batch->id);
        $this->assertEquals($this->workspaceId, $batch->getWorkspaceId());
        $this->assertEquals($this->workspaceId, $record->getWorkspaceId());
    }

    public function test_field_mapping_transforms_contacts_members_fidelity(): void
    {
        $raw = [
            'CONTACTID' => 'ZC-100200',
            'First Name' => '  Tahmid  ',
            'Last Name' => '  Rahman  ',
            'Email' => 'Tahmid.Rahman@Example.COM',
            'Mobile' => '01712345678',
            'Date of Birth' => '15/08/1996',
            'Gender' => 'Male',
            'House No' => 'House 42',
            'Road No' => 'Road 7',
            'Area' => 'Dhanmondi',
            'Postal Code' => '1209',
            'Division' => 'Dhaka',
            'District' => 'Dhaka',
            'Membership Type' => '1 Year Membership',
            'Start Date' => '2024-01-01',
            'Expiry Date' => '2025-01-01',
            'Status' => 'Active',
            'Occupation' => 'Student',
            'Institution' => 'Dhaka University',
            'Highest Certification' => 'Undergraduate Degree',
            'Area of Subject' => 'Economics',
        ];

        $result = $this->mappingService->mapContactMember($raw, $this->workspaceId);

        $this->assertEquals('valid', $result['validation_status']);
        $mapped = $result['mapped'];

        $this->assertEquals('ZC-100200', $mapped['zoho_id']);
        $this->assertEquals('Tahmid', $mapped['first_name']);
        $this->assertEquals('Rahman', $mapped['last_name']);
        $this->assertEquals('tahmid.rahman@example.com', $mapped['email']);
        $this->assertEquals('+8801712345678', $mapped['contact_number']);
        $this->assertEquals('1996-08-15', $mapped['date_of_birth']);
        $this->assertEquals('male', $mapped['sex']);
        $this->assertEquals('active', $mapped['status']);
        $this->assertEquals('student', $mapped['occupation']);
        $this->assertEquals('undergraduate_degree', $mapped['highest_certification']);
    }

    public function test_field_mapping_transforms_leads_with_bkash_and_status(): void
    {
        $raw = [
            'LEADID' => 'ZL-554433',
            'First Name' => 'Nusrat',
            'Last Name' => 'Jahan',
            'Email' => 'nusrat.jahan@example.com',
            'Phone' => '+8801812345678',
            'DOB' => '1998-05-20',
            'Gender' => 'Female',
            'bKash Transaction ID' => '  bk7x9q12m0  ',
            'Status' => 'Under Review',
            'House No' => 'Block C',
            'Area' => 'Mirpur',
            'Postal Code' => '1216',
            'Division' => 'Dhaka',
            'District' => 'Dhaka',
            'Occupation' => 'Artist',
            'Institution' => 'Fine Arts Institute',
            'Highest Certification' => 'Bachelor of Fine Arts',
            'Area of Subject' => 'Painting',
        ];

        $result = $this->mappingService->mapLead($raw, $this->workspaceId);

        $this->assertEquals('valid', $result['validation_status']);
        $mapped = $result['mapped'];

        $this->assertEquals('ZL-554433', $mapped['zoho_id']);
        $this->assertEquals('Nusrat', $mapped['first_name']);
        $this->assertEquals('BK7X9Q12M0', $mapped['bkash_txid']); // DEC-05 10-char uppercase
        $this->assertEquals('under_review', $mapped['status']);
        $this->assertEquals('female', $mapped['sex']);
        $this->assertEquals('artist', $mapped['occupation']);
        $this->assertEquals('undergraduate_degree', $mapped['highest_certification']);
    }

    public function test_ingest_contacts_csv_persists_members_and_preserves_zoho_id(): void
    {
        $csvPath = $this->tempDir . '/contacts.csv';
        $content = "CONTACTID,First Name,Last Name,Email,Mobile,Date of Birth,Gender,House No,Road No,Area,Postal Code,Division,District,Membership Type,Start Date,Expiry Date,Status\n" .
            "ZCONT-001,Amina,Haque,amina.haque@example.com,01711223344,1992-04-10,Female,12/A,5,Gulshan,1212,Dhaka,Dhaka,1 Year,2024-02-01,2025-02-01,Active\n" .
            "ZCONT-002,Kamal,Hossain,kamal.hossain@example.com,01822334455,1990-11-25,Male,84,3,Banani,1213,Dhaka,Dhaka,1 Year,2024-03-01,2025-03-01,Active\n";
        file_put_contents($csvPath, $content);

        $batch = $this->ingestionService->ingestFile($csvPath, $this->workspaceId, 'contacts_members', 10);

        $this->assertEquals('completed', $batch->status);
        $this->assertEquals(2, $batch->total_rows);
        $this->assertEquals(2, $batch->successful_rows);
        $this->assertEquals(0, $batch->failed_rows);
        $this->assertEquals(0, $batch->skipped_rows);

        // Verify Member entities created with preserved zoho_id
        $member1 = Member::where('zoho_id', 'ZCONT-001')->first();
        $this->assertNotNull($member1);
        $this->assertEquals('Amina', $member1->first_name);
        $this->assertEquals('Haque', $member1->last_name);
        $this->assertEquals('amina.haque@example.com', $member1->email);
        $this->assertEquals('ZCONT-001', $member1->zoho_id);
        $this->assertStringStartsWith('EMK-', $member1->member_id);

        $member2 = Member::where('zoho_id', 'ZCONT-002')->first();
        $this->assertNotNull($member2);
        $this->assertEquals('kamal.hossain@example.com', $member2->email);
        $this->assertEquals('ZCONT-002', $member2->zoho_id);

        // Verify Staging Records created
        $this->assertEquals(2, ZohoStagingRecord::where('batch_id', $batch->id)->where('status', 'imported')->count());

        // Verify Audit Log emitted per DEC-11
        $auditLog = AuditLog::where('event_type', AuditLog::EVENT_ZOHO_BATCH_IMPORTED)
            ->where('auditable_id', $batch->id)
            ->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals($batch->batch_uuid, $auditLog->new_values['batch_uuid']);
    }

    public function test_ingest_leads_csv_persists_leads_and_preserves_zoho_id(): void
    {
        $csvPath = $this->tempDir . '/leads.csv';
        $content = "LEADID,First Name,Last Name,Email,Phone,DOB,Gender,bKash Transaction ID,Status,House No,Area,Postal Code,Division,District\n" .
            "ZLEAD-001,Tanvir,Ahmed,tanvir.ahmed@example.com,01933445566,1999-07-14,Male,TXID123456,Submitted,10,Mohammadpur,1207,Dhaka,Dhaka\n" .
            "ZLEAD-002,Sadia,Islam,sadia.islam@example.com,01644556677,2001-09-30,Female,TXID654321,In Review,22,Uttara,1230,Dhaka,Dhaka\n";
        file_put_contents($csvPath, $content);

        $batch = $this->ingestionService->ingestFile($csvPath, $this->workspaceId, 'leads', 10);

        $this->assertEquals('completed', $batch->status);
        $this->assertEquals(2, $batch->total_rows);
        $this->assertEquals(2, $batch->successful_rows);

        $lead1 = Lead::where('zoho_id', 'ZLEAD-001')->first();
        $this->assertNotNull($lead1);
        $this->assertEquals('Tanvir', $lead1->first_name);
        $this->assertEquals('ZLEAD-001', $lead1->zoho_id);
        $this->assertEquals('TXID123456', $lead1->bkash_txid);
        $this->assertEquals('submitted', $lead1->status);

        $lead2 = Lead::where('zoho_id', 'ZLEAD-002')->first();
        $this->assertNotNull($lead2);
        $this->assertEquals('ZLEAD-002', $lead2->zoho_id);
        $this->assertEquals('under_review', $lead2->status);
    }

    public function test_duplicate_records_are_skipped_without_crashing_batch(): void
    {
        $csvPath = $this->tempDir . '/duplicates.csv';
        $content = "LEADID,First Name,Last Name,Email,Phone,DOB,Gender,bKash Transaction ID,Status,House No,Area,Postal Code,Division,District\n" .
            "ZLEAD-DUP1,Farhan,Karim,farhan@example.com,01711002200,1997-01-01,Male,DUPTXID001,Submitted,1,Area,1200,Dhaka,Dhaka\n";
        file_put_contents($csvPath, $content);

        // First run succeeds
        $batch1 = $this->ingestionService->ingestFile($csvPath, $this->workspaceId, 'leads');
        $this->assertEquals(1, $batch1->successful_rows);
        $this->assertEquals(0, $batch1->skipped_rows);

        // Second run with same zoho_id skips
        $batch2 = $this->ingestionService->ingestFile($csvPath, $this->workspaceId, 'leads');
        $this->assertEquals(0, $batch2->successful_rows);
        $this->assertEquals(1, $batch2->skipped_rows);

        // Staging record recorded as skipped
        $record = ZohoStagingRecord::where('batch_id', $batch2->id)->first();
        $this->assertEquals('skipped', $record->status);
    }

    public function test_dry_run_stages_records_without_persisting_domain_models(): void
    {
        $csvPath = $this->tempDir . '/dryrun.csv';
        $content = "CONTACTID,First Name,Last Name,Email,Mobile,Date of Birth,Gender,House No,Area,Postal Code,Division,District,Membership Type,Start Date,Status\n" .
            "ZCONT-DRY,Simu,Akter,simu.akter@example.com,01811998877,1994-03-12,Female,5,Tejgaon,1215,Dhaka,Dhaka,1 Year,2024-01-01,Active\n";
        file_put_contents($csvPath, $content);

        $batch = $this->ingestionService->ingestFile($csvPath, $this->workspaceId, 'contacts_members', 10, true);

        $this->assertEquals('completed', $batch->status);
        $this->assertEquals(1, $batch->successful_rows);

        // Verify Member was NOT created
        $this->assertNull(Member::where('zoho_id', 'ZCONT-DRY')->first());

        // But Staging record WAS created with status 'staged'
        $stagingRecord = ZohoStagingRecord::where('batch_id', $batch->id)->first();
        $this->assertNotNull($stagingRecord);
        $this->assertEquals('staged', $stagingRecord->status);
    }

    public function test_artisan_zoho_import_command_executes_successfully(): void
    {
        $csvPath = $this->tempDir . '/cli_contacts.csv';
        $content = "CONTACTID,First Name,Last Name,Email,Mobile,Date of Birth,Gender,House No,Area,Postal Code,Division,District,Membership Type,Start Date,Status\n" .
            "ZCLI-001,Rahim,Uddin,rahim.uddin@example.com,01755667788,1995-06-15,Male,12,Dhanmondi,1209,Dhaka,Dhaka,1 Year,2024-01-01,Active\n" .
            "ZCLI-002,Karim,Uddin,karim.uddin@example.com,01755667799,1993-08-20,Male,14,Dhanmondi,1209,Dhaka,Dhaka,1 Year,2024-01-01,Active\n";
        file_put_contents($csvPath, $content);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('zoho:import', [
            'file' => $csvPath,
            '--workspace' => $this->workspaceId,
            '--type' => 'contacts_members',
            '--chunk' => 1,
        ]);

        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('EMK Portal - Legacy Zoho Data Ingestion Tool', $output);
        $this->assertStringContainsString('Ingestion batch completed successfully', $output);
        $this->assertStringContainsString('ZCLI-001', Member::where('zoho_id', 'ZCLI-001')->value('zoho_id'));
        $this->assertStringContainsString('ZCLI-002', Member::where('zoho_id', 'ZCLI-002')->value('zoho_id'));
    }

    public function test_artisan_zoho_import_command_fails_gracefully_when_file_missing_or_invalid(): void
    {
        // 1. Missing file
        $exitCode = \Illuminate\Support\Facades\Artisan::call('zoho:import', [
            'file' => $this->tempDir . '/non_existent.csv',
            '--workspace' => $this->workspaceId,
        ]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('does not exist or is not readable', $output);

        // 2. Invalid extension
        $txtPath = $this->tempDir . '/invalid_ext.txt';
        file_put_contents($txtPath, 'some data');

        $exitCodeTxt = \Illuminate\Support\Facades\Artisan::call('zoho:import', [
            'file' => $txtPath,
            '--workspace' => $this->workspaceId,
        ]);
        $outputTxt = \Illuminate\Support\Facades\Artisan::output();

        $this->assertEquals(1, $exitCodeTxt);
        $this->assertStringContainsString('must have a .csv extension', $outputTxt);
    }

    public function test_artisan_zoho_import_command_rejects_non_existent_workspace(): void
    {
        $csvPath = $this->tempDir . '/dummy.csv';
        file_put_contents($csvPath, "CONTACTID,First Name,Last Name,Email\nZC-1,A,B,a@example.com\n");

        $exitCode = \Illuminate\Support\Facades\Artisan::call('zoho:import', [
            'file' => $csvPath,
            '--workspace' => 'ws-non-existent-random',
        ]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertEquals(1, $exitCode);
        $this->assertStringContainsString('Target workspace [ws-non-existent-random] does not exist', $output);
    }

    public function test_artisan_zoho_import_command_maintains_memory_bounds_under_multi_chunk_load(): void
    {
        $csvPath = $this->tempDir . '/large_contacts.csv';
        $handle = fopen($csvPath, 'w');
        fputcsv($handle, [
            'CONTACTID', 'First Name', 'Last Name', 'Email', 'Mobile', 'Date of Birth',
            'Gender', 'House No', 'Area', 'Postal Code', 'Division', 'District', 'Membership Type', 'Start Date', 'Status',
        ]);

        for ($i = 1; $i <= 150; $i++) {
            fputcsv($handle, [
                "ZMEM-CHUNK-{$i}",
                "User{$i}",
                "Test",
                "user{$i}.chunk@example.com",
                "0171" . str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                '1995-01-01',
                'Male',
                "House {$i}",
                'Area',
                '1209',
                'Dhaka',
                'Dhaka',
                '1 Year',
                '2024-01-01',
                'Active',
            ]);
        }
        fclose($handle);

        $exitCode = \Illuminate\Support\Facades\Artisan::call('zoho:import', [
            'file' => $csvPath,
            '--workspace' => $this->workspaceId,
            '--type' => 'contacts_members',
            '--chunk' => 25,
        ]);

        $this->assertEquals(0, $exitCode);
        $this->assertEquals(150, Member::where('email', 'like', '%.chunk@example.com')->count());

        // Verify peak memory consumption is safely below 64MB per T-11-02
        $peakMemory = memory_get_peak_usage(true);
        $this->assertLessThan(64 * 1024 * 1024, $peakMemory, "Memory peak exceeded 64MB envelope: {$peakMemory} bytes");
    }
}
