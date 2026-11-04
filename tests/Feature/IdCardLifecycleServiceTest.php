<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\IdCards\Exceptions\InvalidCardStateTransitionException;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\IdCards\Services\IdCardLifecycleService;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdCardLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private IdCardLifecycleService $service;
    private User $staff;
    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->service = new IdCardLifecycleService();

        $this->staff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Front Desk Staff',
            'email' => 'frontdesk@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staff->assignRole('workspace_manager');

        $term = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-emk-dhaka', 'code' => 'term_standard_1yr'],
            [
                'name' => 'Standard 1-Year Membership',
                'duration_months' => 12,
                'fee_bdt' => 1000.00,
                'is_active' => true,
            ]
        );

        $portalUser = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Member For Card',
            'email' => 'card.member@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'member',
            'is_active' => true,
        ]);

        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'converted',
            'bkash_txid' => 'CARDTXID01',
            'first_name' => 'Card',
            'last_name' => 'Applicant',
            'email' => 'card.member@emk.test',
            'contact_number' => '+8801700000002',
            'date_of_birth' => '1996-06-20',
            'age_at_submission' => 29,
            'sex' => 'male',
            'address_house_no' => '44',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Professional',
            'institution_study' => 'BRAC University',
            'highest_certification' => 'Bachelor',
            'area_of_subject' => 'Computer Science',
            'submitted_at' => Carbon::now(),
        ]);

        $this->member = Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => 'MEM-202611-0088',
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'user_id' => $portalUser->id,
            'membership_term_id' => $term->id,
            'status' => 'active',
            'first_name' => 'Card',
            'last_name' => 'Applicant',
            'email' => 'card.member@emk.test',
            'contact_number' => '+8801700000002',
            'date_of_birth' => '1996-06-20',
            'sex' => 'male',
            'address_house_no' => '44',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Professional',
            'institution_study' => 'BRAC University',
            'highest_certification' => 'Bachelor',
            'area_of_subject' => 'Computer Science',
            'start_date' => Carbon::now()->toDateString(),
            'expiry_date' => Carbon::now()->addYear()->toDateString(),
        ]);
    }

    public function test_create_initial_card_assigns_provided_for_printing_and_unique_card_number(): void
    {
        $card = $this->service->createInitialCard($this->member);

        $this->assertInstanceOf(IdCard::class, $card);
        $this->assertEquals('provided_for_printing', $card->status);
        $this->assertEquals('CRD-MEM-202611-0088-01', $card->card_number);
        $this->assertEquals($this->member->workspace_id, $card->workspace_id);
        $this->assertEquals($this->member->id, $card->member_id);
        $this->assertNull($card->printed_at);
        $this->assertNull($card->delivered_at);

        // Subsequent card gets -02
        $secondCard = $this->service->createInitialCard($this->member);
        $this->assertEquals('CRD-MEM-202611-0088-02', $secondCard->card_number);
    }

    public function test_mark_printed_updates_status_sets_timestamp_and_writes_audit_event(): void
    {
        Mail::fake();

        $card = $this->service->createInitialCard($this->member);

        $printedCard = $this->service->markPrinted($card, 'BATCH-202611-01', $this->staff);

        $this->assertEquals('card_printed_ready_for_pickup', $printedCard->status);
        $this->assertNotNull($printedCard->printed_at);
        $this->assertEquals('BATCH-202611-01', $printedCard->batch_reference);

        // Verify audit log entry
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->staff->id,
            'event_type' => AuditLog::EVENT_ID_CARD_PRINTED,
            'auditable_type' => IdCard::class,
            'auditable_id' => $card->id,
        ]);
    }

    public function test_mark_delivered_updates_status_sets_timestamp_and_records_delivering_staff(): void
    {
        $card = $this->service->createInitialCard($this->member);
        $printedCard = $this->service->markPrinted($card, 'BATCH-202611-01', $this->staff);

        $deliveredCard = $this->service->markDelivered($printedCard, $this->staff);

        $this->assertEquals('delivered', $deliveredCard->status);
        $this->assertNotNull($deliveredCard->delivered_at);

        // Verify audit log entry
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->staff->id,
            'event_type' => AuditLog::EVENT_ID_CARD_DELIVERED,
            'auditable_type' => IdCard::class,
            'auditable_id' => $card->id,
        ]);
    }

    public function test_cannot_skip_printing_state_directly_to_delivered(): void
    {
        $card = $this->service->createInitialCard($this->member);
        $this->assertEquals('provided_for_printing', $card->status);

        $this->expectException(InvalidCardStateTransitionException::class);
        $this->expectExceptionMessage("Cannot mark card as delivered: card is currently in 'provided_for_printing' state");

        $this->service->markDelivered($card, $this->staff);
    }

    public function test_cannot_print_card_not_in_provided_for_printing_state(): void
    {
        $card = $this->service->createInitialCard($this->member);
        $printedCard = $this->service->markPrinted($card, 'BATCH-202611-01', $this->staff);

        $this->expectException(InvalidCardStateTransitionException::class);
        $this->expectExceptionMessage("Cannot mark card as printed: card is currently in 'card_printed_ready_for_pickup' state");

        $this->service->markPrinted($printedCard, 'BATCH-202611-02', $this->staff);
    }

    public function test_cannot_deliver_already_delivered_card(): void
    {
        $card = $this->service->createInitialCard($this->member);
        $printedCard = $this->service->markPrinted($card, 'BATCH-202611-01', $this->staff);
        $deliveredCard = $this->service->markDelivered($printedCard, $this->staff);

        $this->expectException(InvalidCardStateTransitionException::class);
        $this->expectExceptionMessage("Cannot mark card as delivered: card is currently in 'delivered' state");

        $this->service->markDelivered($deliveredCard, $this->staff);
    }
}
