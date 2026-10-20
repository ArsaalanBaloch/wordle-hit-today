<?php

declare(strict_types=1);

namespace WordleHitToday\Clock;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
