<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Payments\Adapters\ManualBkashPaymentAdapter;
use App\Domain\Payments\Contracts\PaymentAdapterInterface;
use App\Domain\Payments\DTOs\PaymentVerificationRequest;
use App\Domain\Payments\Services\PaymentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualBkashPaymentAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected ManualBkashPaymentAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->adapter = new ManualBkashPaymentAdapter();
    }

    public function test_default_payment_driver_resolves_to_bkash_manual(): void
    {
        $manager = app(PaymentManager::class);

        $this->assertEquals('bkash_manual', $manager->getDefaultDriver());
        $this->assertInstanceOf(PaymentAdapterInterface::class, $manager->driver());
        $this->assertInstanceOf(ManualBkashPaymentAdapter::class, $manager->driver());
        $this->assertEquals('bkash_manual', $manager->driver()->getDriverName());
        $this->assertTrue($manager->driver()->supportsManualVerification());
    }

    public function test_valid_ten_character_uppercase_txid_acceptance(): void
    {
        $request = new PaymentVerificationRequest(
            leadId: 1,
            paymentReference: 'BLK7X9Q12M',
            amountBdt: 1000.0,
            reviewerUserId: 1,
            notes: 'Verified via bKash merchant statement'
        );

        $result = $this->adapter->verifyPayment($request);

        $this->assertTrue($result->isVerified);
        $this->assertTrue($result->isSuccessful);
        $this->assertEquals('verified', $result->status);
        $this->assertEquals('BLK7X9Q12M', $result->reference);
        $this->assertEquals('BLK7X9Q12M', $result->transactionId);
        $this->assertNull($result->rejectionReasonCode);
        $this->assertNull($result->errorMessage);
    }

    public function test_lowercase_ten_character_txid_is_auto_uppercased(): void
    {
        $request = new PaymentVerificationRequest(
            leadId: 1,
            paymentReference: 'bl92a8zk91',
            amountBdt: 1000.0,
            reviewerUserId: 1
        );

        $result = $this->adapter->verifyPayment($request);

        $this->assertTrue($result->isVerified);
        $this->assertEquals('BL92A8ZK91', $result->reference);
        $this->assertEquals('BL92A8ZK91', $result->evidencePayload['bkash_txid']);
    }

    public function test_malformed_txid_rejections(): void
    {
        $invalidTxids = [
            'BL92A8ZK',       // Too short (8 chars)
            'BL92A8ZK9100',   // Too long (12 chars)
            'BL92-8ZK91',     // Special character hyphen
            'BL92_8ZK91',     // Special character underscore
            'BL92 8ZK91',     // Space inside
            '!@#$%^&*()',     // Punctuation
        ];

        foreach ($invalidTxids as $invalidTxid) {
            $request = new PaymentVerificationRequest(
                leadId: 1,
                paymentReference: $invalidTxid,
                amountBdt: 1000.0,
                reviewerUserId: 1
            );

            $result = $this->adapter->verifyPayment($request);

            $this->assertFalse($result->isVerified, "TxID {$invalidTxid} should fail verification");
            $this->assertFalse($result->isSuccessful);
            $this->assertEquals('rejected', $result->status);
            $this->assertEquals('INVALID_TXID', $result->rejectionReasonCode);
            $this->assertNotNull($result->errorMessage);
        }
    }

    public function test_duplicate_txid_in_leads_is_rejected(): void
    {
        // Insert existing lead with TxID 'DUPLICAT01'
        DB::table('leads')->insert([
            'id' => 101,
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'submitted',
            'bkash_txid' => 'DUPLICAT01',
            'first_name' => 'Existing',
            'last_name' => 'Applicant',
            'email' => 'existing@example.com',
            'contact_number' => '+8801712345678',
            'date_of_birth' => '2000-01-01',
            'age_at_submission' => 26,
            'sex' => 'male',
            'address_house_no' => '12',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'bachelors',
            'area_of_subject' => 'CS',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = new PaymentVerificationRequest(
            leadId: 202, // Different lead
            paymentReference: 'DUPLICAT01',
            amountBdt: 1000.0,
            reviewerUserId: 1
        );

        $result = $this->adapter->verifyPayment($request);

        $this->assertFalse($result->isVerified);
        $this->assertEquals('rejected', $result->status);
        $this->assertEquals('DUPLICATE_TXID', $result->rejectionReasonCode);
        $this->assertStringContainsString('DUPLICAT01 is already registered', (string) $result->errorMessage);
        $this->assertEquals('leads', $result->evidencePayload['collision']['source']);
        $this->assertEquals(101, $result->evidencePayload['collision']['id']);
    }

    public function test_duplicate_txid_in_invoices_is_rejected(): void
    {
        $userId = DB::table('users')->insertGetId([
            'workspace_id' => 'ws-emk-dhaka',
            'name' => 'Member User',
            'email' => 'member.user@emk.test',
            'password' => bcrypt('password'),
            'guard' => 'web',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $leadId = DB::table('leads')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'converted',
            'bkash_txid' => 'ORIGTX0001',
            'first_name' => 'Original',
            'last_name' => 'Lead',
            'email' => 'orig@example.com',
            'contact_number' => '+8801700000001',
            'date_of_birth' => '1998-01-01',
            'age_at_submission' => 28,
            'sex' => 'male',
            'address_house_no' => '1',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'student',
            'institution_study' => 'DU',
            'highest_certification' => 'bachelors',
            'area_of_subject' => 'CS',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $term = DB::table('membership_terms')->first();
        $termId = $term ? $term->id : 1;

        $memberId = DB::table('members')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => 'EMK-2026-00001',
            'lead_id' => $leadId,
            'user_id' => $userId,
            'membership_term_id' => $termId,
            'status' => 'active',
            'first_name' => 'Member',
            'last_name' => 'User',
            'email' => 'member.user@emk.test',
            'contact_number' => '+8801711122233',
            'date_of_birth' => '1995-05-15',
            'sex' => 'female',
            'address_house_no' => '15',
            'address_area' => 'Gulshan',
            'address_zip_code' => '1212',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'educator',
            'institution_study' => 'NSU',
            'highest_certification' => 'masters',
            'area_of_subject' => 'English',
            'start_date' => now()->toDateString(),
            'expiry_date' => now()->addYear()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert invoice with TxID 'INVOICETX1'
        DB::table('invoices')->insert([
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $memberId,
            'lead_id' => $leadId,
            'invoice_number' => 'INV-2026-00001',
            'amount_bdt' => 1000.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'INVOICETX1',
            'status' => 'paid',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = new PaymentVerificationRequest(
            leadId: 303,
            paymentReference: 'INVOICETX1',
            amountBdt: 1000.0,
            reviewerUserId: 1
        );

        $result = $this->adapter->verifyPayment($request);

        $this->assertFalse($result->isVerified);
        $this->assertEquals('rejected', $result->status);
        $this->assertEquals('DUPLICATE_TXID', $result->rejectionReasonCode);
        $this->assertEquals('invoices', $result->evidencePayload['collision']['source']);
    }

    public function test_same_lead_txid_is_not_treated_as_duplicate(): void
    {
        // Insert lead with ID 404 and TxID 'SELFCHECK1'
        DB::table('leads')->insert([
            'id' => 404,
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK',
            'status' => 'submitted',
            'bkash_txid' => 'SELFCHECK1',
            'first_name' => 'Self',
            'last_name' => 'Check',
            'email' => 'selfcheck@example.com',
            'contact_number' => '+8801712345679',
            'date_of_birth' => '2001-02-02',
            'age_at_submission' => 25,
            'sex' => 'female',
            'address_house_no' => '5',
            'address_area' => 'Banani',
            'address_zip_code' => '1213',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'student',
            'institution_study' => 'BRAC',
            'highest_certification' => 'bachelors',
            'area_of_subject' => 'BBA',
            'submitted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Verifying the SAME lead record (leadId: 404) should not collision with itself
        $request = new PaymentVerificationRequest(
            leadId: 404,
            paymentReference: 'SELFCHECK1',
            amountBdt: 1000.0,
            reviewerUserId: 1
        );

        $result = $this->adapter->verifyPayment($request);

        $this->assertTrue($result->isVerified);
        $this->assertEquals('verified', $result->status);
        $this->assertEquals('SELFCHECK1', $result->reference);
    }

    public function test_evidence_payload_schema_conformance(): void
    {
        $request = new PaymentVerificationRequest(
            leadId: 505,
            paymentReference: 'SCHEMA1234',
            amountBdt: 1500.0,
            reviewerUserId: 42,
            notes: 'Payment confirmed in merchant statement'
        );

        $result = $this->adapter->verifyPayment($request);

        $this->assertTrue($result->isVerified);
        $payload = $result->evidencePayload;

        $this->assertIsArray($payload);
        $this->assertEquals('payment', $payload['stream']);
        $this->assertEquals('verified', $payload['status']);
        $this->assertEquals(42, $payload['verified_by_user_id']);
        $this->assertNotNull($payload['verified_at']);
        $this->assertEquals('SCHEMA1234', $payload['bkash_txid']);
        $this->assertEquals(1500.0, $payload['amount_received_bdt']);
        $this->assertEquals(1500.0, $payload['expected_amount_bdt']);
        $this->assertEquals('bkash_manual', $payload['payment_method']);
        $this->assertNotNull($payload['merchant_statement_date']);
        $this->assertEquals('Payment confirmed in merchant statement', $payload['payment_verification_notes']);
        $this->assertNull($payload['rejection_reason_code']);
    }
}
