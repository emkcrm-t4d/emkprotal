<?php

declare(strict_types=1);

namespace App\Domain\Forms\Services;

use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DuplicateSubmissionGuard
{
    /**
     * Assert that a bKash Transaction ID is in valid 10-char uppercase format
     * and has never been submitted across either the leads or invoices tables per DEC-05 (D-05).
     *
     * @throws ValidationException
     */
    public static function assertUniqueBkashTxid(string $txid): void
    {
        $normalized = Str::upper(trim($txid));

        if (!preg_match('/^[A-Z0-9]{10}$/', $normalized)) {
            throw ValidationException::withMessages([
                'bkash_txid' => ['bKash Transaction ID must be exactly 10 uppercase alphanumeric characters.'],
            ]);
        }

        $leadExists = DB::table('leads')
            ->where('bkash_txid', $normalized)
            ->exists();

        $invoiceExists = DB::table('invoices')
            ->where('payment_reference', $normalized)
            ->exists();

        if ($leadExists || $invoiceExists) {
            throw ValidationException::withMessages([
                'bkash_txid' => ['This bKash Transaction ID has already been submitted. If you believe this is an error, please contact EMK Center Information Desk.'],
            ]);
        }
    }

    /**
     * Assert that an email is unique for active/pending applications or existing member accounts,
     * while permitting reapplication for previously rejected applicants per DEC-01 and LEAD-07.
     *
     * @throws ValidationException
     */
    public static function assertUniqueEmail(string $email): void
    {
        $normalized = Str::lower(trim($email));

        // 1. Check if an account already exists in users table
        $userExists = DB::table('users')
            ->where('email', $normalized)
            ->exists();

        if ($userExists) {
            throw ValidationException::withMessages([
                'email' => ['An application with this email address is already active or registered.'],
            ]);
        }

        // 2. Check if applicant email has been blocked
        $isBlocked = DB::table('leads')
            ->where('email', $normalized)
            ->where('status', 'blocked')
            ->whereNull('deleted_at')
            ->exists();

        if ($isBlocked) {
            throw ValidationException::withMessages([
                'email' => ['This email address has been blocked from submitting applications.'],
            ]);
        }

        // 3. Check for active/pending applications
        $activeLeadExists = DB::table('leads')
            ->where('email', $normalized)
            ->whereIn('status', [
                'draft',
                'submitted',
                'under_review',
                'missing_items',
                'approved',
                'converted',
            ])
            ->whereNull('deleted_at')
            ->exists();

        if ($activeLeadExists) {
            throw ValidationException::withMessages([
                'email' => ['An application with this email address is already active or registered.'],
            ]);
        }
    }

    /**
     * Enforce IP rate limiting (10 submissions per minute per IP) per ASVS V5.2 / T-02-09.
     *
     * @throws ThrottleRequestsException
     */
    public static function checkRateLimit(string $ipAddress): void
    {
        $key = 'membership_submit:' . $ipAddress;

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);
            throw new ThrottleRequestsException(
                "Too many submission attempts. Please wait {$seconds} seconds before trying again."
            );
        }

        RateLimiter::hit($key, 60);
    }
}
