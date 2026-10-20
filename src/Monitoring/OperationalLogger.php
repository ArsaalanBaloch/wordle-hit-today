<?php

declare(strict_types=1);

namespace WordleHitToday\Monitoring;

use WordleHitToday\Storage\AtomicFileWriter;
use WordleHitToday\Support\CanonicalJson;

final readonly class OperationalLogger
{
    private const int MAX_FILE_BYTES = 5_242_880;

    public function __construct(private string $privateRoot, private AtomicFileWriter $writer)
    {
    }

    /** @param array<string, int|string|null> $fields */
    public function write(\DateTimeImmutable $now, array $fields): void
    {
        $date = $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');
        $path = $this->privateRoot.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.$date.'.ndjson';
        $existing = is_file($path) ? file_get_contents($path) : '';
        if (false === $existing) {
            throw new \RuntimeException('Operational log is not readable.');
        }
        $entry = ['at_utc' => $now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z')] + $fields;
        $line = CanonicalJson::encode($entry)."\n";
        if (strlen($existing) + strlen($line) > self::MAX_FILE_BYTES) {
            throw new \RuntimeException('Daily operational log reached its 5 MiB safety cap.');
        }
        $this->writer->write($path, $existing.$line);
        $this->removeExpired($now);
    }

    private function removeExpired(\DateTimeImmutable $now): void
    {
        $directory = $this->privateRoot.DIRECTORY_SEPARATOR.'logs';
        if (!is_dir($directory)) {
            return;
        }
        $cutoff = $now->modify('-30 days')->setTime(0, 0)->getTimestamp();
        foreach (glob($directory.DIRECTORY_SEPARATOR.'*.ndjson') ?: [] as $path) {
            $modified = filemtime($path);
            if (is_int($modified) && $modified < $cutoff) {
                unlink($path);
            }
        }
    }
}
