<?php

declare(strict_types=1);

namespace WordleHitToday\Hints;

final class HintCalculator
{
    /** @return array{vowel_occurrences: int, has_repeats: bool} */
    public function calculate(string $solution): array
    {
        $normalized = strtoupper($solution);
        if (1 !== preg_match('/^[A-Z]{5}$/', $normalized)) {
            throw new \InvalidArgumentException('A solution must contain exactly five ASCII letters.');
        }

        $letters = str_split($normalized);
        $vowels = array_filter($letters, static fn (string $letter): bool => str_contains('AEIOU', $letter));

        return [
            'vowel_occurrences' => count($vowels),
            'has_repeats' => count(array_unique($letters)) < count($letters),
        ];
    }
}
