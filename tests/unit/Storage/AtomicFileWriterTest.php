<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Unit\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use WordleHitToday\Storage\AtomicFileWriter;

#[CoversClass(AtomicFileWriter::class)]
final class AtomicFileWriterTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        $this->temporaryRoot = dirname(__DIR__, 3).'/var/test-writer-'.bin2hex(random_bytes(4));
        mkdir($this->temporaryRoot.'/real', 0o750, true);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->temporaryRoot);
    }

    public function testWritesAndAtomicallyReplacesARegularFile(): void
    {
        $path = $this->temporaryRoot.'/regular/file.txt';
        $writer = new AtomicFileWriter();

        $writer->write($path, 'first');
        $writer->write($path, 'second');

        self::assertSame('second', file_get_contents($path));
    }

    public function testRejectsASymbolicLinkInAnyExistingAncestor(): void
    {
        $link = $this->temporaryRoot.'/linked';
        if (!@symlink($this->temporaryRoot.'/real', $link)) {
            self::markTestSkipped('Symbolic-link creation is unavailable in this environment.');
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('symbolic link');

        new AtomicFileWriter()->write($link.'/nested/file.txt', 'unsafe');
    }
}
