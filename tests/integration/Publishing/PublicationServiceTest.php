<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Integration\Publishing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use WordleHitToday\Config\SiteConfig;
use WordleHitToday\Console\ProjectServices;
use WordleHitToday\Fixtures\FixtureFactory;
use WordleHitToday\Publishing\BuildResult;
use WordleHitToday\Publishing\PublicationService;
use WordleHitToday\Publishing\SiteBuilder;
use WordleHitToday\Support\CanonicalJson;
use WordleHitToday\Validation\SchemaValidator;

#[CoversClass(PublicationService::class)]
final class PublicationServiceTest extends TestCase
{
    private string $projectRoot;
    private string $temporaryRoot;
    private ProjectServices $services;

    protected function setUp(): void
    {
        $this->projectRoot = dirname(__DIR__, 3);
        $relativeRoot = 'var/test-publication-'.bin2hex(random_bytes(4));
        $this->temporaryRoot = $this->projectRoot.'/'.$relativeRoot;
        mkdir($this->temporaryRoot, 0o750, true);

        $json = file_get_contents($this->projectRoot.'/config/site.example.json');
        self::assertIsString($json);
        /** @var array<string, mixed> $values */
        $values = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        $values['public_output'] = $relativeRoot.'/site';
        $values['private_storage'] = $relativeRoot.'/private';
        /** @var array<string, mixed> $monitoring */
        $monitoring = $values['monitoring'];
        $monitoring['status_path'] = $relativeRoot.'/private/status.json';
        $values['monitoring'] = $monitoring;
        /** @var array<string, mixed> $backup */
        $backup = $values['backup'];
        $backup['local_path'] = $relativeRoot.'/backups';
        $values['backup'] = $backup;
        $configPath = $this->temporaryRoot.'/config.json';
        file_put_contents($configPath, CanonicalJson::encode($values, true));

        $config = SiteConfig::load(
            $this->projectRoot,
            $configPath,
            new SchemaValidator($this->projectRoot.'/schemas'),
        );
        $this->services = new ProjectServices($this->projectRoot, $config);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->temporaryRoot);
    }

    public function testJournalUsesThePublishedHomepageRevision(): void
    {
        $record = new FixtureFactory($this->services->hints)->records()[1];
        $record['content_revision'] = 7;
        unset($record['content_hash']);
        $record['content_hash'] = hash('sha256', CanonicalJson::encode($record));
        $this->services->records->save($record);

        $result = $this->services->publisher->publish(new \DateTimeImmutable('2026-10-16T12:00:00Z'));
        $journalJson = file_get_contents($this->services->config->privateStorage().'/journal/last-build.json');
        self::assertIsString($journalJson);
        /** @var array<string, mixed> $journal */
        $journal = json_decode($journalJson, true, 32, JSON_THROW_ON_ERROR);

        self::assertSame(7, $result->homepageRevision);
        self::assertSame(7, $journal['record_revision']);
        self::assertArrayHasKey('.htaccess', $result->checksums);
        self::assertFileExists($this->services->config->publicOutput().'/.htaccess');
        self::assertStringContainsString(
            'ErrorDocument 404 /404.html',
            (string) file_get_contents($this->services->config->publicOutput().'/.htaccess'),
        );
        self::assertStringContainsString(
            'X-Robots-Tag "noindex, nofollow"',
            (string) file_get_contents($this->services->config->publicOutput().'/.htaccess'),
        );
        self::assertStringNotContainsString(
            '__WORDLE_CANONICAL_HOST_RULE__',
            (string) file_get_contents($this->services->config->publicOutput().'/.htaccess'),
        );
        if ('\\' !== DIRECTORY_SEPARATOR) {
            self::assertSame(0o755, fileperms($this->services->config->publicOutput()) & 0o777);
            self::assertSame(0o755, fileperms($this->services->config->publicOutput().'/assets') & 0o777);
            self::assertSame(0o644, fileperms($this->services->config->publicOutput().'/index.html') & 0o777);
        }
    }

    public function testBuildRejectsARecordBeforeItsEarliestGlobalPublicationInstant(): void
    {
        $record = new FixtureFactory($this->services->hints)->records()[2];
        $this->services->records->save($record);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not globally eligible');

        $this->services->publisher->publish(new \DateTimeImmutable('2026-10-16T09:59:59Z'));
    }

    public function testSavedRecordAfterFailedPublicationRemainsRetryable(): void
    {
        $records = new FixtureFactory($this->services->hints)->records();
        $this->services->records->save($records[0]);
        $firstBuild = new \DateTimeImmutable('2026-10-15T12:00:00Z');
        $this->services->publisher->publish($firstBuild);
        self::assertFalse($this->services->publisher->needsPublication());

        // Simulate a tick that durably saved its record and then failed before publication.
        $this->services->records->save($records[1]);
        $retryTime = new \DateTimeImmutable('2026-10-16T12:00:00Z');
        self::assertTrue($this->services->publisher->needsPublication());

        $this->services->publisher->publish($retryTime);
        self::assertFalse($this->services->publisher->needsPublication());
    }

    public function testFailureAfterStagingPreservesTheCurrentPublicBuild(): void
    {
        $record = new FixtureFactory($this->services->hints)->records()[1];
        $this->services->records->save($record);
        $publicRoot = $this->services->config->publicOutput();
        mkdir($publicRoot, 0o750, true);
        file_put_contents($publicRoot.'/index.html', "previous build\n");
        $builder = new SiteBuilder(
            $this->projectRoot,
            $this->services->config,
            $this->services->writer,
            $this->services->recordsValidator,
            $this->services->schemas,
            new \WordleHitToday\Clock\GlobalDateEligibility(),
            $this->services->definitions,
        );

        try {
            $builder->build(
                $this->services->records->all(),
                new \DateTimeImmutable('2026-10-16T12:00:00Z'),
                static function (BuildResult $result): void {
                    self::assertNotEmpty($result->checksums);
                    throw new \RuntimeException('Injected failure after staging.');
                },
            );
            self::fail('The injected staged-build failure was not raised.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Injected failure after staging.', $exception->getMessage());
        }

        self::assertStringEqualsFile($publicRoot.'/index.html', "previous build\n");
        self::assertSame([], glob($this->temporaryRoot.'/.site-stage-*') ?: []);
    }
}
