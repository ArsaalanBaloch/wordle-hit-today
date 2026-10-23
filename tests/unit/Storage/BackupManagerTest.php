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
            $this->temporaryRoot.'/private/status.json',
            new \DateTimeImmutable('2026-10-16T12:00:00Z'),
            'daily',
            2,
        )->path;

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
            $this->temporaryRoot.'/private/status.json',
            new \DateTimeImmutable('2026-10-16T12:00:00Z'),
            'daily',
            2,
        )->path;
        file_put_contents($backup.'/private/records/example.json', "tampered\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('changed: private/records/example.json');
        $manager->verify($backup);
    }

    public function testInjectedExtraFileFailsVerification(): void
    {
        $manager = new BackupManager(new AtomicFileWriter());
        $backup = $manager->create(
            $this->temporaryRoot.'/private',
            $this->temporaryRoot.'/backups',
            $this->temporaryRoot.'/config.json',
            $this->temporaryRoot.'/private/status.json',
            new \DateTimeImmutable('2026-10-16T12:00:00Z'),
            'daily',
            2,
        )->path;
        file_put_contents($backup.'/private/records/injected.json', "{}\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('extra: private/records/injected.json');
        $manager->verify($backup);
    }

    public function testPruneLeavesInvalidCandidatesUntouched(): void
    {
        $manager = new BackupManager(new AtomicFileWriter());
        $oldBackup = $manager->create(
            $this->temporaryRoot.'/private',
            $this->temporaryRoot.'/backups',
            $this->temporaryRoot.'/config.json',
            $this->temporaryRoot.'/private/status.json',
            new \DateTimeImmutable('2026-10-14T12:00:00Z'),
            'daily',
            10,
        )->path;
        $invalidTimestamp = $this->temporaryRoot.'/backups/daily-not-a-timestamp';
        $invalidManifest = $this->temporaryRoot.'/backups/daily-20261013T120000Z';
        mkdir($invalidTimestamp);
        mkdir($invalidManifest);
        file_put_contents($invalidManifest.'/backup-manifest.json', "{}\n");

        $newBackup = $manager->create(
            $this->temporaryRoot.'/private',
            $this->temporaryRoot.'/backups',
            $this->temporaryRoot.'/config.json',
            $this->temporaryRoot.'/private/status.json',
            new \DateTimeImmutable('2026-10-16T12:00:00Z'),
            'daily',
            1,
        )->path;

        self::assertDirectoryDoesNotExist($oldBackup);
        self::assertDirectoryExists($newBackup);
        self::assertDirectoryExists($invalidTimestamp);
        self::assertDirectoryExists($invalidManifest);
    }

    public function testBackupRootsMustBeDisjointInEitherDirection(): void
    {
        $manager = new BackupManager(new AtomicFileWriter());

        foreach ([
            [$this->temporaryRoot.'/private', $this->temporaryRoot.'/private/backups'],
            [$this->temporaryRoot.'/backups/private', $this->temporaryRoot.'/backups'],
        ] as [$privateRoot, $backupRoot]) {
            try {
                $manager->create(
                    $privateRoot,
                    $backupRoot,
                    $this->temporaryRoot.'/config.json',
                    $privateRoot.'/status.json',
                    new \DateTimeImmutable('2026-10-16T12:00:00Z'),
                    'daily',
                    2,
                );
                self::fail('Overlapping backup roots were accepted.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame('Backup root must be separate from private runtime storage.', $exception->getMessage());
            }
        }
    }

    public function testConfiguredNestedStatusPathIsBackedUpAndRestored(): void
    {
        $statusPath = $this->temporaryRoot.'/private/monitoring/runtime/scheduler-state.json';
        mkdir(dirname($statusPath), 0o750, true);
        file_put_contents($statusPath, "{\"state\":\"healthy\"}\n");
        $manager = new BackupManager(new AtomicFileWriter());

        $backup = $manager->create(
            $this->temporaryRoot.'/private',
            $this->temporaryRoot.'/backups',
            $this->temporaryRoot.'/config.json',
            $statusPath,
            new \DateTimeImmutable('2026-10-16T12:00:00Z'),
            'daily',
            2,
        )->path;

        $files = $manager->verify($backup);
        self::assertArrayHasKey('private/monitoring/runtime/scheduler-state.json', $files);
        self::assertArrayNotHasKey('private/status.json', $files);
        $manager->restore($backup, $this->temporaryRoot.'/restored');
        self::assertStringEqualsFile(
            $this->temporaryRoot.'/restored/monitoring/runtime/scheduler-state.json',
            "{\"state\":\"healthy\"}\n",
        );
    }

    public function testStatusPathOutsidePrivateStorageIsRejected(): void
    {
        $manager = new BackupManager(new AtomicFileWriter());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Status path must be strictly inside Private storage.');

        $manager->create(
            $this->temporaryRoot.'/private',
            $this->temporaryRoot.'/backups',
            $this->temporaryRoot.'/config.json',
            $this->temporaryRoot.'/status.json',
            new \DateTimeImmutable('2026-10-16T12:00:00Z'),
            'daily',
            2,
        );
    }
}
