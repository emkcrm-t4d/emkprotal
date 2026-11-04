<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Models\MemberChangeRequest;
use App\Domain\Members\Services\MemberRenewalService;
use App\Livewire\Portal\Modals\SubmitRenewalModal;
use App\Models\MemberUser;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SpaceAndWorkspaceSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class MemberRenewalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SpaceAndWorkspaceSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function createMemberFixture(
        string $status = 'active',
        ?Carbon $expiryDate = null,
        string $email = 'renewal.member@example.com',
        string $memberId = 'MEM-DHK-2026-0001',
        string $workspaceId = 'ws-emk-dhaka'
    ): array {
        $term = MembershipTerm::firstOrCreate(
            ['workspace_id' => $workspaceId, 'code' => 'REG-1YR'],
            [
                'name' => '1 Year Regular Membership',
                'duration_months' => 12,
                'fee_bdt' => 500.00,
                'is_active' => true,
            ]
        );

        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'target_space_code' => 'EMK-DHK',
            'status' => 'converted',
            'bkash_txid' => 'TXID' . strtoupper(Str::random(6)),
            'first_name' => 'Jane',
            'middle_name' => null,
            'last_name' => 'Renew',
            'email' => $email,
            'contact_number' => '+8801711112222',
            'date_of_birth' => '1998-05-15',
            'age_at_submission' => 28,
            'sex' => 'female',
            'address_house_no' => '45/B',
            'address_road_no' => 'Road 7',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'professional',
            'institution_study' => 'BRAC University',
            'highest_certification' => 'bachelor',
            'area_of_subject' => 'Business',
            'submitted_at' => now(),
        ]);

        $memberUser = MemberUser::create([
            'workspace_id' => $workspaceId,
            'name' => 'Jane Renew',
            'email' => $email,
            'password' => Hash::make('MemberPass123!'),
            'guard' => 'member',
            'is_active' => true,
            'must_reset_password' => false,
        ]);
        $memberUser->assignRole('member');

        $effectiveExpiry = $expiryDate ?? Carbon::today()->addDays(60);

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => $memberId,
            'workspace_id' => $workspaceId,
            'lead_id' => $lead->id,
            'user_id' => $memberUser->id,
            'membership_term_id' => $term->id,
            'status' => $status,
            'first_name' => 'Jane',
            'middle_name' => null,
            'last_name' => 'Renew',
            'email' => $email,
            'contact_number' => '+8801711112222',
            'date_of_birth' => '1998-05-15',
            'sex' => 'female',
            'address_house_no' => '45/B',
            'address_road_no' => 'Road 7',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'professional',
            'institution_study' => 'BRAC University',
            'highest_certification' => 'bachelor',
            'area_of_subject' => 'Business',
            'start_date' => Carbon::today()->subMonths(10)->toDateString(),
            'expiry_date' => $effectiveExpiry->toDateString(),
        ]);

        return compact('member', 'memberUser', 'lead', 'term');
    }

    public function test_renewal_window_eligibility_logic(): void
    {
        $service = new MemberRenewalService();

        // 1. Active with 60 days remaining: CLOSED
        $fixture1 = $this->createMemberFixture('active', Carbon::today()->addDays(60), 'm1@emk.test', 'MEM-0001');
        $this->assertFalse($service->isRenewalWindowOpen($fixture1['member']));

        // 2. Active with 31 days remaining: CLOSED
        $fixture2 = $this->createMemberFixture('active', Carbon::today()->addDays(31), 'm2@emk.test', 'MEM-0002');
        $this->assertFalse($service->isRenewalWindowOpen($fixture2['member']));

        // 3. Active with 30 days remaining: OPEN
        $fixture3 = $this->createMemberFixture('active', Carbon::today()->addDays(30), 'm3@emk.test', 'MEM-0003');
        $this->assertTrue($service->isRenewalWindowOpen($fixture3['member']));

        // 4. Active with 5 days remaining: OPEN
        $fixture4 = $this->createMemberFixture('active', Carbon::today()->addDays(5), 'm4@emk.test', 'MEM-0004');
        $this->assertTrue($service->isRenewalWindowOpen($fixture4['member']));

        // 5. Inactive (expired): OPEN
        $fixture5 = $this->createMemberFixture('inactive', Carbon::today()->subDays(10), 'm5@emk.test', 'MEM-0005');
        $this->assertTrue($service->isRenewalWindowOpen($fixture5['member']));

        // 6. Revoked: CLOSED
        $fixture6 = $this->createMemberFixture('revoked', Carbon::today()->addDays(5), 'm6@emk.test', 'MEM-0006');
        $this->assertFalse($service->isRenewalWindowOpen($fixture6['member']));
    }

    public function test_active_member_with_more_than_30_days_remaining_cannot_submit_renewal(): void
    {
        $service = new MemberRenewalService();
        $fixture = $this->createMemberFixture('active', Carbon::today()->addDays(45));

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Renewal window is not open.');

        $service->submitRenewalRequest($fixture['member'], $fixture['term']->id, 'BL9X4K2P9Q');
    }

    public function test_active_member_within_30_days_submits_renewal_successfully(): void
    {
        $service = new MemberRenewalService();
        $fixture = $this->createMemberFixture('active', Carbon::today()->addDays(20));

        $txid = 'BL9X4K2P9Q';
        $request = $service->submitRenewalRequest($fixture['member'], $fixture['term']->id, $txid);

        $this->assertInstanceOf(MemberChangeRequest::class, $request);
        $this->assertEquals(MemberChangeRequest::TYPE_RENEWAL, $request->request_type);
        $this->assertEquals(MemberChangeRequest::STATUS_PENDING, $request->status);
        $this->assertEquals($txid, $request->new_payload['bkash_txid']);
        $this->assertEquals($fixture['term']->id, $request->new_payload['term_id']);

        $this->assertDatabaseHas('audit_logs', [
            'event_type' => AuditLog::EVENT_MEMBER_RENEWAL_SUBMITTED,
            'auditable_type' => MemberChangeRequest::class,
            'auditable_id' => $request->id,
        ]);
    }

    public function test_inactive_expired_member_submits_renewal_successfully(): void
    {
        $service = new MemberRenewalService();
        $fixture = $this->createMemberFixture('inactive', Carbon::today()->subDays(15));

        $txid = 'EXP1234567';
        $request = $service->submitRenewalRequest($fixture['member'], $fixture['term']->id, $txid);

        $this->assertInstanceOf(MemberChangeRequest::class, $request);
        $this->assertEquals(MemberChangeRequest::TYPE_RENEWAL, $request->request_type);
        $this->assertEquals(MemberChangeRequest::STATUS_PENDING, $request->status);
        $this->assertEquals($txid, $request->new_payload['bkash_txid']);
    }

    public function test_submitting_duplicate_pending_renewal_request_is_blocked(): void
    {
        $service = new MemberRenewalService();
        $fixture = $this->createMemberFixture('active', Carbon::today()->addDays(15));

        $service->submitRenewalRequest($fixture['member'], $fixture['term']->id, 'TX12345678');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('A renewal request is already pending review');

        $service->submitRenewalRequest($fixture['member'], $fixture['term']->id, 'TX87654321');
    }

    public function test_10_character_uppercase_bkash_txid_regex_is_strictly_enforced(): void
    {
        $service = new MemberRenewalService();
        $fixture = $this->createMemberFixture('active', Carbon::today()->addDays(10));

        // 9 characters -> fails
        try {
            $service->submitRenewalRequest($fixture['member'], $fixture['term']->id, 'SHORT9CH');
            $this->fail('Expected exception on 9-char bKash TxID');
        } catch (DomainException $e) {
            $this->assertStringContainsString('bKash Transaction ID must be exactly 10', $e->getMessage());
        }

        // 11 characters -> fails
        try {
            $service->submitRenewalRequest($fixture['member'], $fixture['term']->id, 'LONG11CHARS');
            $this->fail('Expected exception on 11-char bKash TxID');
        } catch (DomainException $e) {
            $this->assertStringContainsString('bKash Transaction ID must be exactly 10', $e->getMessage());
        }

        // Lowercase characters -> fails
        try {
            $service->submitRenewalRequest($fixture['member'], $fixture['term']->id, 'txid123456');
            $this->fail('Expected exception on lowercase bKash TxID');
        } catch (DomainException $e) {
            $this->assertStringContainsString('bKash Transaction ID must be exactly 10', $e->getMessage());
        }

        // Special characters -> fails
        try {
            $service->submitRenewalRequest($fixture['member'], $fixture['term']->id, 'TXID@12345');
            $this->fail('Expected exception on special characters in bKash TxID');
        } catch (DomainException $e) {
            $this->assertStringContainsString('bKash Transaction ID must be exactly 10', $e->getMessage());
        }
    }

    public function test_duplicate_bkash_txid_in_invoices_or_leads_is_rejected(): void
    {
        $service = new MemberRenewalService();
        $fixture = $this->createMemberFixture('active', Carbon::today()->addDays(10));

        // 1. Used in Lead
        Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'LEADTXID01',
            'first_name' => 'Other',
            'last_name' => 'Person',
            'email' => 'other@emk.test',
            'contact_number' => '+8801700000001',
            'date_of_birth' => '1990-01-01',
            'age_at_submission' => 36,
            'sex' => 'male',
            'address_house_no' => '1',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'other',
            'institution_study' => 'NA',
            'highest_certification' => 'hsc',
            'area_of_subject' => 'NA',
            'submitted_at' => now(),
        ]);

        try {
            $service->submitRenewalRequest($fixture['member'], $fixture['term']->id, 'LEADTXID01');
            $this->fail('Expected exception on duplicate Lead bKash TxID');
        } catch (DomainException $e) {
            $this->assertStringContainsString('already been submitted or verified', $e->getMessage());
        }

        // 2. Used in Invoice
        Invoice::create([
            'invoice_number' => 'INV-2026-99999',
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $fixture['member']->id,
            'amount_bdt' => 500.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'INVTXID001',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        try {
            $service->submitRenewalRequest($fixture['member'], $fixture['term']->id, 'INVTXID001');
            $this->fail('Expected exception on duplicate Invoice payment reference');
        } catch (DomainException $e) {
            $this->assertStringContainsString('already been submitted or verified', $e->getMessage());
        }
    }

    public function test_submit_renewal_modal_renders_and_submits_via_livewire(): void
    {
        $fixture = $this->createMemberFixture('active', Carbon::today()->addDays(15));

        Livewire::actingAs($fixture['memberUser'], 'member')
            ->test(SubmitRenewalModal::class, ['member' => $fixture['member']])
            ->assertSet('isRenewalOpen', true)
            ->assertSet('hasPendingRequest', false)
            ->call('openModal')
            ->assertSet('isOpen', true)
            ->set('selected_term_id', $fixture['term']->id)
            ->set('bkash_txid', 'lwtxid1234') // input lowercase
            ->assertSet('bkash_txid', 'LWTXID1234') // automatically uppercased
            ->call('submit')
            ->assertSet('isOpen', false)
            ->assertSet('hasPendingRequest', true)
            ->assertDispatched('renewal-submitted');

        $this->assertDatabaseHas('member_change_requests', [
            'member_id' => $fixture['member']->id,
            'request_type' => 'renewal',
            'status' => 'pending',
        ]);
    }

    public function test_submit_renewal_modal_blocks_submission_when_window_closed(): void
    {
        $fixture = $this->createMemberFixture('active', Carbon::today()->addDays(50));

        Livewire::actingAs($fixture['memberUser'], 'member')
            ->test(SubmitRenewalModal::class, ['member' => $fixture['member']])
            ->assertSet('isRenewalOpen', false)
            ->call('openModal')
            ->assertSet('isOpen', true)
            ->set('selected_term_id', $fixture['term']->id)
            ->set('bkash_txid', 'BL9X4K2P9Q')
            ->call('submit')
            ->assertSet('isOpen', true)
            ->assertSee('Renewal window is not open');
    }
}
