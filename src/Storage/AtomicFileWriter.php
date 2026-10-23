<?php

declare(strict_types=1);

namespace WordleHitToday\Storage;

final class AtomicFileWriter
{
    public function write(string $path, string $contents): void
    {
        $directory = dirname($path);
        $this->assertNoSymbolicLinks($directory);
        if (!is_dir($directory) && !mkdir($directory, 0o750, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create directory: %s', $directory));
        }

        $this->assertNoSymbolicLinks($directory);
        $this->assertNoSymbolicLinks($path);

        $temporaryPath = $path.'.tmp.'.bin2hex(random_bytes(8));
        $bytes = file_put_contents($temporaryPath, $contents, LOCK_EX);
        if (false === $bytes || $bytes !== strlen($contents)) {
            @unlink($temporaryPath);
            throw new \RuntimeException(sprintf('Incomplete write for %s.', $path));
        }

        if (!rename($temporaryPath, $path)) {
            @unlink($temporaryPath);
            throw new \RuntimeException(sprintf('Unable to promote temporary file to %s.', $path));
        }
    }

    private function assertNoSymbolicLinks(string $path): void
    {
        for ($cursor = $path; ; $cursor = dirname($cursor)) {
            if (is_link($cursor)) {
                throw new \RuntimeException('Refusing to write through a symbolic link.');
            }
            $parent = dirname($cursor);
            if ($parent === $cursor) {
                return;
            }
        }
    }
}
