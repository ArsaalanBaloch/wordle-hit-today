<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Unit\Publishing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use WordleHitToday\Publishing\PublicBuildIntegrity;
use WordleHitToday\Storage\AtomicFileWriter;

#[CoversClass(PublicBuildIntegrity::class)]
final class PublicBuildIntegrityTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        $this->temporaryRoot = dirname(__DIR__, 3).'/var/test-public-integrity-'.bin2hex(random_bytes(4));
        mkdir($this->temporaryRoot.'/assets', 0o750, true);
        file_put_contents($this->temporaryRoot.'/index.html', "<!doctype html>\n");
        file_put_contents($this->temporaryRoot.'/assets/site.css', "body{}\n");
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->temporaryRoot);
    }

    public function testWritesAndVerifiesAnExactPublicTree(): void
    {
        $integrity = new PublicBuildIntegrity();
        $written = $integrity->write($this->temporaryRoot, new AtomicFileWriter());

        self::assertFileExists($this->temporaryRoot.'/'.PublicBuildIntegrity::MANIFEST_PATH);
        self::assertSame($written, $integrity->verify($this->temporaryRoot));
    }

    public function testRejectsChangedAndInjectedFiles(): void
    {
        $integrity = new PublicBuildIntegrity();
        $integrity->write($this->temporaryRoot, new AtomicFileWriter());
        file_put_contents($this->temporaryRoot.'/assets/site.css', "tampered{}\n");
        file_put_contents($this->temporaryRoot.'/injected.html', "injected\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Public build integrity mismatch');

        $integrity->verify($this->temporaryRoot);
    }
}
