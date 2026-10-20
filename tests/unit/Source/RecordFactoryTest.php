<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Unit\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WordleHitToday\Hints\HintCalculator;
use WordleHitToday\Source\PuzzleNumberResolver;
use WordleHitToday\Source\RecordFactory;
use WordleHitToday\Source\SourceFailure;

#[CoversClass(RecordFactory::class)]
final class RecordFactoryTest extends TestCase
{
    public function testProductionRecordUsesAnchorAndCanonicalHash(): void
    {
        $factory = new RecordFactory(new PuzzleNumberResolver('2026-10-16', 1945), new HintCalculator());
        $record = $factory->production(
            ['print_date' => '2026-10-16', 'solution' => 'CRANE', 'days_since_launch' => 1945, 'id' => 2222],
            'https://www.nytimes.com/svc/wordle/v2/2026-10-16.json',
            new \DateTimeImmutable('2026-10-16T12:00:00Z'),
        );

        self::assertSame(1945, $record['puzzle_number']);
        self::assertSame(64, strlen((string) $record['content_hash']));
        self::assertSame(['vowel_occurrences' => 2, 'has_repeats' => false], $record['hint_values']);
    }

    public function testLaunchDayAnomalyIsRejected(): void
    {
        $factory = new RecordFactory(new PuzzleNumberResolver('2026-10-16', 1945), new HintCalculator());

        $this->expectException(SourceFailure::class);
        $this->expectExceptionMessage('conflicts with anchored puzzle number');
        $factory->production(
            ['print_date' => '2026-10-16', 'solution' => 'CRANE', 'days_since_launch' => 12, 'id' => 2222],
            'https://www.nytimes.com/svc/wordle/v2/2026-10-16.json',
            new \DateTimeImmutable('2026-10-16T12:00:00Z'),
        );
    }
}
