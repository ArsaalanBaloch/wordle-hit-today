<?php

declare(strict_types=1);

namespace WordleHitToday\Storage;

use Symfony\Component\Filesystem\Filesystem;
use WordleHitToday\Support\CanonicalJson;

final readonly class BackupManager
{
    private Filesystem $filesystem;

    public function __construct(private AtomicFileWriter $writer)
    {
        $this->filesystem = new Filesystem();
    }

    public function create(
        string $privateRoot,
        string $backupRoot,
        string $configPath,
        \DateTimeImmutable $now,
        string $kind,
        int $retention,
    ): string {
        if (!in_array($kind, ['daily', 'weekly'], true) || $retention < 1) {
            throw new \InvalidArgumentException('Backup kind or retention is invalid.');
        }
        $this->assertSeparateRoots($privateRoot, $backupRoot);
        $this->filesystem->mkdir($backupRoot, 0o750);
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $destination = rtrim($backupRoot, '\\/').DIRECTORY_SEPARATOR.$kind.'-'.$timestamp;
        if (file_exists($destination)) {
            throw new \RuntimeException('A backup already exists for this timestamp.');
        }
        $stage = rtrim($backupRoot, '\\/').DIRECTORY_SEPARATOR.'.partial-'.bin2hex(random_bytes(6));
        $this->filesystem->mkdir($stage.'/private', 0o750);
        foreach (['records', 'revisions', 'definitions', 'vocabulary', 'journal'] as $directory) {
            $source = rtrim($privateRoot, '\\/').DIRECTORY_SEPARATOR.$directory;
            if (is_dir($source)) {
                $this->filesystem->mirror($source, $stage.'/private/'.$directory);
            }
        }
        foreach (['status.json'] as $filename) {
            $source = rtrim($privateRoot, '\\/').DIRECTORY_SEPARATOR.$filename;
            if (is_file($source)) {
                $this->filesystem->copy($source, $stage.'/private/'.$filename, true);
            }
        }
        $this->filesystem->copy($configPath, $stage.'/config.json', true);
        $manifest = [
            'schema_version' => 1,
            'created_at_utc' => $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'kind' => $kind,
            'files' => $this->checksums($stage),
        ];
        $this->writer->write($stage.'/backup-manifest.json', CanonicalJson::encode($manifest, true));
        if (!rename($stage, $destination)) {
            $this->filesystem->remove($stage);
            throw new \RuntimeException('Unable to promote the staged backup.');
        }
        $this->prune($backupRoot, $kind, $retention);

        return $destination;
    }

    /** @return array<string, string> */
    public function verify(string $backupDirectory): array
    {
        $manifestPath = rtrim($backupDirectory, '\\/').DIRECTORY_SEPARATOR.'backup-manifest.json';
        $json = is_file($manifestPath) ? file_get_contents($manifestPath) : false;
        if (false === $json) {
            throw new \RuntimeException('Backup manifest is missing or unreadable.');
        }
        /** @var array<string, mixed> $manifest */
        $manifest = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        $files = $manifest['files'] ?? null;
        if (!is_array($files)) {
            throw new \RuntimeException('Backup manifest file map is invalid.');
        }
        $verified = [];
        foreach ($files as $relative => $expected) {
            if (!is_string($relative) || !is_string($expected)
                || str_contains($relative, '..') || str_starts_with($relative, '/') || str_contains($relative, '\\')) {
                throw new \RuntimeException('Backup manifest contains an unsafe file entry.');
            }
            $path = rtrim($backupDirectory, '\\/').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $actual = is_file($path) ? hash_file('sha256', $path) : false;
            if (!is_string($actual) || !hash_equals($expected, $actual)) {
                throw new \RuntimeException('Backup checksum mismatch: '.$relative);
            }
            $verified[$relative] = $actual;
        }

        return $verified;
    }

    public function restore(string $backupDirectory, string $targetPrivateRoot): void
    {
        $this->verify($backupDirectory);
        if (file_exists($targetPrivateRoot)) {
            throw new \InvalidArgumentException('Restore target must not already exist.');
        }
        $source = rtrim($backupDirectory, '\\/').DIRECTORY_SEPARATOR.'private';
        if (!is_dir($source)) {
            throw new \RuntimeException('Backup private-data directory is missing.');
        }
        $parent = dirname($targetPrivateRoot);
        $this->filesystem->mkdir($parent, 0o750);
        $stage = $parent.DIRECTORY_SEPARATOR.'.restore-stage-'.bin2hex(random_bytes(6));
        $this->filesystem->mirror($source, $stage);
        if (!rename($stage, $targetPrivateRoot)) {
            $this->filesystem->remove($stage);
            throw new \RuntimeException('Unable to promote the validated restore target.');
        }
    }

    /** @return array<string, string> */
    private function checksums(string $root): array
    {
        $checksums = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $hash = hash_file('sha256', $file->getPathname());
            if (!is_string($hash)) {
                throw new \RuntimeException('Unable to hash backup file: '.$relative);
            }
            $checksums[$relative] = $hash;
        }
        ksort($checksums);

        return $checksums;
    }

    private function prune(string $backupRoot, string $kind, int $retention): void
    {
        $directories = glob(rtrim($backupRoot, '\\/').DIRECTORY_SEPARATOR.$kind.'-*', GLOB_ONLYDIR) ?: [];
        rsort($directories, SORT_STRING);
        foreach (array_slice($directories, $retention) as $directory) {
            $normalizedRoot = strtolower(str_replace('\\', '/', rtrim($backupRoot, '\\/')).'/');
            $normalized = strtolower(str_replace('\\', '/', rtrim($directory, '\\/')).'/');
            if (!str_starts_with($normalized, $normalizedRoot) || !str_starts_with(basename($directory), $kind.'-')) {
                throw new \RuntimeException('Refusing to prune a path outside the configured backup root.');
            }
            $this->filesystem->remove($directory);
        }
    }

    private function assertSeparateRoots(string $privateRoot, string $backupRoot): void
    {
        $private = strtolower(str_replace('\\', '/', rtrim($privateRoot, '\\/')).'/');
        $backup = strtolower(str_replace('\\', '/', rtrim($backupRoot, '\\/')).'/');
        if ($private === $backup || str_starts_with($backup, $private)) {
            throw new \InvalidArgumentException('Backup root must be separate from private runtime storage.');
        }
    }
}
