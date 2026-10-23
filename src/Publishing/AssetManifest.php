<?php

declare(strict_types=1);

namespace WordleHitToday\Publishing;

use WordleHitToday\Storage\DirectoryIntegrity;

final class AssetManifest
{
    /** @return array<string, mixed> */
    public static function loadSnapshot(string $staticRoot, string $expectedMode): array
    {
        $hashes = new DirectoryIntegrity()->hashTree($staticRoot);
        $manifestPath = rtrim($staticRoot, '\\/').DIRECTORY_SEPARATOR.'manifest.json';
        $json = is_file($manifestPath) ? file_get_contents($manifestPath) : false;
        if (false === $json || !isset($hashes['manifest.json']) || !hash_equals($hashes['manifest.json'], hash('sha256', $json))) {
            throw new \RuntimeException('Asset manifest is missing, unreadable, or changed during validation.');
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Asset manifest is not valid JSON.', previous: $exception);
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new \RuntimeException('Asset manifest must be a JSON object.');
        }

        self::assertExactKeys($decoded, [
            'schema_version',
            'css',
            'scripts',
            'fonts',
            'vocabulary',
            'vocabulary_mode',
            'licenses',
        ], 'Asset manifest');
        if (1 !== $decoded['schema_version']) {
            throw new \RuntimeException('Asset manifest schema version is unsupported.');
        }
        if (!is_string($decoded['vocabulary_mode']) || !in_array($decoded['vocabulary_mode'], ['fixture', 'production'], true)) {
            throw new \RuntimeException('Asset manifest vocabulary mode is invalid.');
        }
        if ($decoded['vocabulary_mode'] !== $expectedMode) {
            throw new \RuntimeException(sprintf(
                'Asset vocabulary mode %s does not match configuration mode %s.',
                $decoded['vocabulary_mode'],
                $expectedMode,
            ));
        }

        $scripts = self::requireObject($decoded, 'scripts', ['main', 'solver', 'archive']);
        $fonts = self::requireObject($decoded, 'fonts', ['inter400', 'inter600', 'manrope700']);
        $licenses = self::requireObject($decoded, 'licenses', ['inter', 'manrope']);
        if (!is_string($decoded['css']) || !is_string($decoded['vocabulary'])) {
            throw new \RuntimeException('Asset manifest CSS and vocabulary paths must be strings.');
        }

        $referenced = [];
        self::validateAsset($decoded['css'], '#^site\.([a-f0-9]{12})\.css$#D', $hashes, $referenced, 'CSS');
        self::validateAsset($scripts['main'], '#^main\.([a-f0-9]{12})\.js$#D', $hashes, $referenced, 'main script');
        self::validateAsset($scripts['solver'], '#^solver\.([a-f0-9]{12})\.js$#D', $hashes, $referenced, 'solver script');
        self::validateAsset($scripts['archive'], '#^archive\.([a-f0-9]{12})\.js$#D', $hashes, $referenced, 'archive script');
        self::validateAsset($fonts['inter400'], '#^fonts/inter400\.([a-f0-9]{12})\.woff2$#D', $hashes, $referenced, 'Inter 400 font');
        self::validateAsset($fonts['inter600'], '#^fonts/inter600\.([a-f0-9]{12})\.woff2$#D', $hashes, $referenced, 'Inter 600 font');
        self::validateAsset($fonts['manrope700'], '#^fonts/manrope700\.([a-f0-9]{12})\.woff2$#D', $hashes, $referenced, 'Manrope 700 font');
        self::validateAsset(
            $decoded['vocabulary'],
            '#^solver-'.preg_quote($expectedMode, '#').'\.([a-f0-9]{12})\.json$#D',
            $hashes,
            $referenced,
            'solver vocabulary',
        );
        self::validateAsset($licenses['inter'], '#^licenses/Inter-OFL-1\.1\.([a-f0-9]{12})\.txt$#D', $hashes, $referenced, 'Inter license');
        self::validateAsset($licenses['manrope'], '#^licenses/Manrope-OFL-1\.1\.([a-f0-9]{12})\.txt$#D', $hashes, $referenced, 'Manrope license');

        $expectedFiles = array_merge(['manifest.json'], $referenced);
        sort($expectedFiles, SORT_STRING);
        $actualFiles = array_keys($hashes);
        sort($actualFiles, SORT_STRING);
        if ($expectedFiles !== $actualFiles) {
            throw new \RuntimeException('Asset snapshot contains missing, duplicate, or unreferenced files.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @param array<mixed> $object
     * @param list<string> $expected
     */
    private static function assertExactKeys(array $object, array $expected, string $label): void
    {
        $keys = array_keys($object);
        sort($keys, SORT_STRING);
        sort($expected, SORT_STRING);
        if ($keys !== $expected) {
            throw new \RuntimeException($label.' has unexpected or missing fields.');
        }
    }

    /**
     * @param array<string, mixed> $manifest
     * @param list<string>         $keys
     *
     * @return array<string, string>
     */
    private static function requireObject(array $manifest, string $field, array $keys): array
    {
        $value = $manifest[$field];
        if (!is_array($value) || array_is_list($value)) {
            throw new \RuntimeException(sprintf('Asset manifest %s must be an object.', $field));
        }
        self::assertExactKeys($value, $keys, sprintf('Asset manifest %s', $field));
        foreach ($value as $path) {
            if (!is_string($path)) {
                throw new \RuntimeException(sprintf('Asset manifest %s paths must be strings.', $field));
            }
        }

        /** @var array<string, string> $value */
        return $value;
    }

    /**
     * @param array<string, string> $hashes
     * @param list<string>          $referenced
     */
    private static function validateAsset(
        string $url,
        string $filenamePattern,
        array $hashes,
        array &$referenced,
        string $label,
        bool $verifyFilenameHash = true,
    ): void {
        if (1 !== preg_match('#^/assets/static/([A-Za-z0-9][A-Za-z0-9._-]*(?:/[A-Za-z0-9][A-Za-z0-9._-]*)*)$#D', $url, $pathMatch)) {
            throw new \RuntimeException(sprintf('Asset manifest %s path is unsafe.', $label));
        }
        $relative = $pathMatch[1];
        if (1 !== preg_match($filenamePattern, $relative, $filenameMatch)) {
            throw new \RuntimeException(sprintf('Asset manifest %s filename is invalid.', $label));
        }
        if (!isset($hashes[$relative])) {
            throw new \RuntimeException(sprintf('Asset manifest %s file is missing from the staged snapshot.', $label));
        }
        if ($verifyFilenameHash && !hash_equals($filenameMatch[1], substr($hashes[$relative], 0, 12))) {
            throw new \RuntimeException(sprintf('Asset manifest %s content hash does not match its filename.', $label));
        }
        $referenced[] = $relative;
    }

    private function __construct()
    {
    }
}
