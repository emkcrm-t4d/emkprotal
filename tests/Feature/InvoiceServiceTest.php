<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Invoices\Exceptions\DuplicatePaymentReferenceException;
use App\Domain\Invoices\Models\Invoice;
use App\Domain\Invoices\Services\InvoiceService;
use App\Domain\Leads\Models\Lead;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Services\MembershipValidityEngine;
use App\Domain\Workspaces\Models\Workspace;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $service;
    private User $manager;
    private User $staff;
    private MembershipTerm $term1Yr;
    private MembershipTerm $term2Yr;
    private Member $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->service = new InvoiceService(new MembershipValidityEngine());

        // Create Workspace Manager with invoices.generate / workspace_manager
        $this->manager = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Finance Manager',
            'email' => 'fin.manager@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->manager->assignRole('workspace_manager');

        // Create Regular Staff without invoices.generate
        $this->staff = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Regular Staff',
            'email' => 'regular.staff@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'web',
            'is_active' => true,
        ]);
        $this->staff->assignRole('delegated_staff');

        // Membership terms
        $this->term1Yr = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-emk-dhaka', 'code' => 'term_standard_1yr'],
            [
                'name' => 'Standard 1-Year Membership',
                'duration_months' => 12,
                'fee_bdt' => 1000.00,
                'is_active' => true,
            ]
        );

        $this->term2Yr = MembershipTerm::firstOrCreate(
            ['workspace_id' => 'ws-emk-dhaka', 'code' => 'term_extended_2yr'],
            [
                'name' => 'Extended 2-Year Membership',
                'duration_months' => 24,
                'fee_bdt' => 1800.00,
                'is_active' => true,
            ]
        );

        // Portal user for member
        $portalUser = User::create([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Test Member',
            'email' => 'member.invoice.test@emk.test',
            'password' => bcrypt('secret123'),
            'guard' => 'member',
            'is_active' => true,
        ]);

        // Create a lead record
        $lead = Lead::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'converted',
            'bkash_txid' => 'BKASH00001',
            'first_name' => 'Test',
            'last_name' => 'Member',
            'email' => 'member.invoice.test@emk.test',
            'contact_number' => '+8801700000001',
            'date_of_birth' => '1995-05-15',
            'age_at_submission' => 30,
            'sex' => 'male',
            'address_house_no' => '12',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'Bachelor',
            'area_of_subject' => 'Business',
            'has_parental_consent' => false,
            'terms_agreed' => true,
            'submitted_at' => Carbon::now(),
        ]);

        // Create member
        $this->member = Member::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => 'EMK-2026-00001',
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'user_id' => $portalUser->id,
            'membership_term_id' => $this->term1Yr->id,
            'status' => 'active',
            'first_name' => 'Test',
            'last_name' => 'Member',
            'email' => 'member.invoice.test@emk.test',
            'contact_number' => '+8801700000001',
            'date_of_birth' => '1995-05-15',
            'sex' => 'male',
            'address_house_no' => '12',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'Dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'Student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'Bachelor',
            'area_of_subject' => 'Business',
            'start_date' => Carbon::now()->subMonths(11)->toDateString(),
            'expiry_date' => Carbon::now()->addMonth()->toDateString(),
        ]);
    }

    public function test_generate_invoice_number_format_and_sequence(): void
    {
        $year = (int) Carbon::now()->year;

        $inv1 = $this->service->generateInvoiceNumber('ws-emk-dhaka');
        $this->assertSame(sprintf('INV-%04d-00001', $year), $inv1);

        // Create invoice with inv1
        Invoice::create([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $this->member->id,
            'invoice_number' => $inv1,
            'amount_bdt' => 1000.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'TXTEST0001',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $inv2 = $this->service->generateInvoiceNumber('ws-emk-dhaka');
        $this->assertSame(sprintf('INV-%04d-00002', $year), $inv2);
    }

    public function test_generate_post_conversion_invoice(): void
    {
        $invoice = $this->service->generatePostConversionInvoice(
            $this->member,
            $this->member->lead_id,
            $this->term1Yr,
            'TXCONV0001'
        );

        $this->assertNotNull($invoice->id);
        $this->assertSame('ws-emk-dhaka', $invoice->workspace_id);
        $this->assertSame($this->member->id, $invoice->member_id);
        $this->assertSame($this->member->lead_id, $invoice->lead_id);
        $this->assertEquals(1000.00, (float) $invoice->amount_bdt);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('TXCONV0001', $invoice->payment_reference);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{5}$/', $invoice->invoice_number);
    }

    public function test_generate_renewal_invoice_early_renewal(): void
    {
        $originalExpiry = Carbon::parse($this->member->expiry_date);
        $originalStart = Carbon::parse($this->member->start_date);

        $invoice = $this->service->generateRenewalInvoice(
            $this->member,
            $this->term1Yr,
            'TXRNW00001',
            $this->manager
        );

        $this->assertNotNull($invoice->id);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('TXRNW00001', $invoice->payment_reference);

        $this->member->refresh();
        $this->assertSame('active', $this->member->status);
        $this->assertSame($originalStart->toDateString(), $this->member->start_date->toDateString());
        // Expiry should be extended by 12 months from original expiry
        $expectedExpiry = $originalExpiry->copy()->addMonthsNoOverflow(12)->toDateString();
        $this->assertSame($expectedExpiry, $this->member->expiry_date->toDateString());

        // Check audit log
        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->manager->id,
            'event_type' => AuditLog::EVENT_MEMBERSHIP_RENEWED,
            'auditable_type' => Member::class,
            'auditable_id' => $this->member->id,
        ]);
    }

    public function test_generate_renewal_invoice_late_renewal(): void
    {
        // Member expired 2 months ago
        $this->member->update([
            'start_date' => Carbon::now()->subMonths(14)->toDateString(),
            'expiry_date' => Carbon::now()->subMonths(2)->toDateString(),
            'status' => 'inactive',
        ]);

        $invoice = $this->service->generateRenewalInvoice(
            $this->member,
            $this->term1Yr,
            'TXRNWLATE1',
            $this->manager
        );

        $this->member->refresh();
        $this->assertSame('active', $this->member->status);
        // Start date should reset to today
        $this->assertSame(Carbon::now()->toDateString(), $this->member->start_date->toDateString());
        // Expiry should be 12 months from today
        $expectedExpiry = Carbon::now()->addMonthsNoOverflow(12)->toDateString();
        $this->assertSame($expectedExpiry, $this->member->expiry_date->toDateString());
    }

    public function test_rejects_invalid_bkash_txid_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('bKash Transaction ID must be exactly 10 uppercase alphanumeric characters.');

        // 9 characters (too short)
        $this->service->generateRenewalInvoice(
            $this->member,
            $this->term1Yr,
            'SHORT9999',
            $this->manager
        );
    }

    public function test_rejects_lowercase_characters_in_bkash_txid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Lowercase characters
        $this->service->generateRenewalInvoice(
            $this->member,
            $this->term1Yr,
            'lowercase1',
            $this->manager
        );
    }

    public function test_rejects_duplicate_payment_reference_existing_in_leads(): void
    {
        $this->expectException(DuplicatePaymentReferenceException::class);
        $this->expectExceptionMessage('Payment reference [BKASH00001] has already been utilized.');

        // BKASH00001 was assigned to lead in setUp
        $this->service->generateRenewalInvoice(
            $this->member,
            $this->term1Yr,
            'BKASH00001',
            $this->manager
        );
    }

    public function test_rejects_duplicate_payment_reference_existing_in_invoices(): void
    {
        // Create an invoice with reference 'TXDUP00001'
        Invoice::create([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $this->member->id,
            'invoice_number' => 'INV-2026-99998',
            'amount_bdt' => 1000.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'TXDUP00001',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->expectException(DuplicatePaymentReferenceException::class);
        $this->expectExceptionMessage('Payment reference [TXDUP00001] has already been utilized.');

        $this->service->generateRenewalInvoice(
            $this->member,
            $this->term1Yr,
            'TXDUP00001',
            $this->manager
        );
    }

    public function test_allows_payment_reference_if_prior_invoice_is_voided(): void
    {
        // Create a voided invoice with reference 'TXVOIDED01'
        Invoice::create([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $this->member->id,
            'invoice_number' => 'INV-2026-99999',
            'amount_bdt' => 1000.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'TXVOIDED01',
            'status' => 'voided',
            'paid_at' => now(),
        ]);

        $invoice = $this->service->generateRenewalInvoice(
            $this->member,
            $this->term1Yr,
            'TXVOIDED01',
            $this->manager
        );

        $this->assertSame('TXVOIDED01', $invoice->payment_reference);
        $this->assertSame('paid', $invoice->status);
    }

    public function test_manager_can_void_invoice_with_audit_trail(): void
    {
        $invoice = Invoice::create([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $this->member->id,
            'invoice_number' => 'INV-2026-00050',
            'amount_bdt' => 1000.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'TXTOVOID01',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $voidedInvoice = $this->service->voidInvoice(
            $invoice,
            'Accidental duplicate entry by staff member',
            $this->manager
        );

        $this->assertSame('voided', $voidedInvoice->status);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'voided',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'workspace_id' => 'ws-emk-dhaka',
            'actor_id' => $this->manager->id,
            'event_type' => AuditLog::EVENT_INVOICE_VOIDED,
            'auditable_type' => Invoice::class,
            'auditable_id' => $invoice->id,
            'reason' => 'Accidental duplicate entry by staff member',
        ]);
    }

    public function test_non_manager_cannot_void_invoice(): void
    {
        $invoice = Invoice::create([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $this->member->id,
            'invoice_number' => 'INV-2026-00051',
            'amount_bdt' => 1000.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'TXTOVOID02',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->expectException(AuthorizationException::class);

        $this->service->voidInvoice(
            $invoice,
            'Unauthorized void attempt',
            $this->staff
        );
    }

    public function test_cannot_void_already_voided_invoice(): void
    {
        $invoice = Invoice::create([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $this->member->id,
            'invoice_number' => 'INV-2026-00052',
            'amount_bdt' => 1000.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'TXTOVOID03',
            'status' => 'voided',
            'paid_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot void an invoice with status [voided].');

        $this->service->voidInvoice(
            $invoice,
            'Attempting to void again',
            $this->manager
        );
    }
}
