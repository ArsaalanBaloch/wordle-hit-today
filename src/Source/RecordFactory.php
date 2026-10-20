<?php

declare(strict_types=1);

namespace WordleHitToday\Source;

use WordleHitToday\Hints\HintCalculator;
use WordleHitToday\Support\CanonicalJson;

final readonly class RecordFactory
{
    public function __construct(
        private PuzzleNumberResolver $numbers,
        private HintCalculator $hints,
    ) {
    }

    /**
     * @param array{print_date: string, solution: string, days_since_launch: int, id: int|string, editor?: string} $payload
     * @return array<string, mixed>
     */
    public function production(
        array $payload,
        string $sourceUrl,
        \DateTimeImmutable $checkedAt,
        int $revision = 1,
        ?string $definitionReference = null,
        ?string $correctionNotice = null,
    ): array {
        $number = $this->numbers->forDate($payload['print_date']);
        if ($payload['days_since_launch'] !== $number) {
            throw new SourceFailure(
                'anomaly',
                sprintf(
                    'Source days_since_launch %d conflicts with anchored puzzle number %d.',
                    $payload['days_since_launch'],
                    $number,
                ),
            );
        }

        $timestamp = $checkedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        $record = [
            'schema_version' => 1,
            'mode' => 'production',
            'puzzle_date' => $payload['print_date'],
            'puzzle_number' => $number,
            'upstream_id' => $payload['id'],
            'solution' => $payload['solution'],
            'source_url' => $sourceUrl,
            'fetched_at_utc' => $timestamp,
            'source_checked_at_utc' => $timestamp,
            'validation_method' => 'nyt_structural_validation',
            'content_revision' => $revision,
            'record_state' => 'validated',
            'hint_values' => $this->hints->calculate($payload['solution']),
            'definition_reference' => $definitionReference,
        ];
        if (null !== $correctionNotice) {
            $record['correction_notice'] = $correctionNotice;
        }
        $record['content_hash'] = hash('sha256', CanonicalJson::encode($record));

        return $record;
    }

    /**
     * Create a source-validated record for a private, non-indexable fixture preview.
     *
     * The fixture mode is deliberate: it keeps robots blocked and does not imply
     * that the demo vocabulary or archive is production-complete.
     *
     * @param array{print_date: string, solution: string, days_since_launch: int, id: int|string, editor?: string} $payload
     * @return array<string, mixed>
     */
    public function privatePreview(
        array $payload,
        string $sourceUrl,
        \DateTimeImmutable $checkedAt,
        ?string $definitionReference = null,
    ): array {
        $timestamp = $checkedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
        $record = [
            'schema_version' => 1,
            'mode' => 'fixture',
            'puzzle_date' => $payload['print_date'],
            'puzzle_number' => $payload['days_since_launch'],
            'upstream_id' => $payload['id'],
            'solution' => $payload['solution'],
            'source_url' => $sourceUrl,
            'fetched_at_utc' => $timestamp,
            'source_checked_at_utc' => $timestamp,
            'validation_method' => 'nyt_structural_validation',
            'content_revision' => 1,
            'record_state' => 'validated',
            'hint_values' => $this->hints->calculate($payload['solution']),
            'definition_reference' => $definitionReference,
        ];
        $record['content_hash'] = hash('sha256', CanonicalJson::encode($record));

        return $record;
    }
}
