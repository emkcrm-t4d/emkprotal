<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\IdCards\Services\IdCardLifecycleService;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Workspaces\Models\Workspace;
use App\Livewire\IdCards\IdCardDirectory;
use App\Mail\CardReadyNotificationMail;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class IdCardBatchOperationsTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $otherStaff;
    private User $noPermStaff;
    private Member $member;
    private Member $member2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $ctgSpace = DB::table('spaces')->where('code', 'CR-CTG')->first();
        Workspace::firstOrCreate(
            ['id' => 'ws-chattogram'],
            [
                'space_id' => $ctgSpace ? $ctgSpace->id : 1,
                'name' => 'Chattogram American Corner',
                'is_active' => true,
            ]
        );

        $this->staff = $this->makeStaff('ws-emk-dhaka', 'frontdesk@emk.test', 'delegated_staff');
        $this->otherStaff = $this->makeStaff('ws-chattogram', 'ctg.desk@emk.test', 'delegated_staff');
        $this->noPermStaff = $this->makeStaff('ws-emk-dhaka', 'noperm@emk.test', null);

        $term = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-emk-dhaka', 'code' => 'term_standard_1yr'],
            ['name' => 'Standard 1-Year Membership', 'duration_months' => 12, 'fee_bdt' => 1000.00, 'is_active' => true]
        );

        $this->member = $this->makeMember($term, 'EMK-2026-00101', 'Rahim', 'Ahmed', 'rahim101@example.com');
        $this->member2 = $this->makeMember($term, 'EMK-2026-00102', '=SUM(A1)', 'Karim', 'karim102@example.com');
    }

    private function makeStaff(string $ws, string $email, ?string $role): User
    {
        $u = User::create([
            'workspace_id' => $ws,
            'name' => 'Staff ' . $email,
            'email' => $email,
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        if ($role) {
            $u->assignRole($role);
        }

        return $u;
    }

    private function makeMember(MembershipTerm $term, string $memberId, string $first, string $last, string $email): Member
    {
        $portalUser = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => "$first $last",
            'email' => $email,
            'password' => bcrypt('secret123'),
            'guard' => 'member',
            'is_active' => true,
        ]);

        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'converted',
            'bkash_txid' => 'TX' . $memberId,
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'contact_number' => '+8801711111111',
            'date_of_birth' => '1998-02-20',
            'age_at_submission' => 28,
            'sex' => 'male',
            'address_house_no' => '5',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Engineer',
            'institution_study' => 'BUET',
            'highest_certification' => 'Bachelor',
            'area_of_subject' => 'CSE',
            'has_parental_consent' => false,
            'terms_agreed' => true,
            'submitted_at' => Carbon::now(),
        ]);

        return Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => $memberId,
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'user_id' => $portalUser->id,
            'membership_term_id' => $term->id,
            'status' => 'active',
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'contact_number' => '+8801711111111',
            'date_of_birth' => '1998-02-20',
            'sex' => 'male',
            'address_house_no' => '5',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Engineer',
            'institution_study' => 'BUET',
            'highest_certification' => 'Bachelor',
            'area_of_subject' => 'CSE',
            'start_date' => Carbon::now()->subMonths(2)->toDateString(),
            'expiry_date' => Carbon::now()->addMonths(10)->toDateString(),
        ]);
    }

    public function test_staff_with_manage_cards_can_view_panel(): void
    {
        $this->actingAs($this->staff, 'web')->get('/crm/ws-emk-dhaka/id-cards')->assertOk();
    }

    public function test_staff_without_permission_is_forbidden(): void
    {
        $this->actingAs($this->noPermStaff, 'web')->get('/crm/ws-emk-dhaka/id-cards')->assertForbidden();
    }

    public function test_cross_workspace_request_is_forbidden(): void
    {
        $this->actingAs($this->otherStaff, 'web')->get('/crm/ws-emk-dhaka/id-cards')->assertForbidden();
    }

    public function test_batch_export_generates_sanitized_csv_and_audit_event(): void
    {
        $svc = new IdCardLifecycleService();
        $c1 = $svc->createInitialCard($this->member);
        $c2 = $svc->createInitialCard($this->member2);

        $this->actingAs($this->staff, 'web');

        Livewire::test(IdCardDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->set('selectedCardIds', [$c1->id, $c2->id])
            ->call('exportBatchForPrinting')
            ->assertFileDownloaded();

        $log = AuditLog::where('event_type', AuditLog::EVENT_ID_CARDS_BATCH_EXPORTED)->first();
        $this->assertNotNull($log);
        $this->assertSame(2, $log->new_values['exported_rows']);
        $this->assertMatchesRegularExpression('/^BATCH-\d{6}-\d+$/', $log->new_values['batch_reference']);

        $component = new IdCardDirectory();
        $this->assertSame("'=SUM(A1)", $component->sanitizeCsvCell('=SUM(A1)'));
        $this->assertSame("'@x", $component->sanitizeCsvCell('@x'));
        $this->assertSame('plain', $component->sanitizeCsvCell('plain'));
    }

    public function test_mark_selected_as_printed_advances_cards_and_queues_emails(): void
    {
        Mail::fake();
        $svc = new IdCardLifecycleService();
        $c1 = $svc->createInitialCard($this->member);
        $c2 = $svc->createInitialCard($this->member2);

        $this->actingAs($this->staff, 'web');

        Livewire::test(IdCardDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->set('selectedCardIds', [$c1->id, $c2->id])
            ->call('markSelectedAsPrinted', 'BATCH-202611-002')
            ->assertSet('selectedCardIds', []);

        foreach ([$c1, $c2] as $c) {
            $c->refresh();
            $this->assertSame('card_printed_ready_for_pickup', $c->status);
            $this->assertSame('BATCH-202611-002', $c->batch_reference);
        }
        Mail::assertQueued(CardReadyNotificationMail::class, 2);
    }

    public function test_front_desk_delivery_marks_delivered_with_staff_id(): void
    {
        Mail::fake();
        $svc = new IdCardLifecycleService();
        $card = $svc->markPrinted($svc->createInitialCard($this->member), 'B1', $this->staff);

        $this->actingAs($this->staff, 'web');

        Livewire::test(IdCardDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('deliverCard', $card->id);

        $card->refresh();
        $this->assertSame('delivered', $card->status);
        $log = AuditLog::where('event_type', AuditLog::EVENT_ID_CARD_DELIVERED)->latest('id')->first();
        $this->assertSame($this->staff->id, $log->new_values['delivering_staff_id']);
    }

    public function test_cannot_deliver_card_from_other_workspace(): void
    {
        $card = IdCard::create([
            'workspace_id' => 'ws-chattogram',
            'member_id' => $this->member->id,
            'card_number' => 'CRD-X-01',
            'status' => 'card_printed_ready_for_pickup',
        ]);

        $this->actingAs($this->staff, 'web');

        Livewire::test(IdCardDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('deliverCard', $card->id)
            ->assertNotFound();

        $this->assertSame('card_printed_ready_for_pickup', $card->fresh()->status);
    }
}
