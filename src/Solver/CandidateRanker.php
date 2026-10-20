<?php

declare(strict_types=1);

namespace WordleHitToday\Solver;

final class CandidateRanker
{
    /**
     * @param list<string> $candidates
     * @param array<string, int> $commonnessRanks Lower values are more common.
     * @return list<array{word: string, score: float}>
     */
    public function rank(array $candidates, array $commonnessRanks = []): array
    {
        if ([] === $candidates) {
            return [];
        }

        $normalized = array_map(static fn (string $word): string => strtoupper($word), $candidates);
        $documentFrequency = [];
        foreach ($normalized as $word) {
            if (1 !== preg_match('/^[A-Z]{5}$/', $word)) {
                throw new \InvalidArgumentException(sprintf('Invalid candidate: %s', $word));
            }

            foreach (array_unique(str_split($word)) as $letter) {
                $documentFrequency[$letter] = ($documentFrequency[$letter] ?? 0) + 1;
            }
        }

        $count = count($normalized);
        $ranked = array_map(
            static function (string $word) use ($documentFrequency, $count): array {
                $score = 0;
                foreach (array_unique(str_split($word)) as $letter) {
                    $score += $documentFrequency[$letter];
                }

                return ['word' => $word, 'score' => $score / $count];
            },
            $normalized,
        );

        usort(
            $ranked,
            static function (array $left, array $right) use ($commonnessRanks): int {
                $scoreOrder = $right['score'] <=> $left['score'];
                if (0 !== $scoreOrder) {
                    return $scoreOrder;
                }

                $leftRank = $commonnessRanks[$left['word']] ?? PHP_INT_MAX;
                $rightRank = $commonnessRanks[$right['word']] ?? PHP_INT_MAX;

                return ($leftRank <=> $rightRank) ?: ($left['word'] <=> $right['word']);
            },
        );

        return $ranked;
    }
}
