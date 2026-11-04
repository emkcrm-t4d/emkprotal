<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Members\Services\MemberIdSequenceGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use OverflowException;
use Tests\TestCase;

class MemberIdSequenceGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected MemberIdSequenceGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new MemberIdSequenceGenerator();
    }

    public function test_generates_sequential_member_ids_monotonically(): void
    {
        $id1 = $this->generator->nextMemberId('EMK', 2026);
        $id2 = $this->generator->nextMemberId('EMK', 2026);
        $id3 = $this->generator->nextMemberId('EMK', 2026);

        $this->assertEquals('EMK-2026-00001', $id1);
        $this->assertEquals('EMK-2026-00002', $id2);
        $this->assertEquals('EMK-2026-00003', $id3);

        $this->assertDatabaseHas('member_id_sequences', [
            'space_code' => 'EMK',
            'year' => 2026,
            'last_sequence' => 3,
        ]);
    }

    public function test_distinct_space_codes_maintain_independent_sequences(): void
    {
        $emk1 = $this->generator->nextMemberId('EMK', 2026);
        $bar1 = $this->generator->nextMemberId('BAR', 2026);
        $ctg1 = $this->generator->nextMemberId('CTG', 2026);
        $emk2 = $this->generator->nextMemberId('EMK', 2026);

        $this->assertEquals('EMK-2026-00001', $emk1);
        $this->assertEquals('BAR-2026-00001', $bar1);
        $this->assertEquals('CTG-2026-00001', $ctg1);
        $this->assertEquals('EMK-2026-00002', $emk2);
    }

    public function test_distinct_years_maintain_independent_sequences(): void
    {
        $id2026_1 = $this->generator->nextMemberId('EMK', 2026);
        $id2027_1 = $this->generator->nextMemberId('EMK', 2027);
        $id2026_2 = $this->generator->nextMemberId('EMK', 2026);

        $this->assertEquals('EMK-2026-00001', $id2026_1);
        $this->assertEquals('EMK-2027-00001', $id2027_1);
        $this->assertEquals('EMK-2026-00002', $id2026_2);
    }

    public function test_sequence_format_strictly_matches_composite_pattern(): void
    {
        $id = $this->generator->nextMemberId('EMK', 2026);

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{3,10}-\d{4}-\d{5}$/', $id);
        $this->assertEquals('EMK-2026-00001', $id);
    }

    public function test_throws_overflow_exception_when_sequence_exceeds_99999(): void
    {
        DB::table('member_id_sequences')->insert([
            'space_code' => 'EMK',
            'year' => 2026,
            'last_sequence' => 99999,
            'updated_at' => now(),
        ]);

        $this->expectException(OverflowException::class);
        $this->expectExceptionMessage('Member ID sequence limit exceeded for EMK in year 2026.');

        $this->generator->nextMemberId('EMK', 2026);
    }

    public function test_generator_works_inside_active_database_transaction(): void
    {
        $id = DB::transaction(function () {
            return $this->generator->nextMemberId('EMK', 2026);
        });

        $this->assertEquals('EMK-2026-00001', $id);

        $this->assertDatabaseHas('member_id_sequences', [
            'space_code' => 'EMK',
            'year' => 2026,
            'last_sequence' => 1,
        ]);
    }
}
