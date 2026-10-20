<?php

declare(strict_types=1);

namespace WordleHitToday\Validation;

use WordleHitToday\Hints\HintCalculator;
use WordleHitToday\Support\CanonicalJson;

final readonly class RecordValidator
{
    public function __construct(
        private SchemaValidator $schemas,
        private HintCalculator $hints,
    ) {
    }

    /** @param array<string, mixed> $record */
    public function validate(array $record): void
    {
        $this->schemas->validateJson(CanonicalJson::encode($record), 'record.schema.json');

        $date = (string) $record['puzzle_date'];
        $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        if (!$parsedDate instanceof \DateTimeImmutable || $parsedDate->format('Y-m-d') !== $date) {
            throw new ValidationException('Record puzzle_date is not a real ISO calendar date.');
        }

        $expectedHints = $this->hints->calculate((string) $record['solution']);
        if (!is_array($record['hint_values'])
            || ($record['hint_values']['vowel_occurrences'] ?? null) !== $expectedHints['vowel_occurrences']
            || ($record['hint_values']['has_repeats'] ?? null) !== $expectedHints['has_repeats']) {
            throw new ValidationException('Stored hint values do not match the normalized solution.');
        }

        $hashInput = $record;
        unset($hashInput['content_hash']);
        $expectedHash = hash('sha256', CanonicalJson::encode($hashInput));
        if (!hash_equals($expectedHash, (string) $record['content_hash'])) {
            throw new ValidationException('Record content hash does not match its canonical content.');
        }

        if ('nyt_structural_validation' === $record['validation_method']) {
            foreach (['source_url', 'fetched_at_utc', 'source_checked_at_utc'] as $field) {
                if (!is_string($record[$field]) || '' === $record[$field]) {
                    throw new ValidationException(sprintf('Source-validated record field %s is required.', $field));
                }
            }
            $host = parse_url((string) $record['source_url'], PHP_URL_HOST);
            $scheme = parse_url((string) $record['source_url'], PHP_URL_SCHEME);
            if ('https' !== $scheme || 'www.nytimes.com' !== $host) {
                throw new ValidationException('Source-validated record URL is not on the configured HTTPS host.');
            }
            if (str_starts_with((string) $record['upstream_id'], 'fixture-') || (int) $record['puzzle_number'] >= 900000) {
                throw new ValidationException('Source-validated records cannot use fixture identifiers or numbers.');
            }
        }

        if ('production' === $record['mode']) {
            if ('nyt_structural_validation' !== $record['validation_method']) {
                throw new ValidationException('Production records require NYT structural validation.');
            }
        }
    }
}
