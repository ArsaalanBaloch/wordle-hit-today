<?php

declare(strict_types=1);

namespace WordleHitToday\Source;

final class SourcePayloadValidator
{
    /**
     * @return array{print_date: string, solution: string, days_since_launch: int, id: int|string, editor?: string}
     */
    public function validate(string $json, string $expectedDate): array
    {
        if (strlen($json) > 65_536) {
            throw new SourceFailure('invalid_payload', 'Source response exceeds the 64 KiB validation ceiling.');
        }

        try {
            $payload = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new SourceFailure('invalid_payload', 'Source response is not valid bounded JSON.', previous: $exception);
        }

        if (!is_array($payload) || array_is_list($payload)) {
            throw new SourceFailure('invalid_payload', 'Source response must be a JSON object.');
        }
        if (($payload['print_date'] ?? null) !== $expectedDate) {
            throw new SourceFailure('invalid_payload', 'Source print_date does not match the requested date.');
        }
        $solution = $payload['solution'] ?? null;
        if (!is_string($solution) || 1 !== preg_match('/^[A-Za-z]{5}$/D', $solution)) {
            throw new SourceFailure('invalid_payload', 'Source solution must contain exactly five ASCII letters.');
        }
        $days = $payload['days_since_launch'] ?? null;
        if (!is_int($days) || $days < 0) {
            throw new SourceFailure('invalid_payload', 'Source days_since_launch must be a non-negative integer.');
        }
        $id = $payload['id'] ?? null;
        if ((!is_int($id) && !is_string($id)) || (is_string($id) && ('' === $id || strlen($id) > 128))) {
            throw new SourceFailure('invalid_payload', 'Source id must be a bounded string or integer.');
        }
        $editor = $payload['editor'] ?? null;
        if (null !== $editor && (!is_string($editor) || strlen($editor) > 200)) {
            throw new SourceFailure('invalid_payload', 'Optional source editor is invalid or too long.');
        }

        $validated = [
            'print_date' => $expectedDate,
            'solution' => strtoupper($solution),
            'days_since_launch' => $days,
            'id' => $id,
        ];
        if (is_string($editor)) {
            $validated['editor'] = $editor;
        }

        return $validated;
    }
}
