<?php

declare(strict_types=1);

namespace WordleHitToday\Publishing;

final class PublisherLock
{
    /** @var resource|null */
    private mixed $handle = null;

    public function acquire(string $path): bool
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0o750, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to create the lock directory.');
        }
        $handle = fopen($path, 'c+');
        if (false === $handle) {
            throw new \RuntimeException('Unable to open the publisher lock.');
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }
        $this->handle = $handle;

        return true;
    }

    public function release(): void
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
