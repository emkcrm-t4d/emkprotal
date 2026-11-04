<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Workspaces\Models\Workspace;
use App\Livewire\Invoices\InvoiceDirectory;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class InvoiceDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $staffWithView;
    private User $staffWithoutView;
    private User $otherWorkspaceStaff;
    private Member $member;
    private Invoice $invoicePaid;
    private Invoice $invoiceVoided;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        // Ensure other workspace exists
        $ctgSpace = DB::table('spaces')->where('code', 'CR-CTG')->first();
        Workspace::firstOrCreate(
            ['id' => 'ws-chattogram'],
            [
                'space_id' => $ctgSpace ? $ctgSpace->id : 1,
                'name' => 'Chattogram American Corner',
                'is_active' => true,
            ]
        );

        // 1. Manager in ws-emk-dhaka (has invoices.view, invoices.export, invoices.generate)
        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Billing Manager',
            'email' => 'manager.billing@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');

        // 2. Staff in ws-emk-dhaka with invoices.view & invoices.export
        $this->staffWithView = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Staff With View',
            'email' => 'staff.view@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staffWithView->assignRole('delegated_staff');
        $this->staffWithView->givePermissionTo(['invoices.view', 'invoices.export']);

        // 3. Staff in ws-emk-dhaka without invoices.view (no role or permissions assigned)
        $this->staffWithoutView = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Staff Without View',
            'email' => 'staff.noview@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);

        // 4. Staff in ws-chattogram
        $this->otherWorkspaceStaff = User::create([
            'workspace_id' => 'ws-chattogram',
            'name' => 'Chattogram Staff',
            'email' => 'staff.ctg@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->otherWorkspaceStaff->assignRole('workspace_manager');

        // Term & Lead & Member
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
            'name' => 'Rahim Ahmed',
            'email' => 'rahim.ahmed@example.com',
            'password' => bcrypt('secret123'),
            'guard' => 'member',
            'is_active' => true,
        ]);

        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'converted',
            'bkash_txid' => 'BKASH99001',
            'first_name' => 'Rahim',
            'last_name' => 'Ahmed',
            'email' => 'rahim.ahmed@example.com',
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
            'member_id' => 'EMK-2026-00010',
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'user_id' => $portalUser->id,
            'membership_term_id' => $term->id,
            'status' => 'active',
            'first_name' => 'Rahim',
            'last_name' => 'Ahmed',
            'email' => 'rahim.ahmed@example.com',
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

        // Create Paid Invoice
        $this->invoicePaid = Invoice::create([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $this->member->id,
            'invoice_number' => 'INV-2026-00010',
            'amount_bdt' => 1000.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'BKASHPAID1',
            'status' => 'paid',
            'paid_at' => Carbon::now()->subDays(5),
        ]);

        // Create Voided Invoice
        $this->invoiceVoided = Invoice::create([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $this->member->id,
            'invoice_number' => 'INV-2026-00011',
            'amount_bdt' => 1000.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'BKASHVOID1',
            'status' => 'voided',
            'paid_at' => Carbon::now()->subDays(10),
        ]);
    }

    public function test_unauthenticated_request_redirects_or_rejects(): void
    {
        $response = $this->get('/crm/ws-emk-dhaka/invoices');
        $response->assertRedirect('/login');
    }

    public function test_staff_without_invoices_view_receives_403(): void
    {
        $response = $this->actingAs($this->staffWithoutView)
            ->get('/crm/ws-emk-dhaka/invoices');

        $response->assertStatus(403);
    }

    public function test_cross_workspace_staff_receives_403(): void
    {
        $response = $this->actingAs($this->otherWorkspaceStaff)
            ->get('/crm/ws-emk-dhaka/invoices');

        $response->assertStatus(403);
    }

    public function test_authorized_staff_can_view_invoices_index(): void
    {
        $response = $this->actingAs($this->staffWithView)
            ->get('/crm/ws-emk-dhaka/invoices');

        $response->assertStatus(200);
        $response->assertSee('Billing &amp; Invoice Records', false);
        $response->assertSee('INV-2026-00010');
        $response->assertSee('BKASHPAID1');
        $response->assertSee('Rahim Ahmed');
    }

    public function test_filtering_by_status_isolates_invoices(): void
    {
        Livewire::actingAs($this->staffWithView)
            ->test(InvoiceDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->set('statusFilter', 'paid')
            ->assertSee('INV-2026-00010')
            ->assertDontSee('INV-2026-00011')
            ->set('statusFilter', 'voided')
            ->assertSee('INV-2026-00011')
            ->assertDontSee('INV-2026-00010');
    }

    public function test_search_filters_by_invoice_number_and_bkash_txid(): void
    {
        Livewire::actingAs($this->staffWithView)
            ->test(InvoiceDirectory::class, ['workspace' => 'ws-emk-dhaka'])
            ->set('search', 'BKASHPAID1')
            ->assertSee('INV-2026-00010')
            ->assertDontSee('INV-2026-00011')
            ->set('search', 'EMK-2026-00010')
            ->assertSee('INV-2026-00010');
    }

    public function test_csv_export_generates_sanitized_stream_and_writes_audit_log(): void
    {
        // Add an invoice with formula injection trigger character '='
        Invoice::create([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $this->member->id,
            'invoice_number' => 'INV-2026-00012',
            'amount_bdt' => 1000.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => '=SUM(A1:A10)',
            'status' => 'paid',
            'paid_at' => Carbon::now(),
        ]);

        $component = Livewire::actingAs($this->staffWithView)
            ->test(InvoiceDirectory::class, ['workspace' => 'ws-emk-dhaka']);

        $response = $component->call('exportCsv');
        $response->assertStatus(200);

        // Verify audit log entry
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->staffWithView->id,
            'event_type' => AuditLog::EVENT_INVOICES_EXPORTED,
            'auditable_type' => Invoice::class,
        ]);
    }

    public function test_formula_sanitization_helper(): void
    {
        $component = new InvoiceDirectory();

        $this->assertSame("'=1+2", $component->sanitizeCsvCell('=1+2'));
        $this->assertSame("'+cmd", $component->sanitizeCsvCell('+cmd'));
        $this->assertSame("'-calc", $component->sanitizeCsvCell('-calc'));
        $this->assertSame("'@eval", $component->sanitizeCsvCell('@eval'));
        $this->assertSame("Safe String", $component->sanitizeCsvCell('Safe String'));
        $this->assertSame("1000.00", $component->sanitizeCsvCell('1000.00'));
    }
}
