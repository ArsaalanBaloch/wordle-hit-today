<?php

declare(strict_types=1);

namespace WordleHitToday\Publishing;

final readonly class BuildResult
{
    /** @param array<string, string> $checksums */
    public function __construct(
        public string $outputDirectory,
        public int $pageCount,
        public array $checksums,
        public string $homepageDate,
    ) {
    }
}
