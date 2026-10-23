<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Unit\Publishing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use WordleHitToday\Publishing\AssetManifest;

#[CoversClass(AssetManifest::class)]
final class AssetManifestTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $this->temporaryRoot = $projectRoot.'/var/test-assets-'.bin2hex(random_bytes(4));
        new Filesystem()->mirror($projectRoot.'/assets/dist', $this->temporaryRoot);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->temporaryRoot);
    }

    public function testLoadsACompleteHashVerifiedSnapshot(): void
    {
        $manifest = AssetManifest::loadSnapshot($this->temporaryRoot, 'fixture');

        self::assertSame(1, $manifest['schema_version']);
        self::assertSame('fixture', $manifest['vocabulary_mode']);
    }

    public function testRejectsAReferencedFileWhoseContentsDoNotMatchItsName(): void
    {
        $manifest = $this->manifest();
        $css = $this->temporaryRoot.'/'.substr((string) $manifest['css'], strlen('/assets/static/'));
        file_put_contents($css, "\ncorrupt", FILE_APPEND);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('content hash does not match its filename');

        AssetManifest::loadSnapshot($this->temporaryRoot, 'fixture');
    }

    public function testRejectsUnsafeManifestPaths(): void
    {
        $manifest = $this->manifest();
        $manifest['css'] = '/assets/static/../outside.css';
        file_put_contents(
            $this->temporaryRoot.'/manifest.json',
            json_encode($manifest, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n",
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('path is unsafe');

        AssetManifest::loadSnapshot($this->temporaryRoot, 'fixture');
    }

    public function testRejectsAMissingReferencedFile(): void
    {
        $manifest = $this->manifest();
        $css = $this->temporaryRoot.'/'.substr((string) $manifest['css'], strlen('/assets/static/'));
        unlink($css);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('file is missing from the staged snapshot');

        AssetManifest::loadSnapshot($this->temporaryRoot, 'fixture');
    }

    public function testRejectsTheWrongVocabularyMode(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('does not match configuration mode');

        AssetManifest::loadSnapshot($this->temporaryRoot, 'production');
    }

    public function testRejectsUnreferencedFiles(): void
    {
        file_put_contents($this->temporaryRoot.'/stale.js', "stale\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('unreferenced files');

        AssetManifest::loadSnapshot($this->temporaryRoot, 'fixture');
    }

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        $json = file_get_contents($this->temporaryRoot.'/manifest.json');
        self::assertIsString($json);
        /** @var array<string, mixed> $manifest */
        $manifest = json_decode($json, true, 32, JSON_THROW_ON_ERROR);

        return $manifest;
    }
}
