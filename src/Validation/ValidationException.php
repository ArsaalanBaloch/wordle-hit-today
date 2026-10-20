<?php

declare(strict_types=1);

namespace WordleHitToday\Validation;

final class ValidationException extends \RuntimeException
{
    /** @param array<mixed> $details */
    public function __construct(string $message, public readonly array $details = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
