<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Members\Models\Member;
use App\Domain\Members\Services\MembershipValidityEngine;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Tests\TestCase;

class MembershipValidityEngineTest extends TestCase
{
    protected MembershipValidityEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new MembershipValidityEngine();
    }

    public function test_calculate_initial_dates_defaults_start_date_to_now(): void
    {
        Carbon::setTestNow('2026-05-15 10:00:00');

        $term = new MembershipTerm([
            'duration_months' => 12,
        ]);

        $dates = $this->engine->calculateInitialDates($term);

        $this->assertInstanceOf(CarbonInterface::class, $dates['start_date']);
        $this->assertInstanceOf(CarbonInterface::class, $dates['expiry_date']);
        $this->assertEquals('2026-05-15 10:00:00', $dates['start_date']->toDateTimeString());
        $this->assertEquals('2027-05-15 10:00:00', $dates['expiry_date']->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_calculate_initial_dates_with_various_term_durations(): void
    {
        $startDate = Carbon::parse('2026-01-01 00:00:00');

        // 6 Months Term
        $term6 = new MembershipTerm(['duration_months' => 6]);
        $dates6 = $this->engine->calculateInitialDates($term6, $startDate);
        $this->assertEquals('2026-01-01', $dates6['start_date']->toDateString());
        $this->assertEquals('2026-07-01', $dates6['expiry_date']->toDateString());

        // 12 Months Term
        $term12 = new MembershipTerm(['duration_months' => 12]);
        $dates12 = $this->engine->calculateInitialDates($term12, $startDate);
        $this->assertEquals('2026-01-01', $dates12['start_date']->toDateString());
        $this->assertEquals('2027-01-01', $dates12['expiry_date']->toDateString());

        // 24 Months Term
        $term24 = new MembershipTerm(['duration_months' => 24]);
        $dates24 = $this->engine->calculateInitialDates($term24, $startDate);
        $this->assertEquals('2026-01-01', $dates24['start_date']->toDateString());
        $this->assertEquals('2028-01-01', $dates24['expiry_date']->toDateString());
    }

    public function test_early_renewal_preserves_remaining_days_and_extends_expiry(): void
    {
        // Member started 2026-01-01, expiring 2026-12-31 (1 year)
        $member = new Member([
            'start_date' => Carbon::parse('2026-01-01'),
            'expiry_date' => Carbon::parse('2026-12-31'),
        ]);

        // Renewing early on 2026-12-15 (16 days before expiry)
        $renewalDate = Carbon::parse('2026-12-15');
        $newTerm = new MembershipTerm(['duration_months' => 12]);

        $dates = $this->engine->calculateRenewalDates($member, $newTerm, $renewalDate);

        // Start date must be preserved
        $this->assertEquals('2026-01-01', $dates['start_date']->toDateString());
        // New expiry date is old expiry + 12 months, preserving the 16 remaining days
        $this->assertEquals('2027-12-31', $dates['expiry_date']->toDateString());
    }

    public function test_early_renewal_on_exact_expiry_date(): void
    {
        $member = new Member([
            'start_date' => Carbon::parse('2026-01-01'),
            'expiry_date' => Carbon::parse('2026-12-31'),
        ]);

        $renewalDate = Carbon::parse('2026-12-31');
        $newTerm = new MembershipTerm(['duration_months' => 6]);

        $dates = $this->engine->calculateRenewalDates($member, $newTerm, $renewalDate);

        $this->assertEquals('2026-01-01', $dates['start_date']->toDateString());
        $this->assertEquals('2027-06-30', $dates['expiry_date']->toDateString());
    }

    public function test_late_renewal_post_expiration_resets_start_date_to_approval_date(): void
    {
        // Member whose membership expired on 2026-12-31
        $member = new Member([
            'start_date' => Carbon::parse('2026-01-01'),
            'expiry_date' => Carbon::parse('2026-12-31'),
        ]);

        // Late renewal approval happens on 2027-01-20
        $renewalApprovalDate = Carbon::parse('2027-01-20');
        $newTerm = new MembershipTerm(['duration_months' => 12]);

        $dates = $this->engine->calculateRenewalDates($member, $newTerm, $renewalApprovalDate);

        // Start date is reset to renewal approval date
        $this->assertEquals('2027-01-20', $dates['start_date']->toDateString());
        // Expiry date is 12 months from new start date
        $this->assertEquals('2028-01-20', $dates['expiry_date']->toDateString());
    }

    public function test_renewal_window_boundaries(): void
    {
        $member = new Member([
            'start_date' => Carbon::parse('2026-01-01'),
            'expiry_date' => Carbon::parse('2026-12-31'),
        ]);

        // 31 days prior to 2026-12-31 -> 2026-11-30 -> window is not open yet
        $checkDate31DaysPrior = Carbon::parse('2026-11-30');
        $this->assertFalse($this->engine->isWithinRenewalWindow($member, $checkDate31DaysPrior));

        // 30 days prior to 2026-12-31 -> 2026-12-01 -> window opens
        $checkDate30DaysPrior = Carbon::parse('2026-12-01');
        $this->assertTrue($this->engine->isWithinRenewalWindow($member, $checkDate30DaysPrior));

        // 1 day prior to 2026-12-31 -> 2026-12-30 -> within window
        $checkDate1DayPrior = Carbon::parse('2026-12-30');
        $this->assertTrue($this->engine->isWithinRenewalWindow($member, $checkDate1DayPrior));

        // Expired (e.g. 5 days after expiry) -> within/past window
        $checkDateExpired = Carbon::parse('2027-01-05');
        $this->assertTrue($this->engine->isWithinRenewalWindow($member, $checkDateExpired));
    }

    public function test_is_expired_detection_logic(): void
    {
        $member = new Member([
            'start_date' => Carbon::parse('2026-01-01'),
            'expiry_date' => Carbon::parse('2026-12-31'),
        ]);

        // Prior to expiry
        $this->assertFalse($this->engine->isExpired($member, Carbon::parse('2026-12-30')));

        // Exact expiry timestamp
        $this->assertFalse($this->engine->isExpired($member, Carbon::parse('2026-12-31')));

        // Post expiry
        $this->assertTrue($this->engine->isExpired($member, Carbon::parse('2027-01-01')));
        $this->assertTrue($this->engine->isExpired($member, Carbon::parse('2027-02-15')));
    }

    public function test_leap_year_handling(): void
    {
        $leapStart = Carbon::parse('2024-02-29');
        $term12 = new MembershipTerm(['duration_months' => 12]);

        $dates = $this->engine->calculateInitialDates($term12, $leapStart);

        $this->assertEquals('2024-02-29', $dates['start_date']->toDateString());
        $this->assertEquals('2025-02-28', $dates['expiry_date']->toDateString());
    }
}
