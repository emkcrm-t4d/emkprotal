<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\IdCards\Services\IdCardLifecycleService;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Mail\CardReadyNotificationMail;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdCardNotificationTest extends TestCase
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
            'name' => 'Card Processing Staff',
            'email' => 'carddesk@emk.test',
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
            'name' => 'Nasrin Sultana',
            'email' => 'nasrin.sultana@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'member',
            'is_active' => true,
        ]);

        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'converted',
            'bkash_txid' => 'NOTIFBK001',
            'first_name' => 'Nasrin',
            'last_name' => 'Sultana',
            'email' => 'nasrin.sultana@emk.test',
            'contact_number' => '+8801700000003',
            'date_of_birth' => '1998-03-12',
            'age_at_submission' => 28,
            'sex' => 'female',
            'address_house_no' => '12/A',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Researcher',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'Master',
            'area_of_subject' => 'Social Science',
            'submitted_at' => Carbon::now(),
        ]);

        $this->member = Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => 'MEM-202611-0099',
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'user_id' => $portalUser->id,
            'membership_term_id' => $term->id,
            'status' => 'active',
            'first_name' => 'Nasrin',
            'last_name' => 'Sultana',
            'email' => 'nasrin.sultana@emk.test',
            'contact_number' => '+8801700000003',
            'date_of_birth' => '1998-03-12',
            'sex' => 'female',
            'address_house_no' => '12/A',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Researcher',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'Master',
            'area_of_subject' => 'Social Science',
            'start_date' => Carbon::now()->toDateString(),
            'expiry_date' => Carbon::now()->addYear()->toDateString(),
        ]);
    }

    public function test_card_ready_notification_mail_implements_should_queue(): void
    {
        $card = $this->service->createInitialCard($this->member);
        $mailable = new CardReadyNotificationMail($this->member, $card);

        $this->assertInstanceOf(ShouldQueue::class, $mailable);
    }

    public function test_transition_to_printed_queues_notification_email_to_member(): void
    {
        Mail::fake();

        $card = $this->service->createInitialCard($this->member);
        $this->service->markPrinted($card, 'BATCH-202611-01', $this->staff);

        Mail::assertQueued(CardReadyNotificationMail::class, function (CardReadyNotificationMail $mail) use ($card) {
            return $mail->hasTo('nasrin.sultana@emk.test')
                && $mail->member->id === $this->member->id
                && $mail->card->id === $card->id;
        });
    }

    public function test_card_ready_notification_mail_renders_pickup_instructions_and_credentials(): void
    {
        $card = $this->service->createInitialCard($this->member);
        $mailable = new CardReadyNotificationMail($this->member, $card);
        $rendered = $mailable->render();

        $this->assertStringContainsString('Nasrin Sultana', $rendered);
        $this->assertStringContainsString('MEM-202611-0099', $rendered);
        $this->assertStringContainsString('CRD-MEM-202611-0099-01', $rendered);
        $this->assertStringContainsString('Bring a valid government-issued photo ID', $rendered);
        $this->assertStringContainsString('EMK Center (American Spaces Bangladesh), Dhaka', $rendered);
        $this->assertStringContainsString('Sunday through Thursday', $rendered);
        $this->assertStringContainsString('9:30 AM', $rendered);
        $this->assertStringContainsString('info@emk.com.bd', $rendered);
    }
}
