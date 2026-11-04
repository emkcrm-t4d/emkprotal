<?php

declare(strict_types=1);

namespace App\Domain\Members\Services;

use App\Domain\Forms\Models\MembershipTerm;
use App\Domain\Members\Models\Member;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class MembershipValidityEngine
{
    /**
     * Calculate initial start and expiration dates for a newly admitted member.
     *
     * @return array{start_date: CarbonInterface, expiry_date: CarbonInterface}
     */
    public function calculateInitialDates(
        MembershipTerm $term,
        ?CarbonInterface $startDate = null
    ): array {
        $startDate = $startDate ? $startDate->copy() : Carbon::now();
        $expiryDate = $startDate->copy()->addMonthsNoOverflow((int) $term->duration_months);

        return [
            'start_date' => $startDate,
            'expiry_date' => $expiryDate,
        ];
    }

    /**
     * Calculate renewal start and expiration dates.
     *
     * Early renewal (on or prior to expiration date):
     * Appends term duration to existing expiration date, preserving all remaining days.
     *
     * Late renewal (post-expiration):
     * Resets start date to renewal approval timestamp and computes new expiry from that date.
     *
     * @return array{start_date: CarbonInterface, expiry_date: CarbonInterface}
     */
    public function calculateRenewalDates(
        Member $member,
        MembershipTerm $term,
        ?CarbonInterface $renewalDate = null
    ): array {
        $renewalDate = $renewalDate ? $renewalDate->copy() : Carbon::now();
        $currentExpiry = $member->expiry_date instanceof CarbonInterface
            ? $member->expiry_date->copy()
            : Carbon::parse($member->expiry_date);

        if ($renewalDate->lte($currentExpiry)) {
            // Early renewal: keep existing start date, append duration to current expiry
            $startDate = $member->start_date instanceof CarbonInterface
                ? $member->start_date->copy()
                : Carbon::parse($member->start_date);
            $expiryDate = $currentExpiry->copy()->addMonthsNoOverflow((int) $term->duration_months);
        } else {
            // Late renewal: reset start date to renewal approval date
            $startDate = $renewalDate->copy();
            $expiryDate = $startDate->copy()->addMonthsNoOverflow((int) $term->duration_months);
        }

        return [
            'start_date' => $startDate,
            'expiry_date' => $expiryDate,
        ];
    }

    /**
     * Check if a given date falls within the 30-day pre-expiry renewal window.
     * The renewal window opens 30 calendar days prior to member's expiry date.
     */
    public function isWithinRenewalWindow(
        Member $member,
        ?CarbonInterface $checkDate = null
    ): bool {
        $checkDate = $checkDate ? $checkDate->copy() : Carbon::now();
        $expiry = $member->expiry_date instanceof CarbonInterface
            ? $member->expiry_date->copy()
            : Carbon::parse($member->expiry_date);

        $windowStart = $expiry->copy()->subDays(30);

        return $checkDate->gte($windowStart);
    }

    /**
     * Check if member's validity period has expired on the given date.
     */
    public function isExpired(
        Member $member,
        ?CarbonInterface $checkDate = null
    ): bool {
        $checkDate = $checkDate ? $checkDate->copy() : Carbon::now();
        $expiry = $member->expiry_date instanceof CarbonInterface
            ? $member->expiry_date->copy()
            : Carbon::parse($member->expiry_date);

        return $expiry->lt($checkDate);
    }
}
