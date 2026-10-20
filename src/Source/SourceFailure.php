<?php

declare(strict_types=1);

namespace WordleHitToday\Source;

final class SourceFailure extends \RuntimeException
{
    public function __construct(
        public readonly string $category,
        string $message,
        public readonly ?int $retryAfterSeconds = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
