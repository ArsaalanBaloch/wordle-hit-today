<?php

declare(strict_types=1);

namespace WordleHitToday\Monitoring;

use WordleHitToday\Storage\AtomicFileWriter;
use WordleHitToday\Support\CanonicalJson;
use WordleHitToday\Validation\SchemaValidator;

final readonly class StatusStore
{
    public function __construct(
        private string $path,
        private AtomicFileWriter $writer,
        private SchemaValidator $schemas,
        private int $minimumPollMinutes,
    ) {
    }

    public function heartbeat(\DateTimeImmutable $now): void
    {
        $status = $this->load();
        $status['heartbeat_at_utc'] = $this->timestamp($now);
        $this->save($status);
    }

    public function shouldAttempt(string $date, \DateTimeImmutable $now): bool
    {
        $status = $this->load();
        /** @var array<string, mixed>|null $dateStatus */
        $dateStatus = is_array($status['dates'][$date] ?? null) ? $status['dates'][$date] : null;
        if (null === $dateStatus) {
            return true;
        }
        if (($dateStatus['complete'] ?? false) === true) {
            return false;
        }
        $next = $dateStatus['next_attempt_at_utc'] ?? null;

        return !is_string($next) || new \DateTimeImmutable($next) <= $now;
    }

    public function success(string $date, \DateTimeImmutable $now): void
    {
        $status = $this->load();
        /** @var array<string, mixed> $previous */
        $previous = is_array($status['dates'][$date] ?? null) ? $status['dates'][$date] : [];
        $checks = min(4, (int) ($previous['successful_checks'] ?? 0) + 1);
        $delays = [1 => 60, 2 => 300, 3 => 1080];
        $complete = $checks >= 4;
        $delayMinutes = $complete ? 0 : max($this->minimumPollMinutes, $delays[$checks]);
        $status['dates'][$date] = [
            'retry_count' => 0,
            'successful_checks' => $checks,
            'next_attempt_at_utc' => $complete ? null : $this->timestamp($now->modify(sprintf('+%d minutes', $delayMinutes))),
            'last_result' => 'success',
            'last_error_category' => null,
            'complete' => $complete,
        ];
        $status['last_source_success_at_utc'] = $this->timestamp($now);
        $this->save($status);
    }

    public function failure(
        string $date,
        \DateTimeImmutable $now,
        string $category,
        ?int $retryAfterSeconds = null,
    ): void {
        $status = $this->load();
        /** @var array<string, mixed> $previous */
        $previous = is_array($status['dates'][$date] ?? null) ? $status['dates'][$date] : [];
        $retries = (int) ($previous['retry_count'] ?? 0) + 1;
        $quarantined = in_array($category, ['invalid_payload', 'anomaly'], true);
        $minutes = [5, 15, 30, 60][min($retries - 1, 3)];
        if ('denied' === $category) {
            $minutes = 1440;
        }
        $minutes = max($this->minimumPollMinutes, $minutes);
        if (null !== $retryAfterSeconds) {
            $minutes = max($this->minimumPollMinutes, (int) ceil($retryAfterSeconds / 60));
        }
        $status['dates'][$date] = [
            'retry_count' => $retries,
            'successful_checks' => (int) ($previous['successful_checks'] ?? 0),
            'next_attempt_at_utc' => $quarantined ? null : $this->timestamp($now->modify(sprintf('+%d minutes', $minutes))),
            'last_result' => $quarantined ? 'quarantined' : 'failed',
            'last_error_category' => substr($category, 0, 80),
            'complete' => $quarantined,
        ];
        $this->save($status);
    }

    public function publication(\DateTimeImmutable $now): void
    {
        $status = $this->load();
        $status['last_publication_at_utc'] = $this->timestamp($now);
        $this->save($status);
    }

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return $this->load();
    }

    /** @return array<string, mixed> */
    private function load(): array
    {
        if (!is_file($this->path)) {
            return [
                'schema_version' => 1,
                'heartbeat_at_utc' => null,
                'last_source_success_at_utc' => null,
                'last_publication_at_utc' => null,
                'dates' => [],
            ];
        }
        $json = file_get_contents($this->path);
        if (false === $json) {
            throw new \RuntimeException('Scheduler status is not readable.');
        }
        $this->schemas->validateJson($json, 'status.schema.json');
        /** @var array<string, mixed> $status */
        $status = json_decode($json, true, 32, JSON_THROW_ON_ERROR);

        return $status;
    }

    /** @param array<string, mixed> $status */
    private function save(array $status): void
    {
        if ([] === $status['dates']) {
            $status['dates'] = new \stdClass();
        }
        $json = CanonicalJson::encode($status, true);
        $this->schemas->validateJson($json, 'status.schema.json');
        $this->writer->write($this->path, $json);
    }

    private function timestamp(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }
}
