<?php

declare(strict_types=1);

namespace WordleHitToday\Fixtures;

use WordleHitToday\Hints\HintCalculator;
use WordleHitToday\Support\CanonicalJson;

final readonly class FixtureFactory
{
    public function __construct(private HintCalculator $hints)
    {
    }

    /** @return list<array<string, mixed>> */
    public function records(): array
    {
        return [
            $this->create('2026-10-15', 900015, 'PLANT', 'fixture-2026-10-15'),
            $this->create('2026-10-16', 900016, 'CRANE', 'fixture-2026-10-16'),
            $this->create('2026-10-17', 900017, 'LEVEL', 'fixture-2026-10-17'),
        ];
    }

    /** @return array<string, mixed> */
    private function create(string $date, int $number, string $solution, string $upstreamId): array
    {
        $record = [
            'schema_version' => 1,
            'mode' => 'fixture',
            'puzzle_date' => $date,
            'puzzle_number' => $number,
            'upstream_id' => $upstreamId,
            'solution' => $solution,
            'source_url' => null,
            'fetched_at_utc' => null,
            'source_checked_at_utc' => null,
            'validation_method' => 'synthetic_fixture',
            'content_revision' => 1,
            'record_state' => 'validated',
            'hint_values' => $this->hints->calculate($solution),
            'definition_reference' => null,
        ];
        $record['content_hash'] = hash('sha256', CanonicalJson::encode($record));

        return $record;
    }
}
