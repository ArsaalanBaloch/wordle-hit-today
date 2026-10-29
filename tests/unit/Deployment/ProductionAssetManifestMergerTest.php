<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Unit\Deployment;

use PHPUnit\Framework\TestCase;
use WordleHitToday\Publishing\ProductionAssetManifestMerger;

final class ProductionAssetManifestMergerTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        $this->temporaryRoot = dirname(__DIR__, 3).'/var/test-asset-merge-'.bin2hex(random_bytes(4));
        mkdir($this->temporaryRoot, 0o750, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryRoot.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->temporaryRoot);
    }

    public function testItKeepsReviewedAssetsAndRetainsTheProductionVocabulary(): void
    {
        $source = [
            'schema_version' => 1,
            'css' => '/assets/static/site.111111111111.css',
            'scripts' => ['main' => '/assets/static/main.222222222222.js'],
            'vocabulary' => '/assets/static/solver-fixture.333333333333.json',
            'vocabulary_mode' => 'fixture',
        ];
        $production = [
            'schema_version' => 1,
            'vocabulary' => '/assets/static/solver-production.444444444444.json',
            'vocabulary_mode' => 'production',
        ];
        $sourcePath = $this->write('source.json', $source);
        $productionPath = $this->write('production.json', $production);
        $outputPath = $this->temporaryRoot.'/merged.json';

        ProductionAssetManifestMerger::mergeFiles($sourcePath, $productionPath, $outputPath);

        /** @var array<string, mixed> $merged */
        $merged = json_decode((string) file_get_contents($outputPath), true, 32, JSON_THROW_ON_ERROR);
        self::assertSame($source['css'], $merged['css']);
        self::assertSame($source['scripts'], $merged['scripts']);
        self::assertSame($production['vocabulary'], $merged['vocabulary']);
        self::assertSame('production', $merged['vocabulary_mode']);
    }

    public function testItRejectsANonProductionVocabulary(): void
    {
        $sourcePath = $this->write('source.json', [
            'schema_version' => 1,
            'vocabulary' => '/assets/static/solver-fixture.111111111111.json',
            'vocabulary_mode' => 'fixture',
        ]);
        $productionPath = $this->write('production.json', [
            'schema_version' => 1,
            'vocabulary' => '/assets/static/solver-fixture.222222222222.json',
            'vocabulary_mode' => 'fixture',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('production vocabulary manifest is invalid');

        ProductionAssetManifestMerger::mergeFiles(
            $sourcePath,
            $productionPath,
            $this->temporaryRoot.'/merged.json',
        );
    }

    /** @param array<string, mixed> $data */
    private function write(string $name, array $data): string
    {
        $path = $this->temporaryRoot.'/'.$name;
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $path;
    }
}
