<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Unit\Console\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use WordleHitToday\Console\Command\RestoreCommand;
use WordleHitToday\Console\ExitCode;

#[CoversClass(RestoreCommand::class)]
final class RestoreCommandTest extends TestCase
{
    #[DataProvider('overlappingTargets')]
    public function testRejectsTargetsThatOverlapRuntimeStorage(string $target, string $expectedMessage): void
    {
        $projectRoot = dirname(__DIR__, 4);
        $tester = new CommandTester(new RestoreCommand($projectRoot));

        $exitCode = $tester->execute([
            'backup' => 'daily-20261016T120000Z',
            '--target' => $target,
            '--config' => 'config/site.example.json',
            '--mode' => 'fixture',
        ]);

        self::assertSame(ExitCode::InvalidInputOrConfiguration->value, $exitCode);
        self::assertStringContainsString($expectedMessage, $tester->getDisplay());
    }

    /** @return iterable<string, array{string, string}> */
    public static function overlappingTargets(): iterable
    {
        yield 'nested target' => ['var/private/restored', 'Restore target and Private storage must not overlap.'];
        yield 'dot-segment equivalent target' => ['var/private/records/..', 'Restore target and Private storage must not overlap.'];
        yield 'public rollback target' => ['var/site.rollback', 'Restore target and Public rollback must not overlap.'];
    }
}
