<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Unit\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use WordleHitToday\Storage\AtomicFileWriter;
use WordleHitToday\Storage\BackupManager;

#[CoversClass(BackupManager::class)]
final class BackupManagerTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        $this->temporaryRoot = dirname(__DIR__, 3).'/var/test-backup-'.bin2hex(random_bytes(4));
        mkdir($this->temporaryRoot.'/private/records', 0o750, true);
        file_put_contents($this->temporaryRoot.'/private/records/example.json', "{}\n");
        file_put_contents($this->temporaryRoot.'/config.json', "{}\n");
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->temporaryRoot);
    }

    public function testBackupIsChecksummedAndRestoredToANewPath(): void
    {
        $manager = new BackupManager(new AtomicFileWriter());
        $backup = $manager->create(
            $this->temporaryRoot.'/private',
            $this->temporaryRoot.'/backups',
            $this->temporaryRoot.'/config.json',
            new \DateTimeImmutable('2026-10-16T12:00:00Z'),
            'daily',
            2,
        );

        self::assertNotEmpty($manager->verify($backup));
        $manager->restore($backup, $this->temporaryRoot.'/restored');
        self::assertFileExists($this->temporaryRoot.'/restored/records/example.json');
    }

    public function testTamperingFailsVerification(): void
    {
        $manager = new BackupManager(new AtomicFileWriter());
        $backup = $manager->create(
            $this->temporaryRoot.'/private',
            $this->temporaryRoot.'/backups',
            $this->temporaryRoot.'/config.json',
            new \DateTimeImmutable('2026-10-16T12:00:00Z'),
            'daily',
            2,
        );
        file_put_contents($backup.'/private/records/example.json', "tampered\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('checksum mismatch');
        $manager->verify($backup);
    }
}
