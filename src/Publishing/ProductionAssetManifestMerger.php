<?php

declare(strict_types=1);

namespace WordleHitToday\Publishing;

final class ProductionAssetManifestMerger
{
    public static function mergeFiles(string $sourcePath, string $productionPath, string $outputPath): void
    {
        $source = self::read($sourcePath, 'Source');
        $production = self::read($productionPath, 'Production');
        if (1 !== ($source['schema_version'] ?? null)) {
            throw new \RuntimeException('Source asset manifest schema version is unsupported.');
        }
        if ('production' !== ($production['vocabulary_mode'] ?? null)
            || !is_string($production['vocabulary'] ?? null)) {
            throw new \RuntimeException('Current production vocabulary manifest is invalid.');
        }

        $source['vocabulary'] = $production['vocabulary'];
        $source['vocabulary_mode'] = 'production';
        $json = json_encode(
            $source,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ).PHP_EOL;
        if (false === file_put_contents($outputPath, $json)) {
            throw new \RuntimeException('Unable to write the merged production asset manifest.');
        }
    }

    /** @return array<string, mixed> */
    private static function read(string $path, string $label): array
    {
        $json = file_get_contents($path);
        if (false === $json) {
            throw new \RuntimeException($label.' asset manifest is not readable.');
        }
        $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new \RuntimeException($label.' asset manifest must be a JSON object.');
        }

        return $decoded;
    }

    private function __construct()
    {
    }
}
