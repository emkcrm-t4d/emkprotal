<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Forms\Services\DuplicateSubmissionGuard;
use App\Domain\Leads\Models\Lead;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DuplicateSubmissionGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_valid_ten_character_uppercase_bkash_txid_passes(): void
    {
        // Should execute cleanly without throwing an exception
        DuplicateSubmissionGuard::assertUniqueBkashTxid('TXID123456');
        DuplicateSubmissionGuard::assertUniqueBkashTxid('ABCDEF9999');

        $this->assertTrue(true);
    }

    public function test_invalid_bkash_txid_format_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);
        // Only 8 characters
        DuplicateSubmissionGuard::assertUniqueBkashTxid('SHORT12');
    }

    public function test_special_characters_in_bkash_txid_throws_validation_exception(): void
    {
        $this->expectException(ValidationException::class);
        // Contains invalid special character
        DuplicateSubmissionGuard::assertUniqueBkashTxid('TXID12345!');
    }

    public function test_duplicate_bkash_txid_matching_existing_lead_throws_validation_exception(): void
    {
        $this->createLead([
            'bkash_txid' => 'TXIDDUPL01',
            'email' => 'original@test.test',
        ]);

        $this->expectException(ValidationException::class);
        DuplicateSubmissionGuard::assertUniqueBkashTxid('TXIDDUPL01');
    }

    public function test_duplicate_bkash_txid_matching_existing_invoice_payment_reference_throws_validation_exception(): void
    {
        $lead = $this->createLead([
            'bkash_txid' => 'TXIDORIG01',
            'email' => 'invoice.lead@test.test',
        ]);

        $user = User::create([
            'name' => 'Invoice Member',
            'email' => 'invoice.member@test.test',
            'password' => bcrypt('password'),
            'workspace_id' => 'ws-emk-dhaka',
            'guard' => 'member',
            'is_active' => true,
        ]);

        $memberId = DB::table('members')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'member_id' => 'EMK-2026-90001',
            'workspace_id' => 'ws-emk-dhaka',
            'lead_id' => $lead->id,
            'user_id' => $user->id,
            'membership_term_id' => 1,
            'status' => 'active',
            'first_name' => 'Invoice',
            'last_name' => 'Member',
            'email' => 'invoice.member@test.test',
            'contact_number' => '+8801700000001',
            'date_of_birth' => '1998-01-01',
            'sex' => 'female',
            'address_house_no' => '10',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'CS',
            'start_date' => now(),
            'expiry_date' => now()->addYear(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('invoices')->insert([
            'invoice_number' => 'INV-2026-90001',
            'workspace_id' => 'ws-emk-dhaka',
            'member_id' => $memberId,
            'lead_id' => $lead->id,
            'amount_bdt' => 500.00,
            'payment_method' => 'bkash_manual',
            'payment_reference' => 'INVREF9999',
            'status' => 'paid',
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(ValidationException::class);
        DuplicateSubmissionGuard::assertUniqueBkashTxid('INVREF9999');
    }

    public function test_email_uniqueness_blocks_duplicate_application_for_pending_leads(): void
    {
        $this->createLead([
            'bkash_txid' => 'TXIDUNQ001',
            'email' => 'pending.applicant@test.test',
            'status' => 'submitted',
        ]);

        $this->expectException(ValidationException::class);
        DuplicateSubmissionGuard::assertUniqueEmail('pending.applicant@test.test');
    }

    public function test_email_uniqueness_blocks_duplicate_application_for_approved_leads(): void
    {
        $this->createLead([
            'bkash_txid' => 'TXIDUNQ002',
            'email' => 'approved.applicant@test.test',
            'status' => 'approved',
        ]);

        $this->expectException(ValidationException::class);
        DuplicateSubmissionGuard::assertUniqueEmail('approved.applicant@test.test');
    }

    public function test_email_uniqueness_blocks_application_if_member_account_exists_in_users_table(): void
    {
        User::create([
            'name' => 'Existing Portal Member',
            'email' => 'portal.member@test.test',
            'password' => bcrypt('password'),
            'workspace_id' => 'ws-emk-dhaka',
            'guard' => 'member',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);
        DuplicateSubmissionGuard::assertUniqueEmail('portal.member@test.test');
    }

    public function test_reapplicant_with_previously_rejected_lead_is_permitted_to_reapply(): void
    {
        $this->createLead([
            'bkash_txid' => 'TXIDREJ001',
            'email' => 'rejected.applicant@test.test',
            'status' => 'rejected',
        ]);

        // Must NOT throw ValidationException
        DuplicateSubmissionGuard::assertUniqueEmail('rejected.applicant@test.test');
        $this->assertTrue(true);
    }

    public function test_blocked_lead_email_is_rejected(): void
    {
        $this->createLead([
            'bkash_txid' => 'TXIDBLK001',
            'email' => 'blocked.applicant@test.test',
            'status' => 'blocked',
        ]);

        $this->expectException(ValidationException::class);
        DuplicateSubmissionGuard::assertUniqueEmail('blocked.applicant@test.test');
    }

    public function test_ip_rate_limiter_blocks_excessive_submissions(): void
    {
        $ip = '10.0.0.99';
        RateLimiter::clear('membership_submit:' . $ip);

        // First 10 calls succeed
        for ($i = 0; $i < 10; $i++) {
            DuplicateSubmissionGuard::checkRateLimit($ip);
        }

        // 11th call must trigger ThrottleRequestsException (HTTP 429)
        $this->expectException(ThrottleRequestsException::class);
        DuplicateSubmissionGuard::checkRateLimit($ip);
    }

    /**
     * Helper to create valid Lead records for testing.
     */
    protected function createLead(array $overrides = []): Lead
    {
        $attributes = array_merge([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => 'ws-emk-dhaka',
            'target_space_code' => 'EMK-DHK',
            'status' => 'submitted',
            'bkash_txid' => 'TX' . strtoupper(Str::random(8)),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test.' . Str::random(6) . '@test.test',
            'contact_number' => '+8801711223344',
            'date_of_birth' => '1996-01-01',
            'age_at_submission' => 30,
            'sex' => 'male',
            'address_house_no' => '42',
            'address_area' => 'Dhanmondi',
            'address_zip_code' => '1209',
            'address_division' => 'dhaka',
            'address_district' => 'Dhaka',
            'occupation' => 'student',
            'institution_study' => 'Dhaka University',
            'highest_certification' => 'undergraduate_degree',
            'area_of_subject' => 'Economics',
            'has_us_experience' => false,
            'is_american_alumni' => false,
            'has_parental_consent' => false,
            'terms_agreed' => true,
            'submitted_at' => now(),
        ], $overrides);

        return Lead::create($attributes);
    }
}
