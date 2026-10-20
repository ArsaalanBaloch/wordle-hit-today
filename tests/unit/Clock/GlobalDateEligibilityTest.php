<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Unit\Clock;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WordleHitToday\Clock\GlobalDateEligibility;

#[CoversClass(GlobalDateEligibility::class)]
final class GlobalDateEligibilityTest extends TestCase
{
    public function testThreeDatesCanBeGloballyRelevant(): void
    {
        $instant = new \DateTimeImmutable('2026-10-16T11:30:00Z');

        self::assertSame(
            ['2026-10-15', '2026-10-16', '2026-10-17'],
            new GlobalDateEligibility()->datesAt($instant),
        );
    }

    public function testEarliestPublicationIsMidnightAtUtcPlusFourteen(): void
    {
        self::assertSame(
            '2026-10-16T10:00:00+00:00',
            new GlobalDateEligibility()->earliestPublicationInstant('2026-10-17')->format(DATE_ATOM),
        );
    }
}
