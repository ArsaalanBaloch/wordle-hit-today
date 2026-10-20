<?php

declare(strict_types=1);

namespace WordleHitToday\Source;

final readonly class PuzzleNumberResolver
{
    public function __construct(private string $anchorDate, private int $anchorNumber)
    {
        $this->parseDate($anchorDate);
        if ($anchorNumber < 0) {
            throw new \InvalidArgumentException('Puzzle-number anchor cannot be negative.');
        }
    }

    public function forDate(string $date): int
    {
        $anchor = $this->parseDate($this->anchorDate);
        $target = $this->parseDate($date);
        $number = $this->anchorNumber + (int) $anchor->diff($target)->format('%r%a');
        if ($number < 0) {
            throw new \InvalidArgumentException('Resolved puzzle number cannot be negative.');
        }

        return $number;
    }

    private function parseDate(string $date): \DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        if (!$parsed instanceof \DateTimeImmutable || $parsed->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException(sprintf('Invalid ISO date: %s', $date));
        }

        return $parsed;
    }
}
