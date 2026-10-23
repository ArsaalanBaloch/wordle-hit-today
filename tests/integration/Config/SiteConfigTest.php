<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Integration\Config;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Path;
use WordleHitToday\Config\SiteConfig;
use WordleHitToday\Validation\SchemaValidator;
use WordleHitToday\Validation\ValidationException;

#[CoversClass(SiteConfig::class)]
final class SiteConfigTest extends TestCase
{
    private string $projectRoot;
    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 3);
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
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
        $values = $this->exampleConfiguration();
        $values['mode'] = 'production';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('placeholder canonical origin');

        $this->loadConfiguration($values);
    }

    public function testDotSegmentEquivalentPublicAndPrivatePathsAreRejected(): void
    {
        $values = $this->exampleConfiguration();
        $values['private_storage'] = 'var/temporary/../site';
        $values['monitoring'] = $this->withStatusPath($values, 'var/site/status.json');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Public output and Private storage must not overlap.');

        $this->loadConfiguration($values);
    }

    public function testNestedPublicAndPrivatePathsAreRejected(): void
    {
        $values = $this->exampleConfiguration();
        $values['private_storage'] = 'var/site/private';
        $values['monitoring'] = $this->withStatusPath($values, 'var/site/private/status.json');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Public output and Private storage must not overlap.');

        $this->loadConfiguration($values);
    }

    public function testBackupPathNestedInsidePrivateStorageIsRejected(): void
    {
        $values = $this->exampleConfiguration();
        /** @var array<string, mixed> $backup */
        $backup = $values['backup'];
        $backup['local_path'] = 'var/private/backups';
        $values['backup'] = $backup;

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Private storage and Backup path must not overlap.');

        $this->loadConfiguration($values);
    }

    public function testPrivateStorageCannotAliasThePublicRollbackPath(): void
    {
        $values = $this->exampleConfiguration();
        $values['private_storage'] = 'var/site.rollback';
        $values['monitoring'] = $this->withStatusPath($values, 'var/site.rollback/status.json');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Public rollback and Private storage must not overlap.');

        $this->loadConfiguration($values);
    }

    public function testBackupStorageCannotAliasThePublicRollbackPath(): void
    {
        $values = $this->exampleConfiguration();
        /** @var array<string, mixed> $backup */
        $backup = $values['backup'];
        $backup['local_path'] = 'var/site.rollback';
        $values['backup'] = $backup;

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Public rollback and Backup path must not overlap.');

        $this->loadConfiguration($values);
    }

    public function testStatusPathOutsidePrivateStorageIsRejected(): void
    {
        $values = $this->exampleConfiguration();
        $values['monitoring'] = $this->withStatusPath($values, 'var/status.json');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Status path must be strictly inside Private storage.');

        $this->loadConfiguration($values);
    }

    public function testManagedPathCannotBeFilesystemRoot(): void
    {
        $values = $this->exampleConfiguration();
        $values['public_output'] = Path::getRoot(Path::canonicalize($this->projectRoot));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Public output must not be a filesystem root.');

        $this->loadConfiguration($values);
    }

    public function testManagedPathCannotResolveToProjectRoot(): void
    {
        $values = $this->exampleConfiguration();
        $values['public_output'] = 'var/..';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Public output must not be the project root or one of its ancestors.');

        $this->loadConfiguration($values);
    }

    /** @return array<string, mixed> */
    private function exampleConfiguration(): array
    {
        $json = file_get_contents($this->projectRoot.'/config/site.example.json');
        if (false === $json) {
            self::fail('Example configuration is not readable.');
        }

        /** @var array<string, mixed> $values */
        $values = json_decode($json, true, 64, JSON_THROW_ON_ERROR);

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function withStatusPath(array $values, string $path): array
    {
        /** @var array<string, mixed> $monitoring */
        $monitoring = $values['monitoring'];
        $monitoring['status_path'] = $path;

        return $monitoring;
    }

    /** @param array<string, mixed> $values */
    private function loadConfiguration(array $values): SiteConfig
    {
        $directory = $this->projectRoot.'/var';
        if (!is_dir($directory) && !mkdir($directory, 0o750, true) && !is_dir($directory)) {
            self::fail('Unable to create temporary configuration directory.');
        }

        $path = $directory.'/test-site-config-'.bin2hex(random_bytes(8)).'.json';
        $this->temporaryFiles[] = $path;
        if (false === file_put_contents($path, json_encode($values, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT))) {
            self::fail('Unable to write temporary configuration.');
        }

        return SiteConfig::load(
            $this->projectRoot,
            $path,
            new SchemaValidator($this->projectRoot.'/schemas'),
        );
    }
}
