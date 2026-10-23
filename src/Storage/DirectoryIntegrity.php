<?php

declare(strict_types=1);

namespace WordleHitToday\Storage;

final class DirectoryIntegrity
{
    /**
     * @param list<string> $excludedPaths
     *
     * @return array<string, string>
     */
    public function hashTree(string $root, array $excludedPaths = []): array
    {
        if (is_link($root)) {
            throw new \RuntimeException('Directory integrity root must not be a symbolic link.');
        }

        $resolvedRoot = realpath($root);
        if (false === $resolvedRoot || !is_dir($resolvedRoot)) {
            throw new \RuntimeException('Directory integrity root is missing or is not a directory.');
        }

        $excluded = [];
        foreach ($excludedPaths as $relative) {
            $this->assertSafeRelativePath($relative);
            $excluded[$relative] = true;
        }

        $hashes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($resolvedRoot, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        foreach ($iterator as $entry) {
            $relative = str_replace('\\', '/', substr($entry->getPathname(), strlen($resolvedRoot) + 1));
            if ($entry->isLink()) {
                throw new \RuntimeException('Directory integrity check rejected symbolic link: '.$relative);
            }
            if ($entry->isDir()) {
                continue;
            }
            if (!$entry->isFile()) {
                throw new \RuntimeException('Directory integrity check rejected non-regular entry: '.$relative);
            }
            if (isset($excluded[$relative])) {
                continue;
            }

            $hash = hash_file('sha256', $entry->getPathname());
            if (!is_string($hash)) {
                throw new \RuntimeException('Unable to hash directory entry: '.$relative);
            }
            $hashes[$relative] = $hash;
        }
        ksort($hashes, SORT_STRING);

        return $hashes;
    }

    /**
     * @param array<string, string> $expected
     * @param list<string>          $excludedPaths
     *
     * @return array{missing: list<string>, changed: list<string>, extra: list<string>}
     */
    public function differences(string $root, array $expected, array $excludedPaths = []): array
    {
        foreach ($expected as $relative => $hash) {
            $this->assertSafeRelativePath($relative);
            if (1 !== preg_match('/^[a-f0-9]{64}$/D', $hash)) {
                throw new \RuntimeException('Directory integrity manifest contains an invalid SHA-256 hash.');
            }
        }

        $actual = file_exists($root) || is_link($root)
            ? $this->hashTree($root, $excludedPaths)
            : [];
        $missing = array_values(array_diff(array_keys($expected), array_keys($actual)));
        $extra = array_values(array_diff(array_keys($actual), array_keys($expected)));
        $changed = [];
        foreach (array_intersect(array_keys($expected), array_keys($actual)) as $relative) {
            if (!hash_equals($expected[$relative], $actual[$relative])) {
                $changed[] = $relative;
            }
        }
        sort($missing, SORT_STRING);
        sort($changed, SORT_STRING);
        sort($extra, SORT_STRING);

        return ['missing' => $missing, 'changed' => $changed, 'extra' => $extra];
    }

    /**
     * @param array<string, string> $expected
     * @param list<string>          $excludedPaths
     */
    public function assertExact(
        string $root,
        array $expected,
        array $excludedPaths = [],
        string $label = 'Directory',
    ): void {
        $differences = $this->differences($root, $expected, $excludedPaths);
        $details = [];
        foreach (['missing', 'changed', 'extra'] as $category) {
            if ([] !== $differences[$category]) {
                $details[] = sprintf('%s: %s', $category, implode(', ', $differences[$category]));
            }
        }
        if ([] !== $details) {
            throw new \RuntimeException(sprintf('%s integrity mismatch (%s).', $label, implode('; ', $details)));
        }
    }

    private function assertSafeRelativePath(string $relative): void
    {
        if ('' === $relative
            || str_starts_with($relative, '/')
            || str_contains($relative, '\\')
            || array_intersect(explode('/', $relative), ['', '.', '..'])) {
            throw new \RuntimeException('Directory integrity manifest contains an unsafe relative path.');
        }
    }
}
