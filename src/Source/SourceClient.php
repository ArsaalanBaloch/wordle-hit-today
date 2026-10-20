<?php

declare(strict_types=1);

namespace WordleHitToday\Source;

interface SourceClient
{
    /**
     * @return array{print_date: string, solution: string, days_since_launch: int, id: int|string, editor?: string}
     */
    public function fetch(string $date): array;

    public function urlFor(string $date): string;
}
