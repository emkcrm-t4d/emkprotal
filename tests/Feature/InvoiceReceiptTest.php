<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Workspaces\Models\Workspace;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $staffWithView;
    private User $staffWithoutView;
    private User $otherWorkspaceStaff;
    private Member $member;
    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        // Ensure second workspace exists for cross-workspace isolation tests
        $ctgSpace = DB::table('spaces')->where('code', 'CR-CTG')->first();
        Workspace::firstOrCreate(
            ['id' => 'ws-chattogram'],
            [
                'space_id' => $ctgSpace ? $ctgSpace->id : 1,
                'name' => 'Chattogram American Corner',
                'is_active' => true,
            ]
        );

        // 1. Workspace Manager (ws-emk-dhaka)
        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Receipt Manager',
            'email' => 'manager.receipt@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');

        // 2. Staff with invoices.view (ws-emk-dhaka)
        $this->staffWithView = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Receipt Staff',
            'email' => 'staff.receipt@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staffWithView->assignRole('delegated_staff');
        $this->staffWithView->givePermissionTo('invoices.view');

        // 3. User without invoices.view
        $this->staffWithoutView = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'No View Staff',
            'email' => 'noview.receipt@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);

        // 4. Staff in other workspace (ws-chattogram)
        $this->otherWorkspaceStaff = User::create([
            'workspace_id' => 'ws-chattogram',
            'name' => 'Chattogram Staff',
            'email' => 'other.receipt@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->otherWorkspaceStaff->assignRole('workspace_manager');

        // Term, Lead, Member, Invoice
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
            'name' => 'Fatima Begum',
            'email' => 'fatima.begum@example.com',
            'password' => bcrypt('secret123'),
            'guard' => 'member',
            'is_active' => true,
        ]);

        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'converted',
            'bkash_txid' => 'TXBKASH888',
            'first_name' => 'Fatima',
            'last_name' => 'Begum',
            'email' => 'fatima.begum@example.com',
            'contact_number' => '+8801811111111',
            'date_of_birth' => '1999-07-10',
            'age_at_submission' => 27,
            'sex' => 'female',
            'address_house_no' => '42',
            'address_area' => 'Banani',
            'address_zip_code' => '1213',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Architect',
            'institution_study' => 'BRAC University',
            'highest_certification' => 'Bachelor',
            'area_of_subject' => 'Architecture',
            'has_parental_consent' => false,
            'terms_agreed' => true,
            'submitted_at' => Carbon::now(),
        ]);

        $this->member = Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => 'EMK-2026-00088',
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'user_id' => $portalUser->id,
            'membership_term_id' => $term->id,
            'status' => 'active',
            'first_name' => 'Fatima',
            'last_name' => 'Begum',
            'email' => 'fatima.begum@example.com',
            'contact_number' => '+8801811111111',
            'date_of_birth' => '1999-07-10',
            'sex' => 'female',
            'address_house_no' => '42',
            'address_area' => 'Banani',
            'address_zip_code' => '1213',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Architect',
            'institution_study' => 'BRAC University',
            'highest_certification' => 'Bachelor',
            'area_of_subject' => 'Architecture',
            'start_date' => Carbon::now()->subMonths(1)->toDateString(),
            'expiry_date' => Carbon::now()->addMonths(11)->toDateString(),
        ]);

        $this->invoice = Invoice::create([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $this->member->id,
            'lead_id' => $lead->id,
            'invoice_number' => 'INV-2026-00088',
            'amount_bdt' => 1000.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'TXBKASH888',
            'status' => 'paid',
            'paid_at' => Carbon::now()->subDays(2),
        ]);
    }

    public function test_unauthenticated_request_is_redirected(): void
    {
        $response = $this->get("/crm/ws-emk-dhaka/invoices/{$this->invoice->id}/receipt");
        $response->assertRedirect('/login');
    }

    public function test_staff_without_invoices_view_permission_is_forbidden(): void
    {
        $response = $this->actingAs($this->staffWithoutView)
            ->get("/crm/ws-emk-dhaka/invoices/{$this->invoice->id}/receipt");

        $response->assertStatus(403);
    }

    public function test_cross_workspace_receipt_request_is_rejected_with_403(): void
    {
        // Staff from ws-chattogram attempting to access ws-emk-dhaka invoice
        $response = $this->actingAs($this->otherWorkspaceStaff)
            ->get("/crm/ws-emk-dhaka/invoices/{$this->invoice->id}/receipt");

        $response->assertStatus(403);
    }

    public function test_cross_workspace_route_mismatch_returns_403(): void
    {
        // Accessing ws-emk-dhaka invoice via ws-chattogram route
        $response = $this->actingAs($this->otherWorkspaceStaff)
            ->get("/crm/ws-chattogram/invoices/{$this->invoice->id}/receipt");

        $response->assertStatus(403);
    }

    public function test_authorized_staff_can_view_receipt(): void
    {
        $response = $this->actingAs($this->staffWithView)
            ->get("/crm/ws-emk-dhaka/invoices/{$this->invoice->id}/receipt");

        $response->assertStatus(200);
        $response->assertSee('INV-2026-00088');
        $response->assertSee('EMK-2026-00088');
        $response->assertSee('Fatima Begum');
        $response->assertSee('TXBKASH888');
        $response->assertSee('1,000.00');
        $response->assertSee('One Thousand Taka Only');
        $response->assertSee('Official Money Receipt');
        $response->assertSee('PAID');
    }

    public function test_manager_can_view_receipt(): void
    {
        $response = $this->actingAs($this->manager)
            ->get("/crm/ws-emk-dhaka/invoices/{$this->invoice->id}/receipt");

        $response->assertStatus(200);
        $response->assertSee('INV-2026-00088');
    }
}
