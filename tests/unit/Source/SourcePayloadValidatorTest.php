<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Unit\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WordleHitToday\Source\SourceFailure;
use WordleHitToday\Source\SourcePayloadValidator;

#[CoversClass(SourcePayloadValidator::class)]
final class SourcePayloadValidatorTest extends TestCase
{
    public function testValidPayloadIsNormalized(): void
    {
        $payload = new SourcePayloadValidator()->validate(
            '{"id":123,"solution":"crane","print_date":"2026-10-16","days_since_launch":1945,"editor":"Example"}',
            '2026-10-16',
        );

        self::assertSame('CRANE', $payload['solution']);
        self::assertSame(1945, $payload['days_since_launch']);
    }

    #[DataProvider('invalidPayloads')]
    public function testInvalidPayloadIsRejected(string $json, string $date): void
    {
        $this->expectException(SourceFailure::class);
        new SourcePayloadValidator()->validate($json, $date);
    }

    /** @return iterable<string, array{string, string}> */
    public static function invalidPayloads(): iterable
    {
        yield 'html 200 body' => ['<html>Site unavailable</html>', '2026-10-16'];
        yield 'wrong date' => ['{"id":1,"solution":"CRANE","print_date":"2026-10-15","days_since_launch":1}', '2026-10-16'];
        yield 'malformed solution' => ['{"id":1,"solution":"CR4NE","print_date":"2026-10-16","days_since_launch":1}', '2026-10-16'];
        yield 'negative launch day' => ['{"id":1,"solution":"CRANE","print_date":"2026-10-16","days_since_launch":-1}', '2026-10-16'];
    }
}
