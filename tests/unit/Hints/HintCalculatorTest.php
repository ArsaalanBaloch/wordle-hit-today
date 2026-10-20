<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Unit\Hints;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WordleHitToday\Hints\HintCalculator;

#[CoversClass(HintCalculator::class)]
final class HintCalculatorTest extends TestCase
{
    public function testCountsVowelOccurrencesAndRepeats(): void
    {
        self::assertSame(
            ['vowel_occurrences' => 2, 'has_repeats' => true],
            new HintCalculator()->calculate('LEVEL'),
        );
    }

    public function testYIsNotCountedAsAVowel(): void
    {
        self::assertSame(
            ['vowel_occurrences' => 0, 'has_repeats' => false],
            new HintCalculator()->calculate('CRYPT'),
        );
    }
}
