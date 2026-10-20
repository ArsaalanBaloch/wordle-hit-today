<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Unit\Solver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WordleHitToday\Solver\CandidateRanker;

#[CoversClass(CandidateRanker::class)]
final class CandidateRankerTest extends TestCase
{
    public function testExactDocumentFrequencyFormula(): void
    {
        $ranked = new CandidateRanker()->rank(['APPLE', 'PLEAT', 'LEVEL']);

        self::assertSame('PLEAT', $ranked[0]['word']);
        self::assertEqualsWithDelta(11 / 3, $ranked[0]['score'], 0.00001);
        self::assertSame('APPLE', $ranked[1]['word']);
        self::assertEqualsWithDelta(10 / 3, $ranked[1]['score'], 0.00001);
    }

    public function testCommonnessAndAlphabeticalTieBreaks(): void
    {
        $ranked = new CandidateRanker()->rank(['CRANE', 'TRACE'], ['TRACE' => 1, 'CRANE' => 2]);

        self::assertSame(['TRACE', 'CRANE'], array_column($ranked, 'word'));
    }
}
