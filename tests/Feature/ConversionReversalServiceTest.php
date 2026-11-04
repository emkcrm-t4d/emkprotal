<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Models\LeadAttachment;
use App\Domain\Leads\Models\LeadVerification;
use App\Domain\Leads\Services\LeadConversionService;
use App\Domain\Members\Exceptions\InvalidConversionReversalException;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Services\ConversionReversalService;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConversionReversalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ConversionReversalService $reversalService;
    protected LeadConversionService $conversionService;
    protected User $manager;
    protected User $staff;
    protected User $crossWorkspaceManager;
    protected MembershipTerm $term;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->seed(DatabaseSeeder::class);

        $this->reversalService = app(ConversionReversalService::class);
        $this->conversionService = app(LeadConversionService::class);

        // 1. Authorized Workspace Manager
        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Manager User',
            'email' => 'mgr.rev@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');

        // 2. Delegated Staff user without leads.reverse_conversion
        $this->staff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Staff User',
            'email' => 'staff.rev@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staff->assignRole('delegated_staff');

        // 3. Cross-Workspace Manager
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
            'email' => 'other.rev@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->crossWorkspaceManager->assignRole('workspace_manager');

        // 4. Active Membership Term
        $this->term = MembershipTerm::where('workspace_id', 'ws-emk-dhaka')
            ->where('code', 'std-1yr')
            ->firstOrFail();
    }

    /**
     * Helper to provision a fully verified and converted member.
     */
    protected function createConvertedMember(string $email = 'applicant.rev@example.com'): array
    {
        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'first_name' => 'Tahmid',
            'last_name' => 'Rahman',
            'email' => $email,
            'contact_number' => '+8801711000000',
            'date_of_birth' => '2000-01-01',
            'sex' => 'male',
            'address_house_no' => '12/A',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'hsc',
            'area_of_subject' => 'Computer Science',
            'target_space_code' => 'EMK',
            'status' => 'approved',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'age_at_submission' => 26,
            'bkash_txid' => 'REV' . strtoupper(Str::random(7)),
            'submitted_at' => now()->subDays(5),
            'terms_agreed' => true,
        ]);

        LeadAttachment::create([
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'document_type' => 'identity_document',
            'original_filename' => 'nid_scan.pdf',
            'storage_path' => 'workspaces/ws-emk-dhaka/leads/' . $lead->uuid . '/nid.pdf',
            'size_bytes' => 1024,
            'mime_type' => 'application/pdf',
            'file_hash' => hash('sha256', 'dummy-data'),
        ]);

        LeadVerification::create([
            'lead_id' => $lead->id,
            'stream' => 'payment',
            'status' => 'verified',
            'verified_by_user_id' => $this->manager->id,
            'verified_at' => now(),
            'evidence_payload' => ['txid' => $lead->bkash_txid],
        ]);

        $member = $this->conversionService->convert($lead, $this->term->id, $this->manager);

        return [$lead, $member];
    }

    public function test_can_reverse_evaluates_eligibility_correctly(): void
    {
        /** @var Member $member */
        [$lead, $member] = $this->createConvertedMember();

        $check = $this->reversalService->canReverse($member);
        $this->assertTrue($check['allowed']);
        $this->assertEmpty($check['reasons']);
    }

    public function test_successful_reversal_within_15_days_with_25_character_justification(): void
    {
        /** @var Lead $lead */
        /** @var Member $member */
        [$lead, $member] = $this->createConvertedMember();

        // Simulate conversion occurred 15 days ago
        $member->created_at = Carbon::now()->subDays(15);
        $member->save();

        $justification = 'Accidental approval during testing phase with invalid payment reference.';
        $this->assertGreaterThanOrEqual(20, mb_strlen($justification));

        $this->reversalService->reverse($member, $justification, $this->manager);

        // Assert member archived_reversed
        $member->refresh();
        $this->assertSame('archived_reversed', $member->status);

        // Assert portal user deactivated and remember_token evicted
        $user = User::find($member->user_id);
        $this->assertNotNull($user);
        $this->assertFalse($user->is_active);
        $this->assertNull($user->remember_token);

        // Assert post-conversion invoice marked void_reversed
        $invoice = Invoice::where('member_id', $member->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('void_reversed', $invoice->status);

        // Assert ID card marked voided_reversal
        $card = IdCard::where('member_id', $member->id)->first();
        $this->assertNotNull($card);
        $this->assertSame('voided_reversal', $card->status);

        // Assert lead restored to under_review, converted_member_id nulled
        $lead->refresh();
        $this->assertSame('under_review', $lead->status);
        $this->assertNull($lead->converted_member_id);

        // Assert attachments and verifications preserved for re-inspection
        $this->assertCount(1, $lead->attachments);
        $this->assertCount(1, $lead->verifications);

        // Assert immutable audit event recorded
        $auditLog = AuditLog::where('auditable_type', Member::class)
            ->where('auditable_id', $member->id)
            ->where('event_type', AuditLog::EVENT_CONVERSION_REVERSED)
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame($this->manager->id, $auditLog->actor_id);
        $this->assertSame($justification, $auditLog->reason);
        $this->assertSame('active', $auditLog->old_values['status']);
        $this->assertSame('archived_reversed', $auditLog->new_values['status']);
        $this->assertSame($lead->id, $auditLog->new_values['lead_id']);
        $this->assertSame('under_review', $auditLog->new_values['restored_lead_status']);
    }

    public function test_reversal_blocked_on_day_31_statutory_cutoff_expired(): void
    {
        /** @var Member $member */
        [$lead, $member] = $this->createConvertedMember();

        // Set created_at to 31 days in past
        $member->created_at = Carbon::now()->subDays(31);
        $member->save();

        $canReverse = $this->reversalService->canReverse($member);
        $this->assertFalse($canReverse['allowed']);
        $this->assertArrayHasKey('cutoff', $canReverse['reasons']);

        $this->expectException(InvalidConversionReversalException::class);
        $this->expectExceptionMessage('Statutory 30-day cutoff expired');

        $this->reversalService->reverse(
            $member,
            'Attempting to reverse an ancient conversion after statutory window expired.',
            $this->manager
        );
    }

    public function test_reversal_blocked_if_justification_under_20_characters(): void
    {
        /** @var Member $member */
        [$lead, $member] = $this->createConvertedMember();

        $shortJustification = 'Wrong approval'; // 14 characters (< 20)
        $this->assertLessThan(20, mb_strlen($shortJustification));

        $this->expectException(InvalidConversionReversalException::class);
        $this->expectExceptionMessage('at least 20 characters');

        $this->reversalService->reverse($member, $shortJustification, $this->manager);
    }

    public function test_reversal_blocked_if_physical_card_was_marked_delivered(): void
    {
        /** @var Member $member */
        [$lead, $member] = $this->createConvertedMember();

        // Mark ID card as delivered to member
        IdCard::where('member_id', $member->id)->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        $canReverse = $this->reversalService->canReverse($member);
        $this->assertFalse($canReverse['allowed']);
        $this->assertArrayHasKey('card_delivered', $canReverse['reasons']);

        $this->expectException(InvalidConversionReversalException::class);
        $this->expectExceptionMessage('Physical membership card has already been delivered');

        $this->reversalService->reverse(
            $member,
            'Valid justification of at least twenty characters here.',
            $this->manager
        );
    }

    public function test_reversal_blocked_if_subsequent_invoice_exists(): void
    {
        /** @var Member $member */
        [$lead, $member] = $this->createConvertedMember();

        // Simulate subsequent invoice created 2 days after conversion
        Invoice::create([
            'workspace_id' => $member->workspace_id,
            'member_id' => $member->id,
            'lead_id' => null,
            'invoice_number' => 'INV-2026-99999',
            'amount_bdt' => 500.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'SUBSEQ1234',
            'status' => 'paid',
            'paid_at' => now()->addDays(2),
            'created_at' => Carbon::now()->addDays(2),
        ]);

        $canReverse = $this->reversalService->canReverse($member);
        $this->assertFalse($canReverse['allowed']);
        $this->assertArrayHasKey('subsequent_invoices', $canReverse['reasons']);

        $this->expectException(InvalidConversionReversalException::class);
        $this->expectExceptionMessage('Member has subsequent invoices or renewal transactions');

        $this->reversalService->reverse(
            $member,
            'Valid justification of at least twenty characters here.',
            $this->manager
        );
    }

    public function test_reversal_blocked_if_member_status_is_not_active(): void
    {
        /** @var Member $member */
        [$lead, $member] = $this->createConvertedMember();

        $member->status = 'archived_reversed';
        $member->save();

        $canReverse = $this->reversalService->canReverse($member);
        $this->assertFalse($canReverse['allowed']);
        $this->assertArrayHasKey('status', $canReverse['reasons']);

        $this->expectException(InvalidConversionReversalException::class);
        $this->expectExceptionMessage("Cannot reverse member in status 'archived_reversed'");

        $this->reversalService->reverse(
            $member,
            'Valid justification of at least twenty characters here.',
            $this->manager
        );
    }

    public function test_unauthorized_staff_cannot_reverse_conversion(): void
    {
        /** @var Member $member */
        [$lead, $member] = $this->createConvertedMember();

        $this->expectException(InvalidConversionReversalException::class);
        $this->expectExceptionMessage('leads.reverse_conversion');

        $this->reversalService->reverse(
            $member,
            'Valid justification of at least twenty characters attempted by staff.',
            $this->staff
        );
    }

    public function test_cross_workspace_manager_cannot_reverse_conversion(): void
    {
        /** @var Member $member */
        [$lead, $member] = $this->createConvertedMember();

        $this->expectException(InvalidConversionReversalException::class);
        $this->expectExceptionMessage('does not have access to workspace');

        $this->reversalService->reverse(
            $member,
            'Valid justification of at least twenty characters attempted across workspaces.',
            $this->crossWorkspaceManager
        );
    }
}
