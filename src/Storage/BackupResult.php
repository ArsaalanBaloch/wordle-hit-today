<?php

declare(strict_types=1);

namespace WordleHitToday\Storage;

final readonly class BackupResult
{
    public function __construct(
        public string $path,
        public ?string $retentionWarning = null,
    ) {
    }
}
