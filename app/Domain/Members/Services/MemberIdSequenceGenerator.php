<?php

declare(strict_types=1);

namespace App\Domain\Members\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use OverflowException;

final class MemberIdSequenceGenerator
{
    /**
     * Atomically obtain and increment the next monotonic Member ID.
     * Format: {SPACE_CODE}-{YEAR}-{5_DIGIT_SEQ} (e.g. EMK-2026-00001)
     *
     * @throws OverflowException
     */
    public function nextMemberId(string $spaceCode, int $year): string
    {
        $execute = function () use ($spaceCode, $year): string {
            // 1. Lock sequence row for update (Creates or Locks)
            $sequenceRecord = DB::table('member_id_sequences')
                ->where('space_code', $spaceCode)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $currentSeq = $sequenceRecord !== null ? (int) $sequenceRecord->last_sequence : 0;
            $nextSeq = $currentSeq + 1;

            if ($nextSeq > 99999) {
                throw new OverflowException("Member ID sequence limit exceeded for {$spaceCode} in year {$year}.");
            }

            // 2. Persist incremented counter
            DB::table('member_id_sequences')->updateOrInsert(
                ['space_code' => $spaceCode, 'year' => $year],
                ['last_sequence' => $nextSeq, 'updated_at' => Carbon::now()]
            );

            return sprintf('%s-%04d-%05d', $spaceCode, $year, $nextSeq);
        };

        return DB::transactionLevel() > 0 ? $execute() : DB::transaction($execute);
    }
}
