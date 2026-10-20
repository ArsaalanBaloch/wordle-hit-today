<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Unit\Solver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WordleHitToday\Solver\Feedback;
use WordleHitToday\Solver\FeedbackEvaluator;

#[CoversClass(FeedbackEvaluator::class)]
final class FeedbackEvaluatorTest extends TestCase
{
    public function testAlleyAgainstApple(): void
    {
        self::assertSame(
            [Feedback::Correct, Feedback::Present, Feedback::Absent, Feedback::Present, Feedback::Absent],
            new FeedbackEvaluator()->evaluate('APPLE', 'ALLEY'),
        );
    }

    public function testEerieAgainstLevel(): void
    {
        self::assertSame(
            [Feedback::Present, Feedback::Correct, Feedback::Absent, Feedback::Absent, Feedback::Absent],
            new FeedbackEvaluator()->evaluate('LEVEL', 'EERIE'),
        );
    }

    public function testUnsetRowIsExcludedFromFiltering(): void
    {
        $rows = [[
            'guess' => 'CRANE',
            'feedback' => [Feedback::Unset, Feedback::Unset, Feedback::Unset, Feedback::Unset, Feedback::Unset],
        ]];

        self::assertSame(['APPLE', 'LEVEL'], new FeedbackEvaluator()->filter(['APPLE', 'LEVEL'], $rows));
    }
}
