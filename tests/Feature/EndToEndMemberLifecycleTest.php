<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\IdCards\Models\IdCard;
use App\Domain\IdCards\Services\IdCardLifecycleService;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Services\InvoiceService;
use App\Domain\Leads\Models\Lead;
use App\Domain\Leads\Services\LeadConversionService;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Services\MemberStatusService;
use App\Domain\Members\Services\MembershipValidityEngine;
use App\Livewire\IdCards\IdCardDirectory;
use App\Livewire\Members\ManageTerms;
use App\Livewire\Members\MemberDirectory;
use App\Livewire\Members\MemberProfile;
use App\Livewire\Members\MemberStatusModal;
use App\Livewire\Members\RequestCardReplacementModal;
use App\Mail\CardReadyNotificationMail;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DefaultMembershipFormSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Holistic Phase 7 end-to-end suite covering MEMB-01 through MEMB-06.
 */
class EndToEndMemberLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $staff;
    private User $sylhetStaff;
    private User $admin;
    private MembershipTerm $term;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Mail::fake();
        Queue::fake();

        $this->seed(DatabaseSeeder::class);
        $this->seed(DefaultMembershipFormSeeder::class);

        $sylhetSpace = DB::table('spaces')->where('code', 'CR-SYL')->first();
        if ($sylhetSpace) {
            DB::table('workspaces')->updateOrInsert(
                ['id' => 'ws-corner-sylhet'],
                ['space_id' => $sylhetSpace->id, 'name' => 'American Corner Sylhet', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $this->manager = $this->makeUser('ml.manager@emk.test', 'ws-emk-dhaka', 'workspace_manager');
        $this->manager->givePermissionTo(['leads.view', 'leads.convert']);
        $this->staff = $this->makeUser('ml.staff@emk.test', 'ws-emk-dhaka', 'delegated_staff');
        $this->sylhetStaff = $this->makeUser('ml.sylhet@emk.test', 'ws-corner-sylhet', 'delegated_staff');
        $this->admin = $this->makeUser('ml.admin@emk.test', 'ws-emk-dhaka', 'global_admin');

        $this->term = MembershipTerm::where('workspace_id', 'ws-emk-dhaka')->where('code', 'std-1yr')->firstOrFail();
    }

    private function makeUser(string $email, string $ws, string $role): User
    {
        $u = User::create([
            'workspace_id' => $ws,
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $u->assignRole($role);

        return $u;
    }

    private function convertMember(string $email, string $txid, string $first = 'Mehedi'): Member
    {
        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'first_name' => $first,
            'last_name' => 'Hasan',
            'email' => $email,
            'contact_number' => '+8801755667799',
            'bkash_txid' => $txid,
            'date_of_birth' => '1998-12-01',
            'age_at_submission' => 28,
            'sex' => 'male',
            'address_house_no' => 'House 22',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'dhaka',
            'occupation' => 'entrepreneur',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Finance',
            'status' => 'approved',
            'payment_verification_status' => 'verified',
            'document_verification_status' => 'verified',
            'photo_verification_status' => 'verified',
            'terms_agreed' => true,
            'submitted_at' => now()->subDay(),
        ]);

        return app(LeadConversionService::class)->convert($lead, $this->term->id, $this->manager);
    }

    public function test_scenario_1_conversion_to_profile_and_invoice_tracer(): void
    {
        $member = $this->convertMember('s1.member@example.com', 'S1TXID0001');

        $this->assertDatabaseHas('members', ['id' => $member->id, 'status' => 'active']);

        $invoice = Invoice::where('member_id', $member->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame('paid', $invoice->status);

        $card = IdCard::where('member_id', $member->id)->first();
        $this->assertNotNull($card);
        $this->assertSame('provided_for_printing', $card->status);

        $this->actingAs($this->staff, 'web');

        $this->get('/crm/ws-emk-dhaka/members')->assertOk()->assertSee('Mehedi Hasan');

        $this->get('/crm/ws-emk-dhaka/members/' . $member->uuid)
            ->assertOk()
            ->assertSee($member->member_id)
            ->assertSee('Mehedi Hasan')
            ->assertSee('Originating bKash TxID')
            ->assertSee('Verified Identity Documents');

        Livewire::test(MemberProfile::class, ['workspace' => 'ws-emk-dhaka', 'member' => $member])
            ->call('setTab', 'invoices')
            ->assertSee($invoice->invoice_number)
            ->assertSee('S1TXID0001');
    }

    public function test_scenario_2_dynamic_terms_and_early_renewal(): void
    {
        // Manager configures a dynamic 12-month / 1000 BDT term
        Livewire::actingAs($this->manager, 'web')
            ->test(ManageTerms::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('openCreateModal')
            ->set('name', 'Dynamic 1-Year Term')
            ->set('code', 'dyn_1yr')
            ->set('durationMonths', '12')
            ->set('feeBdt', '1000.00')
            ->set('isActive', true)
            ->call('createTerm')
            ->assertHasNoErrors();

        $dynamic = MembershipTerm::where('workspace_id', 'ws-emk-dhaka')->where('code', 'dyn_1yr')->firstOrFail();
        $this->term = $dynamic;

        $engine = app(MembershipValidityEngine::class);
        $approval = Carbon::parse('2026-01-15');
        $initial = $engine->calculateInitialDates($dynamic, $approval);
        $this->assertSame('2026-01-15', $initial['start_date']->toDateString());
        $this->assertSame('2027-01-15', $initial['expiry_date']->toDateString());

        $member = $this->convertMember('s2.member@example.com', 'S2TXID0001');
        $this->assertSame(now()->toDateString(), $member->start_date->toDateString());
        $this->assertSame(now()->addMonthsNoOverflow(12)->toDateString(), $member->expiry_date->toDateString());

        // Member reaches renewal window: 20 days before expiry
        $oldStart = now()->subDays(345)->toDateString();
        $oldExpiry = now()->addDays(20);
        $member->update(['start_date' => $oldStart, 'expiry_date' => $oldExpiry->toDateString()]);
        $member->refresh();
        $this->assertTrue($engine->isWithinRenewalWindow($member));

        $invoice = app(InvoiceService::class)->generateRenewalInvoice($member, $dynamic, 'RENEWTX001', $this->manager);

        $member->refresh();
        $this->assertSame($oldStart, $member->start_date->toDateString());
        $this->assertSame(
            $oldExpiry->copy()->addMonthsNoOverflow(12)->toDateString(),
            $member->expiry_date->toDateString(),
            'Remaining 20 days must be preserved on early renewal.'
        );
        $this->assertSame('RENEWTX001', $invoice->payment_reference);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{10}$/', $invoice->payment_reference);
    }

    public function test_scenario_3_id_card_issuance_lifecycle(): void
    {
        $member = $this->convertMember('s3.member@example.com', 'S3TXID0001');
        $card = IdCard::where('member_id', $member->id)->firstOrFail();

        $this->actingAs($this->staff, 'web');

        Livewire::test(IdCardDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->set('selectedCardIds', [$card->id])
            ->call('exportBatchForPrinting')
            ->assertFileDownloaded();
        $this->assertNotNull(AuditLog::where('event_type', AuditLog::EVENT_ID_CARDS_BATCH_EXPORTED)->first());

        Livewire::test(IdCardDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->set('selectedCardIds', [$card->id])
            ->call('markSelectedAsPrinted', 'BATCH-202611-900');

        $this->assertSame('card_printed_ready_for_pickup', $card->fresh()->status);
        Mail::assertQueued(CardReadyNotificationMail::class);

        Livewire::test(IdCardDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->call('deliverCard', $card->id);

        $this->assertSame('delivered', $card->fresh()->status);
        $log = AuditLog::where('event_type', AuditLog::EVENT_ID_CARD_DELIVERED)->latest('id')->first();
        $this->assertSame($this->staff->id, $log->new_values['delivering_staff_id']);
    }

    public function test_scenario_4_id_card_replacement(): void
    {
        $member = $this->convertMember('s4.member@example.com', 'S4TXID0001');
        $old = IdCard::where('member_id', $member->id)->firstOrFail();

        $this->actingAs($this->staff, 'web');

        Livewire::test(RequestCardReplacementModal::class, ['workspace' => 'ws-emk-dhaka', 'member' => $member])
            ->call('openModal')
            ->set('replacementReason', 'LOST_CARD')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame('superseded_replaced', $old->fresh()->status);
        $new = IdCard::where('member_id', $member->id)->where('status', 'provided_for_printing')->first();
        $this->assertNotNull($new);
        $this->assertNotSame($old->card_number, $new->card_number);
        $this->assertNotNull(AuditLog::where('event_type', 'id_card_replaced')->first());
    }

    public function test_scenario_5_status_governance_and_revocation(): void
    {
        $member = $this->convertMember('s5.member@example.com', 'S5TXID0001');

        // Regular staff blocked (403)
        $this->actingAs($this->staff, 'web');
        Livewire::test(MemberStatusModal::class, ['workspace' => 'ws-emk-dhaka', 'member' => $member])
            ->call('openModal')
            ->assertForbidden();

        try {
            app(MemberStatusService::class)->transitionStatus(
                $member, 'revoked', 'DIR-1', 'Twenty plus characters of rationale text.', $this->staff
            );
            $this->fail('Staff must not revoke.');
        } catch (AuthorizationException) {
            $this->assertSame('active', $member->fresh()->status);
        }

        // Admin revokes
        $this->actingAs($this->admin, 'web');
        Livewire::test(MemberStatusModal::class, ['workspace' => 'ws-emk-dhaka', 'member' => $member])
            ->call('openModal')
            ->set('targetStatus', 'revoked')
            ->set('directiveRef', 'DIR-2026-EMK-077')
            ->set('rationale', 'Conduct review board decision, case 77.')
            ->call('submit')
            ->assertHasNoErrors();

        $this->assertSame('revoked', $member->fresh()->status);
        $portal = User::find($member->user_id);
        $this->assertFalse($portal->is_active);

        $log = AuditLog::where('event_type', 'member_status_changed')->first();
        $this->assertNotNull($log);
        $this->assertSame($this->admin->id, $log->actor_id);
        $this->assertSame('DIR-2026-EMK-077', $log->new_values['directive_ref']);
        $this->assertSame('revoked', $log->new_values['status']);
    }

    public function test_scenario_6_cross_workspace_isolation_defense(): void
    {
        $member = $this->convertMember('s6.member@example.com', 'S6TXID0001');
        $invoice = Invoice::where('member_id', $member->id)->firstOrFail();

        $this->actingAs($this->sylhetStaff, 'web');

        $this->get('/crm/ws-emk-dhaka/members')->assertForbidden();
        $this->get('/crm/ws-emk-dhaka/members/' . $member->uuid)->assertForbidden();
        $this->get("/crm/ws-emk-dhaka/invoices/{$invoice->id}/receipt")->assertForbidden();
        $this->get('/crm/ws-emk-dhaka/id-cards')->assertForbidden();

        Livewire::test(MemberProfile::class, ['workspace' => 'ws-corner-sylhet', 'member' => $member])
            ->assertForbidden();
    }
}
