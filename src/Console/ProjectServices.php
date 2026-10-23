<?php

declare(strict_types=1);

namespace WordleHitToday\Console;

use Symfony\Component\HttpClient\HttpClient;
use WordleHitToday\Clock\Clock;
use WordleHitToday\Clock\SystemClock;
use WordleHitToday\Config\SiteConfig;
use WordleHitToday\Definitions\DefinitionStore;
use WordleHitToday\Hints\HintCalculator;
use WordleHitToday\Monitoring\OperationalLogger;
use WordleHitToday\Monitoring\StatusStore;
use WordleHitToday\Publishing\PublicationService;
use WordleHitToday\Source\NytSourceClient;
use WordleHitToday\Source\PuzzleNumberResolver;
use WordleHitToday\Source\RecordFactory;
use WordleHitToday\Source\SourcePayloadValidator;
use WordleHitToday\Storage\AtomicFileWriter;
use WordleHitToday\Storage\RecordStore;
use WordleHitToday\Validation\RecordValidator;
use WordleHitToday\Validation\SchemaValidator;

final readonly class ProjectServices
{
    public SchemaValidator $schemas;
    public HintCalculator $hints;
    public RecordValidator $recordsValidator;
    public AtomicFileWriter $writer;
    public RecordStore $records;
    public PublicationService $publisher;
    public DefinitionStore $definitions;
    public StatusStore $status;
    public OperationalLogger $logger;
    public Clock $clock;

    public function __construct(
        public string $projectRoot,
        public SiteConfig $config,
        ?Clock $clock = null,
    ) {
        $this->clock = $clock ?? new SystemClock();
        $this->schemas = new SchemaValidator($projectRoot.'/schemas');
        $this->hints = new HintCalculator();
        $this->recordsValidator = new RecordValidator($this->schemas, $this->hints);
        $this->writer = new AtomicFileWriter();
        $this->records = new RecordStore($config->privateStorage(), $this->writer, $this->recordsValidator);
        $this->definitions = new DefinitionStore($config->privateStorage(), $this->writer);
        $this->status = new StatusStore(
            $config->statusPath(),
            $this->writer,
            $this->schemas,
            $config->sourcePollMinutes(),
        );
        $this->logger = new OperationalLogger($config->privateStorage(), $this->writer);
        $this->publisher = new PublicationService(
            $projectRoot,
            $config,
            $this->writer,
            $this->records,
            $this->recordsValidator,
            $this->schemas,
            $this->definitions,
        );
    }

    public static function load(string $projectRoot, string $configPath, ?string $expectedMode): self
    {
        $schemas = new SchemaValidator($projectRoot.'/schemas');
        $config = SiteConfig::load($projectRoot, $configPath, $schemas);
        if (null !== $expectedMode && $expectedMode !== $config->mode()) {
            throw new \InvalidArgumentException(sprintf(
                'Requested mode %s does not match configuration mode %s. CLI flags cannot silently change mode.',
                $expectedMode,
                $config->mode(),
            ));
        }

        return new self($projectRoot, $config);
    }

    public function sourceClient(): NytSourceClient
    {
        return new NytSourceClient(HttpClient::create(), new SourcePayloadValidator(), $this->config->source());
    }

    public function productionRecordFactory(): RecordFactory
    {
        $anchor = $this->config->puzzleNumberAnchor();
        if (null === $anchor) {
            throw new \InvalidArgumentException('A puzzle-number anchor is required for production ingestion.');
        }

        return new RecordFactory(new PuzzleNumberResolver($anchor['date'], $anchor['number']), $this->hints);
    }
}
