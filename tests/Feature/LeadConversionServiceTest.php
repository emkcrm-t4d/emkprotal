<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Exceptions\UnauthorizedVerificationException;
use App\Domain\Leads\Exceptions\UnmetConversionInvariantException;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Services\LeadConversionService;
use App\Domain\Members\Models\Member;
use App\Jobs\SendMemberWelcomeEmailJob;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class LeadConversionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected LeadConversionService $service;
    protected User $manager;
    protected User $staff;
    protected User $crossWorkspaceManager;
    protected MembershipTerm $term;
    protected Lead $approvedLead;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->service = app(LeadConversionService::class);

        // 1. Authorized Workspace Manager
        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Manager User',
            'email' => 'mgr.conv@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');

        // 2. Staff user without leads.convert
        $this->staff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Staff User',
            'email' => 'staff.conv@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staff->assignRole('delegated_staff');

        // 3. Second Workspace & Cross-Workspace Manager
        $ctgSpace = DB::table('spaces')->where('code', 'CR-CTG')->first();
        DB::table('workspaces')->updateOrInsert(
            ['id' => 'ws-cr-ctg'],
            [
                'space_id' => $ctgSpace->id,
                'name' => 'American Corner Chattogram',
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->crossWorkspaceManager = User::create([
            'workspace_id' => 'ws-cr-ctg',
            'name' => 'Other Space Manager',
            'email' => 'other.conv@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->crossWorkspaceManager->assignRole('workspace_manager');

        // 4. Active Membership Term (from MembershipTermSeeder)
        $this->term = MembershipTerm::where('workspace_id', 'ws-emk-dhaka')
            ->where('code', 'std-1yr')
            ->firstOrFail();

        // 5. Fully Verified & Approved Lead
        $this->approvedLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'approved',
            'bkash_txid' => 'BKASHCONV01',
            'first_name' => 'Tahsin',
            'middle_name' => 'Ahmed',
            'last_name' => 'Khan',
            'email' => 'tahsin.khan@example.com',
            'contact_number' => '+8801712345001',
            'date_of_birth' => '1998-05-20',
            'age_at_submission' => 28,
            'sex' => 'male',
            'address_house_no' => 'Flat 4B, House 10',
            'address_road_no' => 'Road 27',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'employed',
            'institution_study' => 'University of Dhaka',
            'highest_certification' => 'masters',
            'area_of_subject' => 'Computer Science',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'has_parental_consent' => false,
            'submitted_at' => now()->subDays(2),
        ]);
    }

    public function test_successful_conversion_executes_all_10_steps_atomically(): void
    {
        Queue::fake();
        Storage::fake('private');

        // Setup lead private files
        $disk = Storage::disk('private');
        $leadDir = "workspaces/{$this->approvedLead->workspace_id}/leads/{$this->approvedLead->uuid}";
        $disk->put("{$leadDir}/nid.pdf", 'test content for nid');
        $disk->put("{$leadDir}/photo.jpg", 'test content for photo');

        $now = Carbon::parse('2026-11-04 12:00:00');
        Carbon::setTestNow($now);

        $member = $this->service->convert(
            $this->approvedLead,
            $this->term->id,
            $this->manager
        );

        // Step 2 & 3: Member profile instantiated with correct ID
        $this->assertInstanceOf(Member::class, $member);
        $this->assertEquals('EMK-2026-00001', $member->member_id);
        $this->assertEquals('Tahsin', $member->first_name);
        $this->assertEquals('Ahmed', $member->middle_name);
        $this->assertEquals('Khan', $member->last_name);
        $this->assertEquals('tahsin.khan@example.com', $member->email);
        $this->assertEquals('active', $member->status);

        // Step 4: Dynamic dates calculated from term duration (12 months per DEC-08)
        $this->assertEquals('2026-11-04', $member->start_date->toDateString());
        $this->assertEquals('2027-11-04', $member->expiry_date->toDateString());

        // Step 9 prep: Provisioned portal user
        $this->assertDatabaseHas('users', [
            'id' => $member->user_id,
            'workspace_id' => 'ws-emk-dhaka',
            'email' => 'tahsin.khan@example.com',
            'guard' => 'member',
            'is_active' => true,
            'must_reset_password' => true,
        ]);

        // Step 5: Document re-association on private storage
        $memberDir = "workspaces/{$member->workspace_id}/members/{$member->uuid}";
        $this->assertTrue($disk->exists("{$memberDir}/nid.pdf"));
        $this->assertTrue($disk->exists("{$memberDir}/photo.jpg"));

        // Step 6: Post-conversion paid invoice generated (INV-2026-00001 per DEC-03)
        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'INV-2026-00001',
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $member->id,
            'lead_id' => $this->approvedLead->id,
            'amount_bdt' => '500.00',
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'BKASHCONV01',
            'status' => 'paid',
        ]);

        // Step 7: Plastic ID card queued for printing
        $this->assertDatabaseHas('id_cards', [
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $member->id,
            'card_number' => 'EMK-2026-00001',
            'status' => 'provided_for_printing',
        ]);

        // Step 8: Lead updated to converted
        $freshLead = $this->approvedLead->fresh();
        $this->assertEquals('converted', $freshLead->status);
        $this->assertEquals($member->id, $freshLead->converted_member_id);

        // Step 9: Welcome email job queued
        Queue::assertPushed(SendMemberWelcomeEmailJob::class, function (SendMemberWelcomeEmailJob $job) use ($member) {
            return $job->memberId === $member->id;
        });

        // Step 10: Immutable audit log emitted (DEC-11)
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->manager->id,
            'event_type' => AuditLog::EVENT_CONVERSION_COMPLETED,
            'auditable_type' => Member::class,
            'auditable_id' => $member->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_conversion_blocked_if_payment_stream_is_not_verified(): void
    {
        $this->approvedLead->update(['payment_verification_status' => 'pending']);

        $this->expectException(UnmetConversionInvariantException::class);
        $this->expectExceptionCode(422);

        try {
            $this->service->convert($this->approvedLead, $this->term->id, $this->manager);
        } catch (UnmetConversionInvariantException $e) {
            $this->assertArrayHasKey('payment', $e->getUnmetConditions());
            $this->assertDatabaseCount('members', 0);
            $this->assertDatabaseCount('invoices', 0);
            $this->assertDatabaseCount('id_cards', 0);
            throw $e;
        }
    }

    public function test_conversion_blocked_if_document_stream_is_not_verified(): void
    {
        $this->approvedLead->update(['document_verification_status' => 'missing_items']);

        $this->expectException(UnmetConversionInvariantException::class);

        try {
            $this->service->convert($this->approvedLead, $this->term->id, $this->manager);
        } catch (UnmetConversionInvariantException $e) {
            $this->assertArrayHasKey('document', $e->getUnmetConditions());
            $this->assertDatabaseCount('members', 0);
            throw $e;
        }
    }

    public function test_conversion_blocked_if_photo_stream_is_not_verified(): void
    {
        $this->approvedLead->update(['photo_verification_status' => 'reupload_requested']);

        $this->expectException(UnmetConversionInvariantException::class);

        try {
            $this->service->convert($this->approvedLead, $this->term->id, $this->manager);
        } catch (UnmetConversionInvariantException $e) {
            $this->assertArrayHasKey('photo', $e->getUnmetConditions());
            $this->assertDatabaseCount('members', 0);
            throw $e;
        }
    }

    public function test_conversion_blocked_if_overall_lead_is_not_approved(): void
    {
        $this->approvedLead->update(['status' => 'under_review']);

        $this->expectException(UnmetConversionInvariantException::class);

        try {
            $this->service->convert($this->approvedLead, $this->term->id, $this->manager);
        } catch (UnmetConversionInvariantException $e) {
            $this->assertArrayHasKey('lead_status', $e->getUnmetConditions());
            $this->assertDatabaseCount('members', 0);
            throw $e;
        }
    }

    public function test_conversion_blocked_if_minor_lacks_parental_consent(): void
    {
        $this->approvedLead->update([
            'date_of_birth' => '2010-01-01',
            'age_at_submission' => 16,
            'has_parental_consent' => false,
        ]);

        $this->expectException(UnmetConversionInvariantException::class);

        try {
            $this->service->convert($this->approvedLead, $this->term->id, $this->manager);
        } catch (UnmetConversionInvariantException $e) {
            $this->assertArrayHasKey('parental_consent', $e->getUnmetConditions());
            $this->assertDatabaseCount('members', 0);
            throw $e;
        }
    }

    public function test_conversion_blocked_if_email_already_belongs_to_active_member(): void
    {
        // Another lead for existing member
        $existingLead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'converted',
            'bkash_txid' => 'BKASHEXIST1',
            'first_name' => 'Existing',
            'last_name' => 'Member',
            'email' => 'existing.member@example.com',
            'contact_number' => '+8801700000000',
            'date_of_birth' => '1995-01-01',
            'age_at_submission' => 31,
            'sex' => 'male',
            'address_house_no' => '1',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'employed',
            'institution_study' => 'BUET',
            'highest_certification' => 'bachelors',
            'area_of_subject' => 'EEE',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'submitted_at' => now()->subYear(),
        ]);

        // Existing active member with same email as approvedLead
        Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => 'EMK-2025-00100',
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $existingLead->id,
            'user_id' => $this->manager->id,
            'membership_term_id' => $this->term->id,
            'status' => 'active',
            'first_name' => 'Existing',
            'last_name' => 'Member',
            'email' => $this->approvedLead->email,
            'contact_number' => '+8801700000000',
            'date_of_birth' => '1995-01-01',
            'sex' => 'male',
            'address_house_no' => '1',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'employed',
            'institution_study' => 'BUET',
            'highest_certification' => 'bachelors',
            'area_of_subject' => 'EEE',
            'start_date' => '2025-01-01',
            'expiry_date' => '2026-01-01',
        ]);

        $this->expectException(UnmetConversionInvariantException::class);

        try {
            $this->service->convert($this->approvedLead, $this->term->id, $this->manager);
        } catch (UnmetConversionInvariantException $e) {
            $this->assertArrayHasKey('email_conflict', $e->getUnmetConditions());
            throw $e;
        }
    }

    public function test_transaction_rollback_leaves_zero_orphan_records_on_unexpected_failure(): void
    {
        $shouldFail = true;
        Event::listen('eloquent.creating: ' . IdCard::class, function () use (&$shouldFail) {
            if ($shouldFail) {
                $shouldFail = false;
                throw new RuntimeException('Simulated database crash during ID card insertion');
            }
        });

        try {
            $this->service->convert($this->approvedLead, $this->term->id, $this->manager);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertEquals('Simulated database crash during ID card insertion', $e->getMessage());
        }

        // Verify total rollback: zero orphan records in members, invoices, id_cards
        $this->assertEquals(0, Member::count());
        $this->assertEquals(0, Invoice::count());
        $this->assertEquals(0, IdCard::count());

        // Lead remains approved, unconverted
        $freshLead = $this->approvedLead->fresh();
        $this->assertEquals('approved', $freshLead->status);
        $this->assertNull($freshLead->converted_member_id);

        // Sequence counter rolled back
        $sequence = DB::table('member_id_sequences')
            ->where('space_code', 'EMK')
            ->where('year', Carbon::now()->year)
            ->first();
        $this->assertNull($sequence);
    }

    public function test_unauthorized_staff_cannot_convert_leads(): void
    {
        $this->expectException(UnauthorizedVerificationException::class);
        $this->expectExceptionMessage('leads.convert');

        $this->service->convert($this->approvedLead, $this->term->id, $this->staff);
    }

    public function test_cross_workspace_manager_cannot_convert_leads(): void
    {
        $this->expectException(UnauthorizedVerificationException::class);
        $this->expectExceptionMessage('does not have access to workspace');

        $this->service->convert($this->approvedLead, $this->term->id, $this->crossWorkspaceManager);
    }
}
