<?php

declare(strict_types=1);

namespace WordleHitToday\Support;

final class CanonicalJson
{
    /** @param array<array-key, mixed> $data */
    public static function encode(array $data, bool $pretty = false): string
    {
        $normalized = self::sortRecursively($data);
        $flags = JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode($normalized, $flags).($pretty ? "\n" : '');
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function sortRecursively(array $data): array
    {
        if (!array_is_list($data)) {
            ksort($data, SORT_STRING);
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::sortRecursively($value);
            }
        }

        return $data;
    }
}
