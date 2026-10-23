<?php

declare(strict_types=1);

namespace WordleHitToday\Storage;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use WordleHitToday\Support\CanonicalJson;
use WordleHitToday\Support\PathPolicy;

final readonly class BackupManager
{
    private Filesystem $filesystem;
    private DirectoryIntegrity $integrity;

    public function __construct(private AtomicFileWriter $writer)
    {
        $this->filesystem = new Filesystem();
        $this->integrity = new DirectoryIntegrity();
    }

    public function create(
        string $privateRoot,
        string $backupRoot,
        string $configPath,
        string $statusPath,
        \DateTimeImmutable $now,
        string $kind,
        int $retention,
    ): BackupResult {
        if (!in_array($kind, ['daily', 'weekly'], true) || $retention < 1) {
            throw new \InvalidArgumentException('Backup kind or retention is invalid.');
        }
        $this->assertSeparateRoots($privateRoot, $backupRoot);
        PathPolicy::assertStrictlyInside($statusPath, $privateRoot, 'Status path', 'Private storage');
        $privateRoot = PathPolicy::normalizeAbsolute($privateRoot, 'Private storage');
        $statusPath = PathPolicy::normalizeAbsolute($statusPath, 'Status path');
        $statusRelative = str_replace('\\', '/', Path::makeRelative($statusPath, $privateRoot));

        $privateFiles = [];
        foreach (['records', 'revisions', 'definitions', 'vocabulary', 'journal'] as $directory) {
            $source = $privateRoot.DIRECTORY_SEPARATOR.$directory;
            if (is_dir($source) || is_link($source)) {
                foreach ($this->integrity->hashTree($source) as $relative => $hash) {
                    $privateFiles[$directory.'/'.$relative] = $hash;
                }
            }
        }
        if (file_exists($statusPath) || is_link($statusPath)) {
            if (is_link($statusPath) || !is_file($statusPath)) {
                throw new \RuntimeException('Configured backup status path must be a regular file without symbolic links.');
            }
            $statusHash = hash_file('sha256', $statusPath);
            if (!is_string($statusHash)) {
                throw new \RuntimeException('Unable to hash the configured backup status file.');
            }
            $privateFiles[$statusRelative] = $statusHash;
        }
        if (is_link($configPath) || !is_file($configPath)) {
            throw new \RuntimeException('Backup configuration must be a regular file without symbolic links.');
        }
        $configHash = hash_file('sha256', $configPath);
        if (!is_string($configHash)) {
            throw new \RuntimeException('Unable to hash the backup configuration.');
        }
        ksort($privateFiles, SORT_STRING);

        $this->filesystem->mkdir($backupRoot, 0o750);
        $timestamp = $now->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $destination = rtrim($backupRoot, '\\/').DIRECTORY_SEPARATOR.$kind.'-'.$timestamp;
        if (file_exists($destination) || is_link($destination)) {
            throw new \RuntimeException('A backup already exists for this timestamp.');
        }
        $stage = rtrim($backupRoot, '\\/').DIRECTORY_SEPARATOR.'.partial-'.bin2hex(random_bytes(6));
        $this->filesystem->mkdir($stage.'/private', 0o750);
        try {
            foreach (['records', 'revisions', 'definitions', 'vocabulary', 'journal'] as $directory) {
                $source = $privateRoot.DIRECTORY_SEPARATOR.$directory;
                if (is_dir($source)) {
                    $this->filesystem->mirror($source, $stage.'/private/'.$directory);
                }
            }
            if (isset($privateFiles[$statusRelative])) {
                $statusDestination = $stage.'/private/'.$statusRelative;
                $this->filesystem->mkdir(dirname($statusDestination), 0o750);
                $this->filesystem->copy($statusPath, $statusDestination, true);
            }
            $this->filesystem->copy($configPath, $stage.'/config.json', true);
            $expectedFiles = ['config.json' => $configHash];
            foreach ($privateFiles as $relative => $hash) {
                $expectedFiles['private/'.$relative] = $hash;
            }
            ksort($expectedFiles, SORT_STRING);
            $this->integrity->assertExact($stage, $expectedFiles, label: 'Staged backup');
            $manifest = [
                'schema_version' => 1,
                'created_at_utc' => $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
                'kind' => $kind,
                'files' => $expectedFiles,
            ];
            $this->writer->write($stage.'/backup-manifest.json', CanonicalJson::encode($manifest, true));
            if (!rename($stage, $destination)) {
                throw new \RuntimeException('Unable to promote the staged backup.');
            }
        } catch (\Throwable $exception) {
            if (file_exists($stage) || is_link($stage)) {
                $this->filesystem->remove($stage);
            }
            throw $exception;
        }
        $retentionWarning = null;
        try {
            $this->prune($backupRoot, $kind, $retention);
        } catch (\Throwable $exception) {
            $retentionWarning = 'Backup was created, but retention cleanup failed: '.$exception->getMessage();
        }

        return new BackupResult($destination, $retentionWarning);
    }

    /** @return array<string, string> */
    public function verify(string $backupDirectory): array
    {
        $manifestPath = rtrim($backupDirectory, '\\/').DIRECTORY_SEPARATOR.'backup-manifest.json';
        $json = !is_link($manifestPath) && is_file($manifestPath) ? file_get_contents($manifestPath) : false;
        if (false === $json) {
            throw new \RuntimeException('Backup manifest is missing or unreadable.');
        }
        $files = $this->validatedManifestFiles($backupDirectory, $json);
        $this->integrity->assertExact($backupDirectory, $files, ['backup-manifest.json'], 'Backup directory');

        return $files;
    }

    public function restore(string $backupDirectory, string $targetPrivateRoot): void
    {
        $files = $this->verify($backupDirectory);
        if (file_exists($targetPrivateRoot) || is_link($targetPrivateRoot)) {
            throw new \InvalidArgumentException('Restore target must not already exist.');
        }
        $source = rtrim($backupDirectory, '\\/').DIRECTORY_SEPARATOR.'private';
        if (!is_dir($source)) {
            throw new \RuntimeException('Backup private-data directory is missing.');
        }
        $parent = dirname($targetPrivateRoot);
        $this->filesystem->mkdir($parent, 0o750);
        $stage = $parent.DIRECTORY_SEPARATOR.'.restore-stage-'.bin2hex(random_bytes(6));
        $this->filesystem->mkdir($stage, 0o750);
        $privateFiles = [];
        try {
            foreach ($files as $relative => $hash) {
                if (!str_starts_with($relative, 'private/')) {
                    continue;
                }
                $privateRelative = substr($relative, strlen('private/'));
                $sourceFile = rtrim($backupDirectory, '\\/').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (is_link($sourceFile) || !is_file($sourceFile)) {
                    throw new \RuntimeException('Verified backup file changed before restore: '.$relative);
                }
                $this->filesystem->copy($sourceFile, $stage.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $privateRelative), true);
                $privateFiles[$privateRelative] = $hash;
            }
            $this->integrity->assertExact($stage, $privateFiles, label: 'Restored private data');
            if (!rename($stage, $targetPrivateRoot)) {
                throw new \RuntimeException('Unable to promote the validated restore target.');
            }
        } catch (\Throwable $exception) {
            if (file_exists($stage)) {
                $this->filesystem->remove($stage);
            }
            throw $exception;
        }
    }

    private function prune(string $backupRoot, string $kind, int $retention): void
    {
        $directories = glob(rtrim($backupRoot, '\\/').DIRECTORY_SEPARATOR.$kind.'-*', GLOB_ONLYDIR) ?: [];
        $verified = [];
        foreach ($directories as $directory) {
            if (1 !== preg_match('/^'.preg_quote($kind, '/').'-\d{8}T\d{6}Z$/D', basename($directory))) {
                continue;
            }
            try {
                $this->verify($directory);
            } catch (\Throwable) {
                continue;
            }
            $verified[] = $directory;
        }
        rsort($verified, SORT_STRING);
        foreach (array_slice($verified, $retention) as $directory) {
            $normalizedRoot = strtolower(str_replace('\\', '/', rtrim($backupRoot, '\\/')).'/');
            $normalized = strtolower(str_replace('\\', '/', rtrim($directory, '\\/')).'/');
            if (!str_starts_with($normalized, $normalizedRoot)
                || 1 !== preg_match('/^'.preg_quote($kind, '/').'-\d{8}T\d{6}Z$/D', basename($directory))) {
                throw new \RuntimeException('Refusing to prune a path outside the configured backup root.');
            }
            $this->filesystem->remove($directory);
        }
    }

    /** @return array<string, string> */
    private function validatedManifestFiles(string $backupDirectory, string $json): array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Backup manifest is not valid JSON.', previous: $exception);
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new \RuntimeException('Backup manifest must be a JSON object.');
        }
        $required = ['schema_version', 'created_at_utc', 'kind', 'files'];
        $keys = array_keys($decoded);
        sort($keys, SORT_STRING);
        $expectedKeys = $required;
        sort($expectedKeys, SORT_STRING);
        if ($keys !== $expectedKeys
            || 1 !== ($decoded['schema_version'] ?? null)
            || !is_string($decoded['created_at_utc'] ?? null)
            || !is_string($decoded['kind'] ?? null)
            || !in_array($decoded['kind'], ['daily', 'weekly'], true)
            || !is_array($decoded['files'] ?? null)
            || array_is_list($decoded['files'])) {
            throw new \RuntimeException('Backup manifest core fields are invalid.');
        }

        $createdAt = \DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i:s\Z',
            $decoded['created_at_utc'],
            new \DateTimeZone('UTC'),
        );
        if (!$createdAt instanceof \DateTimeImmutable || $createdAt->format('Y-m-d\TH:i:s\Z') !== $decoded['created_at_utc']) {
            throw new \RuntimeException('Backup manifest creation time is invalid.');
        }
        if (1 !== preg_match('/^(daily|weekly)-(\d{8}T\d{6}Z)$/D', basename(rtrim($backupDirectory, '\\/')), $matches)
            || $matches[1] !== $decoded['kind']
            || $matches[2] !== $createdAt->format('Ymd\THis\Z')) {
            throw new \RuntimeException('Backup directory name does not match its manifest.');
        }

        /** @var array<mixed> $rawFiles */
        $rawFiles = $decoded['files'];
        $files = [];
        foreach ($rawFiles as $relative => $hash) {
            if (!is_string($relative) || !is_string($hash)) {
                throw new \RuntimeException('Backup manifest file map is invalid.');
            }
            if ('config.json' !== $relative && !str_starts_with($relative, 'private/')) {
                throw new \RuntimeException('Backup manifest contains an unexpected application path.');
            }
            $files[$relative] = $hash;
        }
        if (!isset($files['config.json'])) {
            throw new \RuntimeException('Backup manifest does not include config.json.');
        }
        ksort($files, SORT_STRING);

        return $files;
    }

    private function assertSeparateRoots(string $privateRoot, string $backupRoot): void
    {
        try {
            PathPolicy::assertDisjoint($privateRoot, $backupRoot, 'Private storage', 'Backup root');
        } catch (\InvalidArgumentException) {
            throw new \InvalidArgumentException('Backup root must be separate from private runtime storage.');
        }
    }
}
