<?php

declare(strict_types=1);

namespace WordleHitToday\Solver;

final class FeedbackEvaluator
{
    /** @return list<Feedback> */
    public function evaluate(string $answer, string $guess): array
    {
        $answer = $this->normalizeWord($answer);
        $guess = $this->normalizeWord($guess);
        $answerLetters = str_split($answer);
        $guessLetters = str_split($guess);
        $feedback = array_fill(0, 5, Feedback::Absent);
        $remaining = [];

        for ($index = 0; $index < 5; ++$index) {
            if ($guessLetters[$index] === $answerLetters[$index]) {
                $feedback[$index] = Feedback::Correct;
                continue;
            }

            $letter = $answerLetters[$index];
            $remaining[$letter] = ($remaining[$letter] ?? 0) + 1;
        }

        for ($index = 0; $index < 5; ++$index) {
            if (Feedback::Correct === $feedback[$index]) {
                continue;
            }

            $letter = $guessLetters[$index];
            if (($remaining[$letter] ?? 0) > 0) {
                $feedback[$index] = Feedback::Present;
                --$remaining[$letter];
            }
        }

        return $feedback;
    }

    /**
     * @param list<array{guess: string, feedback: list<Feedback>}> $rows
     * @param list<string> $candidates
     * @return list<string>
     */
    public function filter(array $candidates, array $rows): array
    {
        $completedRows = array_filter(
            $rows,
            static fn (array $row): bool => 5 === count($row['feedback'])
                && !in_array(Feedback::Unset, $row['feedback'], true),
        );

        return array_values(array_filter(
            $candidates,
            function (string $candidate) use ($completedRows): bool {
                foreach ($completedRows as $row) {
                    if ($this->evaluate($candidate, $row['guess']) !== $row['feedback']) {
                        return false;
                    }
                }

                return true;
            },
        ));
    }

    private function normalizeWord(string $word): string
    {
        $normalized = strtoupper($word);
        if (1 !== preg_match('/^[A-Z]{5}$/', $normalized)) {
            throw new \InvalidArgumentException('Words must contain exactly five ASCII letters.');
        }

        return $normalized;
    }
}
