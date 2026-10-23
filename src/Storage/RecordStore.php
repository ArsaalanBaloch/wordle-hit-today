<?php

declare(strict_types=1);

namespace WordleHitToday\Storage;

use WordleHitToday\Support\CanonicalJson;
use WordleHitToday\Validation\RecordValidator;

final readonly class RecordStore
{
    public function __construct(
        private string $privateRoot,
        private AtomicFileWriter $writer,
        private RecordValidator $validator,
    ) {
    }

    /** @param array<string, mixed> $record */
    public function save(array $record): void
    {
        $date = (string) ($record['puzzle_date'] ?? '');
        if (1 !== preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
            throw new \InvalidArgumentException('Record puzzle_date must be a strict ISO date.');
        }

        $this->validator->validate($record);
        $json = CanonicalJson::encode($record, true);
        $path = $this->privateRoot.DIRECTORY_SEPARATOR.'records'.DIRECTORY_SEPARATOR.$matches[1]
            .DIRECTORY_SEPARATOR.$matches[2].DIRECTORY_SEPARATOR.$date.'.json';
        $this->assertWithinRoot($path);
        $this->writer->write($path, $json);
    }

    /** @return array<string, mixed>|null */
    public function find(string $date): ?array
    {
        $path = $this->recordPath($date);
        if (!is_file($path)) {
            return null;
        }
        $json = file_get_contents($path);
        if (false === $json) {
            throw new \RuntimeException(sprintf('Unable to read record: %s', $path));
        }
        /** @var array<string, mixed> $record */
        $record = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        $this->validator->validate($record);

        return $record;
    }

    /** @param array<string, mixed> $record */
    public function saveRevision(array $record): void
    {
        $date = (string) ($record['puzzle_date'] ?? '');
        $revision = (int) ($record['content_revision'] ?? 0);
        $this->validator->validate($record);
        if ($revision < 1) {
            throw new \InvalidArgumentException('Revision number must be positive.');
        }
        $path = $this->privateRoot.DIRECTORY_SEPARATOR.'revisions'.DIRECTORY_SEPARATOR.$date
            .DIRECTORY_SEPARATOR.sprintf('revision-%d.json', $revision);
        $this->assertWithinRoot($path);
        $this->writer->write($path, CanonicalJson::encode($record, true));
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $root = $this->privateRoot.DIRECTORY_SEPARATOR.'records';
        if (!is_dir($root)) {
            return [];
        }

        $records = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if (!$file->isFile() || 'json' !== strtolower($file->getExtension())) {
                continue;
            }

            $json = file_get_contents($file->getPathname());
            if (false === $json) {
                throw new \RuntimeException(sprintf('Unable to read record: %s', $file->getPathname()));
            }
            /** @var array<string, mixed> $record */
            $record = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
            $this->validator->validate($record);
            $records[] = $record;
        }

        usort($records, static fn (array $left, array $right): int => $left['puzzle_date'] <=> $right['puzzle_date']);

        return $records;
    }

    private function assertWithinRoot(string $path): void
    {
        $normalizedRoot = str_replace('\\', '/', rtrim($this->privateRoot, '\\/')).'/';
        $normalizedPath = str_replace('\\', '/', $path);
        if (!str_starts_with(strtolower($normalizedPath), strtolower($normalizedRoot))) {
            throw new \RuntimeException('Resolved record path is outside private storage.');
        }
    }

    private function recordPath(string $date): string
    {
        if (1 !== preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $date, $matches)) {
            throw new \InvalidArgumentException('Record date must be a strict ISO date.');
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        if (!$parsed instanceof \DateTimeImmutable || $parsed->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException('Record date must be a real calendar date.');
        }

        $path = $this->privateRoot.DIRECTORY_SEPARATOR.'records'.DIRECTORY_SEPARATOR.$matches[1]
            .DIRECTORY_SEPARATOR.$matches[2].DIRECTORY_SEPARATOR.$date.'.json';
        $this->assertWithinRoot($path);

        return $path;
    }
}
