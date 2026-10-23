<?php

declare(strict_types=1);

namespace WordleHitToday\Support;

use Symfony\Component\Filesystem\Path;

final class PathPolicy
{
    public static function normalizeAbsolute(string $path, string $label = 'Path'): string
    {
        self::assertLocalPath($path, $label);
        if (!self::isAbsoluteFilesystemPath($path)) {
            throw new \InvalidArgumentException(sprintf('%s must be an absolute local filesystem path.', $label));
        }

        return self::resolveExistingPrefix(self::toNativeSeparators(Path::canonicalize($path)), $label);
    }

    public static function resolve(string $path, string $basePath, string $label = 'Path'): string
    {
        self::assertLocalPath($path, $label);
        $basePath = self::normalizeAbsolute($basePath, 'Base path');

        if (Path::isAbsolute($path) && !self::isAbsoluteFilesystemPath($path)) {
            throw new \InvalidArgumentException(sprintf('%s must be a fully qualified local filesystem path.', $label));
        }

        $resolved = Path::makeAbsolute($path, $basePath);

        return self::normalizeAbsolute($resolved, $label);
    }

    public static function isAbsoluteFilesystemPath(string $path): bool
    {
        if ('' === $path || str_contains($path, "\0") || !Path::isLocal($path)) {
            return false;
        }

        if ('\\' === DIRECTORY_SEPARATOR) {
            return 1 === preg_match('/^[A-Za-z]:[\\\\\/]/D', $path);
        }

        return Path::isAbsolute($path);
    }

    public static function assertManagedRoot(string $path, string $projectRoot, string $label): void
    {
        $path = self::normalizeAbsolute($path, $label);
        $projectRoot = self::normalizeAbsolute($projectRoot, 'Project root');

        if (self::isFilesystemRoot($path)) {
            throw new \InvalidArgumentException(sprintf('%s must not be a filesystem root.', $label));
        }
        if (self::isBasePath($path, $projectRoot)) {
            throw new \InvalidArgumentException(sprintf('%s must not be the project root or one of its ancestors.', $label));
        }
    }

    public static function assertDisjoint(string $first, string $second, string $firstLabel, string $secondLabel): void
    {
        $first = self::normalizeAbsolute($first, $firstLabel);
        $second = self::normalizeAbsolute($second, $secondLabel);

        if (self::isBasePath($first, $second) || self::isBasePath($second, $first)) {
            throw new \InvalidArgumentException(sprintf('%s and %s must not overlap.', $firstLabel, $secondLabel));
        }
    }

    public static function assertStrictlyInside(string $path, string $root, string $pathLabel, string $rootLabel): void
    {
        if (!self::isStrictlyInside($path, $root)) {
            throw new \InvalidArgumentException(sprintf('%s must be strictly inside %s.', $pathLabel, $rootLabel));
        }
    }

    public static function isStrictlyInside(string $path, string $root): bool
    {
        $path = self::normalizeAbsolute($path);
        $root = self::normalizeAbsolute($root);

        return !self::isSamePath($path, $root) && self::isBasePath($root, $path);
    }

    private static function assertLocalPath(string $path, string $label): void
    {
        if ('' === $path) {
            throw new \InvalidArgumentException(sprintf('%s must not be empty.', $label));
        }
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException(sprintf('%s must not contain a null byte.', $label));
        }
        if (!Path::isLocal($path)) {
            throw new \InvalidArgumentException(sprintf('%s must be a local filesystem path.', $label));
        }
    }

    private static function isFilesystemRoot(string $path): bool
    {
        $canonical = self::comparable($path);
        $root = Path::getRoot($canonical);

        return '' !== $root && $canonical === self::comparable($root);
    }

    private static function isSamePath(string $first, string $second): bool
    {
        return self::comparable($first) === self::comparable($second);
    }

    private static function isBasePath(string $basePath, string $path): bool
    {
        return Path::isBasePath(self::comparable($basePath), self::comparable($path));
    }

    private static function comparable(string $path): string
    {
        $canonical = Path::canonicalize($path);

        return '\\' === DIRECTORY_SEPARATOR ? strtolower($canonical) : $canonical;
    }

    private static function resolveExistingPrefix(string $path, string $label): string
    {
        $cursor = $path;
        $suffix = [];
        while (!file_exists($cursor) && !is_link($cursor)) {
            $parent = dirname($cursor);
            if ($parent === $cursor) {
                return $path;
            }
            array_unshift($suffix, basename($cursor));
            $cursor = $parent;
        }

        $resolved = realpath($cursor);
        if (false === $resolved) {
            throw new \InvalidArgumentException(sprintf('%s contains an unresolved symbolic link.', $label));
        }
        foreach ($suffix as $component) {
            $resolved .= DIRECTORY_SEPARATOR.$component;
        }

        return self::toNativeSeparators(Path::canonicalize($resolved));
    }

    private static function toNativeSeparators(string $path): string
    {
        return '\\' === DIRECTORY_SEPARATOR ? str_replace('/', '\\', $path) : $path;
    }

    private function __construct()
    {
    }
}
