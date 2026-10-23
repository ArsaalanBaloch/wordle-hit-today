<?php

declare(strict_types=1);

namespace WordleHitToday\Publishing;

use WordleHitToday\Storage\AtomicFileWriter;
use WordleHitToday\Storage\DirectoryIntegrity;
use WordleHitToday\Support\CanonicalJson;

final class PublicBuildIntegrity
{
    public const MANIFEST_PATH = 'build-integrity.json';

    public function __construct(private readonly DirectoryIntegrity $integrity = new DirectoryIntegrity())
    {
    }

    /** @return array<string, string> */
    public function write(string $root, AtomicFileWriter $writer): array
    {
        $files = $this->integrity->hashTree($root);
        if (isset($files[self::MANIFEST_PATH])) {
            throw new \RuntimeException('Public build integrity manifest already exists before finalization.');
        }
        $writer->write($root.'/'.self::MANIFEST_PATH, CanonicalJson::encode([
            'schema_version' => 1,
            'files' => $files,
        ], true));

        return $files;
    }

    /** @return array<string, string> */
    public function verify(string $root): array
    {
        $manifestPath = rtrim($root, '\\/').DIRECTORY_SEPARATOR.self::MANIFEST_PATH;
        $json = !is_link($manifestPath) && is_file($manifestPath) ? file_get_contents($manifestPath) : false;
        if (false === $json) {
            throw new \RuntimeException('Public build integrity manifest is missing or unreadable.');
        }
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Public build integrity manifest is not valid JSON.', previous: $exception);
        }
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new \RuntimeException('Public build integrity manifest must be a JSON object.');
        }
        $keys = array_keys($decoded);
        sort($keys, SORT_STRING);
        if (['files', 'schema_version'] !== $keys
            || 1 !== ($decoded['schema_version'] ?? null)
            || !is_array($decoded['files'] ?? null)
            || array_is_list($decoded['files'])) {
            throw new \RuntimeException('Public build integrity manifest fields are invalid.');
        }

        /** @var array<mixed> $rawFiles */
        $rawFiles = $decoded['files'];
        $files = [];
        foreach ($rawFiles as $relative => $hash) {
            if (!is_string($relative) || !is_string($hash)) {
                throw new \RuntimeException('Public build integrity file map is invalid.');
            }
            if (self::MANIFEST_PATH === $relative) {
                throw new \RuntimeException('Public build integrity manifest cannot list itself.');
            }
            $files[$relative] = $hash;
        }
        if (!isset($files['index.html'])) {
            throw new \RuntimeException('Public build integrity manifest does not include index.html.');
        }
        ksort($files, SORT_STRING);
        $this->integrity->assertExact($root, $files, [self::MANIFEST_PATH], 'Public build');

        return $files;
    }
}
