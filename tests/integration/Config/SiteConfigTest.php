<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Integration\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use WordleHitToday\Config\SiteConfig;
use WordleHitToday\Validation\SchemaValidator;
use WordleHitToday\Validation\ValidationException;

#[CoversClass(SiteConfig::class)]
final class SiteConfigTest extends TestCase
{
    private string $projectRoot;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 3);
    }

    public function testFixtureConfigurationLoads(): void
    {
        $config = SiteConfig::load(
            $this->projectRoot,
            $this->projectRoot.'/config/site.example.json',
            new SchemaValidator($this->projectRoot.'/schemas'),
        );

        self::assertSame('fixture', $config->mode());
        self::assertStringEndsWith('var'.DIRECTORY_SEPARATOR.'site', $config->publicOutput());
    }

    public function testProductionRejectsPlaceholderOrigin(): void
    {
        $source = file_get_contents($this->projectRoot.'/config/site.example.json');
        self::assertIsString($source);
        $production = str_replace('"mode": "fixture"', '"mode": "production"', $source);
        $path = $this->projectRoot.'/var/test-production-config.json';
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0o750, true);
        }
        file_put_contents($path, $production);

        try {
            $this->expectException(ValidationException::class);
            $this->expectExceptionMessage('placeholder canonical origin');
            SiteConfig::load($this->projectRoot, $path, new SchemaValidator($this->projectRoot.'/schemas'));
        } finally {
            @unlink($path);
        }
    }
}
