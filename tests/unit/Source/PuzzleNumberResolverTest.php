<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Unit\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WordleHitToday\Source\PuzzleNumberResolver;

#[CoversClass(PuzzleNumberResolver::class)]
final class PuzzleNumberResolverTest extends TestCase
{
    public function testNumberUsesCalendarDistanceFromAnchor(): void
    {
        $resolver = new PuzzleNumberResolver('2026-10-16', 1945);

        self::assertSame(1944, $resolver->forDate('2026-10-15'));
        self::assertSame(1945, $resolver->forDate('2026-10-16'));
        self::assertSame(1946, $resolver->forDate('2026-10-17'));
    }

    public function testInvalidCalendarDateIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PuzzleNumberResolver('2026-02-30', 1);
    }
}
