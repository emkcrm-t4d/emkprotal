<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\IdCards\Services\IdCardLifecycleService;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Livewire\Members\RequestCardReplacementModal;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class IdCardReplacementTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;
    private User $noPerm;
    private Member $member;
    private IdCardLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->staff = $this->makeUser('desk@emk.test', 'delegated_staff', 'web');
        $this->noPerm = $this->makeUser('noperm@emk.test', null, 'web');
        $portal = $this->makeUser('member301@example.com', null, 'member');

        $term = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-emk-dhaka', 'code' => 'term_standard_1yr'],
            ['name' => 'Standard', 'duration_months' => 12, 'fee_bdt' => 1000.00, 'is_active' => true]
        );

        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'converted',
            'bkash_txid' => 'TXREPL0001',
            'first_name' => 'Ali',
            'last_name' => 'Reza',
            'email' => 'member301@example.com',
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

        $this->member = Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => 'EMK-2026-00301',
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'user_id' => $portal->id,
            'membership_term_id' => $term->id,
            'status' => 'active',
            'first_name' => 'Ali',
            'last_name' => 'Reza',
            'email' => 'member301@example.com',
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

        $this->service = new IdCardLifecycleService();
    }

    private function makeUser(string $email, ?string $role, string $guard): User
    {
        $u = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('secret123'),
            'guard' => $guard,
            'is_active' => true,
        ]);
        if ($role) {
            $u->assignRole($role);
        }

        return $u;
    }

    public function test_replacement_requires_valid_reason(): void
    {
        $this->service->createInitialCard($this->member);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->replaceCard($this->member, 'BECAUSE', null, $this->staff);
    }

    public function test_modal_requires_reason_and_valid_txid_when_fee_charged(): void
    {
        $this->service->createInitialCard($this->member);
        $this->actingAs($this->staff, 'web');

        Livewire::test(RequestCardReplacementModal::class, ['workspace' => 'ws-emk-dhaka', 'member' => $this->member])
            ->call('openModal')
            ->call('submit')
            ->assertHasErrors(['replacementReason'])
            ->set('replacementReason', 'LOST_CARD')
            ->set('requiresFee', true)
            ->set('bkashTxId', 'short')
            ->call('submit')
            ->assertHasErrors(['bkashTxId']);

        $this->assertSame(1, IdCard::where('member_id', $this->member->id)->count());
    }

    public function test_modal_forbidden_without_manage_cards_permission(): void
    {
        $this->actingAs($this->noPerm, 'web');

        Livewire::test(RequestCardReplacementModal::class, ['workspace' => 'ws-emk-dhaka', 'member' => $this->member])
            ->call('openModal')
            ->assertForbidden();
    }

    public function test_replacement_supersedes_prior_card_and_creates_new_in_printing_queue(): void
    {
        $old = $this->service->markPrinted($this->service->createInitialCard($this->member), 'B1', $this->staff);

        $new = $this->service->replaceCard($this->member, 'LOST_CARD', null, $this->staff);

        $this->assertSame('superseded_replaced', $old->fresh()->status);
        $this->assertSame('provided_for_printing', $new->status);
        $this->assertSame('CRD-EMK-2026-00301-02', $new->card_number);
        $this->assertSame('LOST_CARD', $new->replacement_reason);
        $this->assertSame(0, Invoice::where('member_id', $this->member->id)->count());
        $this->assertSame(1, IdCard::where('member_id', $this->member->id)
            ->whereIn('status', ['provided_for_printing', 'card_printed_ready_for_pickup', 'delivered'])->count());
    }

    public function test_optional_fee_creates_invoice_with_unique_txid(): void
    {
        $this->service->createInitialCard($this->member);

        $this->service->replaceCard($this->member, 'DAMAGED_CARD', 'REPLFEE001', $this->staff);

        $invoice = Invoice::where('member_id', $this->member->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('REPLFEE001', $invoice->payment_reference);
        $this->assertSame('paid', $invoice->status);

        // Reusing the same TxID is rejected and rolls back the whole replacement
        $before = IdCard::where('member_id', $this->member->id)->count();
        try {
            $this->service->replaceCard($this->member, 'DAMAGED_CARD', 'REPLFEE001', $this->staff);
            $this->fail('Expected duplicate payment reference exception');
        } catch (\App\Domain\Invoices\Exceptions\DuplicatePaymentReferenceException) {
            $this->assertSame($before, IdCard::where('member_id', $this->member->id)->count());
            $this->assertSame(
                1,
                IdCard::where('member_id', $this->member->id)->where('status', 'provided_for_printing')->count()
            );
        }
    }

    public function test_replacement_writes_audit_event(): void
    {
        $this->service->createInitialCard($this->member);
        $new = $this->service->replaceCard($this->member, 'NAME_CHANGE', null, $this->staff);

        $log = AuditLog::where('event_type', 'id_card_replaced')->first();
        $this->assertNotNull($log);
        $this->assertSame($this->staff->id, $log->actor_id);
        $this->assertSame(['CRD-EMK-2026-00301-01'], $log->new_values['prior_card_numbers']);
        $this->assertSame($new->card_number, $log->new_values['new_card_number']);
        $this->assertSame('NAME_CHANGE', $log->new_values['replacement_reason']);
    }

    public function test_modal_submit_replaces_card(): void
    {
        $old = $this->service->createInitialCard($this->member);
        $this->actingAs($this->staff, 'web');

        Livewire::test(RequestCardReplacementModal::class, ['workspace' => 'ws-emk-dhaka', 'member' => $this->member])
            ->call('openModal')
            ->set('replacementReason', 'LOST_CARD')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertDispatched('id-card-replaced');

        $this->assertSame('superseded_replaced', $old->fresh()->status);
    }
}
