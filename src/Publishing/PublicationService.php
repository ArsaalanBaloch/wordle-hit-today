<?php

declare(strict_types=1);

namespace WordleHitToday\Publishing;

use WordleHitToday\Clock\GlobalDateEligibility;
use WordleHitToday\Config\SiteConfig;
use WordleHitToday\Definitions\DefinitionStore;
use WordleHitToday\Storage\AtomicFileWriter;
use WordleHitToday\Storage\RecordStore;
use WordleHitToday\Support\CanonicalJson;
use WordleHitToday\Validation\RecordValidator;
use WordleHitToday\Validation\SchemaValidator;

final readonly class PublicationService
{
    public function __construct(
        private string $projectRoot,
        private SiteConfig $config,
        private AtomicFileWriter $writer,
        private RecordStore $records,
        private RecordValidator $recordValidator,
        private SchemaValidator $schemas,
        private DefinitionStore $definitions,
    ) {
    }

    public function publish(\DateTimeImmutable $builtAt): BuildResult
    {
        $assetJson = file_get_contents($this->projectRoot.'/assets/dist/manifest.json');
        if (false === $assetJson) {
            throw new \RuntimeException('Asset manifest is missing. Run npm run build first.');
        }
        /** @var array<string, mixed> $assets */
        $assets = json_decode($assetJson, true, 32, JSON_THROW_ON_ERROR);
        if (($assets['vocabulary_mode'] ?? 'fixture') !== $this->config->mode()) {
            throw new \RuntimeException(sprintf(
                'Asset vocabulary mode %s does not match configuration mode %s.',
                (string) ($assets['vocabulary_mode'] ?? 'fixture'),
                $this->config->mode(),
            ));
        }

        $builder = new SiteBuilder(
            $this->projectRoot,
            $this->config,
            $this->writer,
            $this->recordValidator,
            $this->schemas,
            new GlobalDateEligibility(),
            $this->definitions,
        );
        $result = $builder->build($this->records->all(), $assets, $builtAt);
        $journal = [
            'schema_version' => 1,
            'run_id' => bin2hex(random_bytes(12)),
            'mode' => $this->config->mode(),
            'record_date' => $result->homepageDate,
            'record_revision' => 1,
            'stage' => 'published',
            'files' => array_map(
                static fn (string $path, string $hash): array => ['path' => $path, 'sha256' => $hash],
                array_keys($result->checksums),
                array_values($result->checksums),
            ),
            'promoted' => true,
            'error_class' => null,
        ];
        $journalJson = CanonicalJson::encode($journal, true);
        $this->schemas->validateJson($journalJson, 'journal.schema.json');
        $this->writer->write($this->config->privateStorage().'/journal/last-build.json', $journalJson);

        return $result;
    }
}
