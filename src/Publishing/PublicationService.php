<?php

declare(strict_types=1);

namespace WordleHitToday\Publishing;

use WordleHitToday\Clock\GlobalDateEligibility;
use WordleHitToday\Config\SiteConfig;
use WordleHitToday\Definitions\DefinitionStore;
use WordleHitToday\Storage\AtomicFileWriter;
use WordleHitToday\Storage\DirectoryIntegrity;
use WordleHitToday\Storage\RecordStore;
use WordleHitToday\Support\CanonicalJson;
use WordleHitToday\Validation\RecordValidator;
use WordleHitToday\Validation\SchemaValidator;

final readonly class PublicationService
{
    public function __construct(
        private string $projectRoot,
        private SiteConfig $config,
        private AtomicFileWriter $writer,
        private RecordStore $records,
        private RecordValidator $recordValidator,
        private SchemaValidator $schemas,
        private DefinitionStore $definitions,
    ) {
    }

    public function publish(\DateTimeImmutable $builtAt): BuildResult
    {
        $records = $this->records->all();
        $runId = bin2hex(random_bytes(12));
        $recordsHash = $this->recordsHash($records);
        $planned = null;
        $promotionCompleted = false;
        $builder = new SiteBuilder(
            $this->projectRoot,
            $this->config,
            $this->writer,
            $this->recordValidator,
            $this->schemas,
            new GlobalDateEligibility(),
            $this->definitions,
        );
        try {
            $result = $builder->build(
                $records,
                $builtAt,
                function (BuildResult $staged) use (&$planned, $runId, $recordsHash): void {
                    $planned = $staged;
                    $this->writeJournal($runId, $recordsHash, $staged, 'staged', false, null);
                },
            );
            $promotionCompleted = true;
            $this->writeJournal($runId, $recordsHash, $result, 'published', true, null);
        } catch (\Throwable $exception) {
            $failedPlan = $planned ?? $this->fallbackBuildResult($records, $builtAt);
            if (null !== $failedPlan) {
                try {
                    $this->writeJournal(
                        $runId,
                        $recordsHash,
                        $failedPlan,
                        'failed',
                        $promotionCompleted,
                        get_debug_type($exception),
                    );
                } catch (\Throwable $journalException) {
                    throw new \RuntimeException(
                        'Publication failed and its failed-state journal could not be written: '.$journalException->getMessage(),
                        previous: $exception,
                    );
                }
            }

            throw $exception;
        }

        return $result;
    }

    public function needsPublication(?\DateTimeImmutable $at = null): bool
    {
        $records = $this->records->all();
        if ([] === $records) {
            return false;
        }

        try {
            $journalPath = $this->config->privateStorage().'/journal/last-build.json';
            $json = !is_link($journalPath) && is_file($journalPath) ? file_get_contents($journalPath) : false;
            if (false === $json) {
                return true;
            }
            $this->schemas->validateJson($json, 'journal.schema.json');
            /** @var array<string, mixed> $journal */
            $journal = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            if ('published' !== $journal['stage']
                || true !== $journal['promoted']
                || $this->config->mode() !== $journal['mode']
                || !hash_equals($this->recordsHash($records), (string) $journal['records_sha256'])) {
                return true;
            }
            $at ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
            if ($journal['record_date'] !== $this->homepageDateAt($records, $at)) {
                return true;
            }

            $expected = [];
            /** @var list<mixed> $files */
            $files = $journal['files'];
            foreach ($files as $entry) {
                if (!is_array($entry) || !is_string($entry['path'] ?? null) || !is_string($entry['sha256'] ?? null)) {
                    return true;
                }
                if (isset($expected[$entry['path']])) {
                    return true;
                }
                $expected[$entry['path']] = $entry['sha256'];
            }
            $differences = new DirectoryIntegrity()->differences($this->config->publicOutput(), $expected);

            return [] !== $differences['missing'] || [] !== $differences['changed'] || [] !== $differences['extra'];
        } catch (\Throwable) {
            return true;
        }
    }

    /** @param list<array<string, mixed>> $records */
    private function homepageDateAt(array $records, \DateTimeImmutable $at): string
    {
        usort($records, static fn (array $left, array $right): int => $left['puzzle_date'] <=> $right['puzzle_date']);
        $utcDate = $at->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');
        $eligible = array_values(array_filter(
            $records,
            static fn (array $record): bool => $record['puzzle_date'] <= $utcDate,
        ));
        $record = [] !== $eligible ? $eligible[count($eligible) - 1] : $records[count($records) - 1];

        return (string) $record['puzzle_date'];
    }

    /** @param list<array<string, mixed>> $records */
    private function recordsHash(array $records): string
    {
        return hash('sha256', CanonicalJson::encode($records));
    }

    private function writeJournal(
        string $runId,
        string $recordsHash,
        BuildResult $result,
        string $stage,
        bool $promoted,
        ?string $errorClass,
    ): void {
        $journal = [
            'schema_version' => 1,
            'run_id' => $runId,
            'mode' => $this->config->mode(),
            'record_date' => $result->homepageDate,
            'record_revision' => $result->homepageRevision,
            'records_sha256' => $recordsHash,
            'stage' => $stage,
            'files' => array_map(
                static fn (string $path, string $hash): array => ['path' => $path, 'sha256' => $hash],
                array_keys($result->checksums),
                array_values($result->checksums),
            ),
            'promoted' => $promoted,
            'error_class' => $errorClass,
        ];
        $journalJson = CanonicalJson::encode($journal, true);
        $this->schemas->validateJson($journalJson, 'journal.schema.json');
        $this->writer->write($this->config->privateStorage().'/journal/last-build.json', $journalJson);
    }

    /**
     * @param list<array<string, mixed>> $records
     */
    private function fallbackBuildResult(array $records, \DateTimeImmutable $builtAt): ?BuildResult
    {
        if ([] === $records) {
            return null;
        }
        usort($records, static fn (array $left, array $right): int => $left['puzzle_date'] <=> $right['puzzle_date']);
        $utcDate = $builtAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');
        $eligible = array_values(array_filter(
            $records,
            static fn (array $record): bool => $record['puzzle_date'] <= $utcDate,
        ));
        $record = [] !== $eligible ? $eligible[count($eligible) - 1] : $records[count($records) - 1];

        return new BuildResult(
            $this->config->publicOutput(),
            0,
            [],
            (string) $record['puzzle_date'],
            (int) $record['content_revision'],
        );
    }
}
