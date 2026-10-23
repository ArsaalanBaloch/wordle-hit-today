<?php

declare(strict_types=1);

namespace WordleHitToday\Publishing;

use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use WordleHitToday\Clock\GlobalDateEligibility;
use WordleHitToday\Config\SiteConfig;
use WordleHitToday\Definitions\DefinitionStore;
use WordleHitToday\Storage\AtomicFileWriter;
use WordleHitToday\Storage\DirectoryIntegrity;
use WordleHitToday\Support\CanonicalJson;
use WordleHitToday\Support\PathPolicy;
use WordleHitToday\Validation\RecordValidator;
use WordleHitToday\Validation\SchemaValidator;

final readonly class SiteBuilder
{
    private Environment $twig;
    private Filesystem $filesystem;

    public function __construct(
        private string $projectRoot,
        private SiteConfig $config,
        private AtomicFileWriter $writer,
        private RecordValidator $recordValidator,
        private SchemaValidator $schemaValidator,
        private GlobalDateEligibility $dateEligibility,
        private DefinitionStore $definitions,
    ) {
        $this->twig = new Environment(new FilesystemLoader($projectRoot.'/templates'), [
            'autoescape' => 'html',
            'strict_variables' => true,
            'cache' => false,
        ]);
        $this->filesystem = new Filesystem();
    }

    /**
     * @param list<array<string, mixed>> $records
     * @param callable(BuildResult): void $onStaged
     */
    public function build(array $records, \DateTimeImmutable $builtAt, callable $onStaged): BuildResult
    {
        if ([] === $records) {
            throw new \RuntimeException('At least one validated record is required to build the site.');
        }

        $builtAt = $builtAt->setTimezone(new \DateTimeZone('UTC'));
        foreach ($records as $record) {
            $this->recordValidator->validate($record);
            if ($record['mode'] !== $this->config->mode()) {
                throw new \RuntimeException('Record mode does not match configuration mode.');
            }
            if ($this->dateEligibility->earliestPublicationInstant((string) $record['puzzle_date']) > $builtAt) {
                throw new \RuntimeException(sprintf(
                    'Record %s is not globally eligible for publication yet.',
                    $record['puzzle_date'],
                ));
            }
        }

        usort($records, static fn (array $left, array $right): int => $left['puzzle_date'] <=> $right['puzzle_date']);
        $records = array_map(fn (array $record): array => $this->withFullDate($record), $records);
        $homepage = $this->selectHomepageRecord($records, $builtAt);
        $output = $this->config->publicOutput();
        $this->assertSafeOutputPath($output);
        $stage = dirname($output).DIRECTORY_SEPARATOR.'.site-stage-'.bin2hex(random_bytes(6));
        $this->filesystem->mkdir($stage, 0o750);

        try {
            $this->filesystem->mirror($this->projectRoot.'/assets/dist', $stage.'/assets/static');
            $assets = AssetManifest::loadSnapshot($stage.'/assets/static', $this->config->mode());
            $archiveIndexes = $this->writeArchiveIndexes($stage, $records);
            $manifest = $this->writePublishedManifest($stage, $records, $homepage, $archiveIndexes, $builtAt);
            $pageCount = $this->writePages($stage, $records, $homepage, $assets, $builtAt);
            $this->writeSitemapAndRobots($stage, $records, $builtAt);
            new PublicBuildIntegrity()->write($stage, $this->writer);
            $checksums = new DirectoryIntegrity()->hashTree($stage);
            $this->schemaValidator->validateJson(
                CanonicalJson::encode($manifest),
                'published-dates.schema.json',
            );
            $result = new BuildResult(
                $output,
                $pageCount,
                $checksums,
                (string) $homepage['puzzle_date'],
                (int) $homepage['content_revision'],
            );
            $onStaged($result);
            $this->promote($stage, $output);
        } catch (\Throwable $exception) {
            if (is_dir($stage)) {
                $this->filesystem->remove($stage);
            }
            throw $exception;
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @param array<string, mixed>       $homepage
     * @param array<string, mixed>       $assets
     */
    private function writePages(
        string $stage,
        array $records,
        array $homepage,
        array $assets,
        \DateTimeImmutable $builtAt,
    ): int {
        $pageCount = 0;
        $descending = array_reverse($records);
        $base = $this->baseContext($assets, $builtAt);
        $homeCanonical = $this->canonical('/');
        $this->render($stage, 'index.html', 'home.html.twig', $base + [
            'title' => $this->pageTitle(sprintf('Wordle Hint Today and Answer for %s', $homepage['full_date'])),
            'description' => sprintf('Get spoiler-controlled Wordle hints, clues and the answer for %s, plus links to the solver and past answers.', $homepage['full_date']),
            'canonical' => $homeCanonical,
            'current_route' => 'home',
            'record' => $homepage,
            'full_date' => $homepage['full_date'],
            'minimum_date' => $records[0]['puzzle_date'],
            'maximum_date' => $records[count($records) - 1]['puzzle_date'],
            'recent_records' => array_slice($descending, 0, 3),
            'definition' => $this->definitions->glossFor(is_string($homepage['definition_reference']) ? $homepage['definition_reference'] : null),
            'structured_data' => $this->structuredData([
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $this->config->siteName(),
                'url' => $homeCanonical,
            ]),
        ]);
        ++$pageCount;

        foreach ($records as $index => $record) {
            $route = '/wordle-answers/'.$record['puzzle_date'].'/';
            $canonical = $this->canonical($route);
            $this->render($stage, trim($route, '/').'/index.html', 'dated.html.twig', $base + [
                'title' => $this->pageTitle(sprintf('Wordle #%d Answer and Hints for %s', $record['puzzle_number'], $record['full_date'])),
                'description' => sprintf('Progressive hints and a controlled answer reveal for Wordle #%d on %s.', $record['puzzle_number'], $record['full_date']),
                'canonical' => $canonical,
                'current_route' => 'archive',
                'record' => $record,
                'full_date' => $record['full_date'],
                'previous_record' => $records[$index - 1] ?? null,
                'next_record' => $records[$index + 1] ?? null,
                'definition' => $this->definitions->glossFor(is_string($record['definition_reference']) ? $record['definition_reference'] : null),
                'structured_data' => $this->structuredData([
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Past Answers', 'item' => $this->canonical('/wordle-answers/')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Wordle #'.$record['puzzle_number'], 'item' => $canonical],
                    ],
                ]),
            ]);
            ++$pageCount;
        }

        $archivePageSize = $this->config->archivePageSize();
        if ($archivePageSize < 1) {
            throw new \LogicException('Archive page size must be positive.');
        }
        $chunks = array_chunk($descending, $archivePageSize);
        foreach ($chunks as $pageIndex => $pageRecords) {
            $page = $pageIndex + 1;
            $route = 1 === $page ? '/wordle-answers/' : sprintf('/wordle-answers/page/%d/', $page);
            $path = 1 === $page ? 'wordle-answers/index.html' : sprintf('wordle-answers/page/%d/index.html', $page);
            $this->render($stage, $path, 'archive.html.twig', $base + [
                'title' => $this->pageTitle(1 === $page ? 'Wordle Archive: Past Answers by Date and Number' : sprintf('Wordle Archive — Page %d', $page)),
                'description' => 'Search the Wordle answer archive by date, puzzle number or exact word, and browse previous validated answers and hints.',
                'canonical' => $this->canonical($route),
                'current_route' => 'archive',
                'page_scripts' => [$assets['scripts']['archive']],
                'page_records' => $pageRecords,
                'years' => array_values(array_unique(array_map(static fn (array $record): string => substr((string) $record['puzzle_date'], 0, 4), $records))),
                'current_page' => $page,
                'total_pages' => count($chunks),
            ]);
            ++$pageCount;
        }

        $byYear = [];
        foreach ($descending as $record) {
            $year = substr((string) $record['puzzle_date'], 0, 4);
            $month = substr((string) $record['puzzle_date'], 5, 2);
            $byYear[$year][$month][] = $record;
        }
        foreach ($byYear as $year => $months) {
            $yearRecords = array_merge(...array_values($months));
            $monthLinks = [];
            foreach (array_keys($months) as $month) {
                $monthDate = \DateTimeImmutable::createFromFormat('!Y-m', $year.'-'.$month, new \DateTimeZone('UTC'));
                $monthLinks[] = ['number' => $month, 'name' => false === $monthDate ? $month : $monthDate->format('F')];
            }
            $this->render($stage, sprintf('wordle-answers/%s/index.html', $year), 'archive-period.html.twig', $base + [
                'title' => $this->pageTitle(sprintf('%s Wordle Answers and Hints', $year)),
                'description' => sprintf('Browse published Wordle answer and hint pages from %s.', $year),
                'canonical' => $this->canonical(sprintf('/wordle-answers/%s/', $year)),
                'current_route' => 'archive',
                'heading' => sprintf('Wordle answers from %s', $year),
                'year' => $year,
                'months' => $monthLinks,
                'page_records' => $yearRecords,
            ]);
            ++$pageCount;
            foreach ($months as $month => $monthRecords) {
                $monthDate = \DateTimeImmutable::createFromFormat('!Y-m', $year.'-'.$month, new \DateTimeZone('UTC'));
                $monthName = false === $monthDate ? $year.'-'.$month : $monthDate->format('F Y');
                $this->render($stage, sprintf('wordle-answers/%s/%s/index.html', $year, $month), 'archive-period.html.twig', $base + [
                    'title' => $this->pageTitle(sprintf('Wordle Answers for %s', $monthName)),
                    'description' => sprintf('Browse published Wordle answer and hint pages from %s.', $monthName),
                    'canonical' => $this->canonical(sprintf('/wordle-answers/%s/%s/', $year, $month)),
                    'current_route' => 'archive',
                    'heading' => sprintf('Wordle answers for %s', $monthName),
                    'page_records' => $monthRecords,
                ]);
                ++$pageCount;
            }
        }

        $this->render($stage, 'wordle-solver/index.html', 'solver.html.twig', $base + [
            'title' => $this->pageTitle('Wordle Solver and 5-Letter Word Finder'),
            'description' => 'Use this private Wordle solver and five-letter word finder to filter candidates from green, yellow and grey feedback, including repeats.',
            'canonical' => $this->canonical('/wordle-solver/'),
            'current_route' => 'solver',
            'page_scripts' => [$assets['scripts']['solver']],
        ]);
        ++$pageCount;

        $contentJson = file_get_contents($this->projectRoot.'/resources/content/pages.json');
        if (false === $contentJson) {
            throw new \RuntimeException('Editorial content is not readable.');
        }
        /** @var array<string, array<string, mixed>> $pages */
        $pages = json_decode($contentJson, true, 64, JSON_THROW_ON_ERROR);
        $guideRoutes = [
            'how-to-play-wordle' => ['route' => '/how-to-play-wordle/', 'current' => 'how-to-play'],
            'wordle-strategy' => ['route' => '/wordle-strategy/', 'current' => 'strategy'],
            'about' => ['route' => '/about/', 'current' => 'about'],
            'privacy' => ['route' => '/privacy/', 'current' => 'privacy'],
        ];
        foreach ($guideRoutes as $key => $routing) {
            $page = $pages[$key];
            $this->render($stage, trim($routing['route'], '/').'/index.html', 'guide.html.twig', $base + [
                'title' => $this->pageTitle((string) $page['title']),
                'description' => $page['description'],
                'canonical' => $this->canonical($routing['route']),
                'current_route' => $routing['current'],
                'page' => $page,
            ]);
            ++$pageCount;
        }

        $this->render($stage, '404.html', '404.html.twig', $base + [
            'title' => $this->pageTitle('Page Not Found'),
            'description' => 'The requested Wordle help page is not available.',
            'canonical' => $this->canonical('/404.html'),
            'current_route' => 'none',
            'robots' => 'noindex, follow',
        ]);
        ++$pageCount;

        return $pageCount;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return array<string, string>
     */
    private function writeArchiveIndexes(string $stage, array $records): array
    {
        $years = [];
        foreach ($records as $record) {
            $year = substr((string) $record['puzzle_date'], 0, 4);
            $years[$year][] = [
                'date' => $record['puzzle_date'],
                'number' => $record['puzzle_number'],
                'word' => $record['solution'],
                'url' => '/wordle-answers/'.$record['puzzle_date'].'/',
            ];
        }

        $indexes = [];
        foreach ($years as $year => $entries) {
            $json = CanonicalJson::encode($entries, true);
            $filename = sprintf('archive-%s.%s.json', $year, substr(hash('sha256', $json), 0, 12));
            $this->writer->write($stage.'/assets/data/'.$filename, $json);
            $indexes[$year] = '/assets/data/'.$filename;
        }

        return $indexes;
    }

    /**
     * @param list<array<string, mixed>> $records
     * @param array<string, mixed>       $homepage
     * @param array<string, string>      $archiveIndexes
     * @return array<string, mixed>
     */
    private function writePublishedManifest(
        string $stage,
        array $records,
        array $homepage,
        array $archiveIndexes,
        \DateTimeImmutable $builtAt,
    ): array {
        $eligible = $this->dateEligibility->datesAt($builtAt);
        $available = [];
        foreach ($records as $record) {
            if (in_array($record['puzzle_date'], $eligible, true)) {
                $available[] = [
                    'date' => $record['puzzle_date'],
                    'number' => $record['puzzle_number'],
                    'url' => '/wordle-answers/'.$record['puzzle_date'].'/',
                ];
            }
        }
        $manifest = [
            'schema_version' => 1,
            'built_at_utc' => $builtAt->format('Y-m-d\TH:i:s\Z'),
            'homepage_default_date' => $homepage['puzzle_date'],
            'available_dates' => array_slice($available, -3),
            'archive_indexes' => $archiveIndexes,
        ];
        $this->writer->write($stage.'/assets/data/published-dates.json', CanonicalJson::encode($manifest, true));

        return $manifest;
    }

    /** @param list<array<string, mixed>> $records */
    private function writeSitemapAndRobots(string $stage, array $records, \DateTimeImmutable $builtAt): void
    {
        $routes = ['/', '/wordle-solver/', '/wordle-answers/', '/how-to-play-wordle/', '/wordle-strategy/', '/about/', '/privacy/'];
        $archivePageSize = $this->config->archivePageSize();
        if ($archivePageSize < 1) {
            throw new \LogicException('Archive page size must be positive.');
        }
        $archivePageCount = (int) ceil(count($records) / $archivePageSize);
        for ($page = 2; $page <= $archivePageCount; ++$page) {
            $routes[] = sprintf('/wordle-answers/page/%d/', $page);
        }
        $periods = [];
        foreach ($records as $record) {
            $routes[] = '/wordle-answers/'.$record['puzzle_date'].'/';
            $periods['/wordle-answers/'.substr((string) $record['puzzle_date'], 0, 4).'/'] = true;
            $periods['/wordle-answers/'.substr((string) $record['puzzle_date'], 0, 4).'/'.substr((string) $record['puzzle_date'], 5, 2).'/'] = true;
        }
        $routes = array_merge($routes, array_keys($periods));
        $lastModified = $builtAt->format('Y-m-d');
        $urls = array_map(
            fn (string $route): string => sprintf(
                '  <url><loc>%s</loc><lastmod>%s</lastmod></url>',
                htmlspecialchars($this->canonical($route), ENT_XML1 | ENT_QUOTES, 'UTF-8'),
                $lastModified,
            ),
            $routes,
        );
        $sitemap = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n".implode("\n", $urls)."\n</urlset>\n";
        $this->writer->write($stage.'/sitemap.xml', $sitemap);
        $robots = 'fixture' === $this->config->mode()
            ? "User-agent: *\nDisallow: /\n"
            : "User-agent: *\nAllow: /\nSitemap: ".$this->canonical('/sitemap.xml')."\n";
        $this->writer->write($stage.'/robots.txt', $robots);
    }

    /** @param array<string, mixed> $context */
    private function render(string $stage, string $path, string $template, array $context): void
    {
        $this->writer->write($stage.'/'.str_replace('\\', '/', $path), $this->twig->render($template, $context));
    }

    /**
     * @param array<string, mixed> $assets
     *
     * @return array<string, mixed>
     */
    private function baseContext(array $assets, \DateTimeImmutable $builtAt): array
    {
        return [
            'assets' => $assets,
            'site_name' => $this->config->siteName(),
            'mode' => $this->config->mode(),
            'contact_address' => $this->config->contactAddress(),
            'build_year' => $builtAt->format('Y'),
        ];
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return array<string, mixed>
     */
    private function selectHomepageRecord(array $records, \DateTimeImmutable $builtAt): array
    {
        $utcDate = $builtAt->format('Y-m-d');
        $eligible = array_values(array_filter($records, static fn (array $record): bool => $record['puzzle_date'] <= $utcDate));

        return [] !== $eligible
            ? $eligible[count($eligible) - 1]
            : $records[count($records) - 1];
    }

    /**
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     */
    private function withFullDate(array $record): array
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $record['puzzle_date'], new \DateTimeZone('UTC'));
        if (!$date instanceof \DateTimeImmutable) {
            throw new \RuntimeException('Validated record date could not be formatted.');
        }
        $record['full_date'] = $date->format('F j, Y');

        return $record;
    }

    private function canonical(string $route): string
    {
        return $this->config->canonicalOrigin().$route;
    }

    private function pageTitle(string $title): string
    {
        return $title.' | '.$this->config->siteName();
    }

    /** @param array<string, mixed> $data */
    private function structuredData(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
    }

    private function promote(string $stage, string $output): void
    {
        $rollback = $output.'.rollback';
        if (file_exists($rollback)) {
            $this->filesystem->remove($rollback);
        }
        $hadPrevious = file_exists($output);
        if ($hadPrevious && !rename($output, $rollback)) {
            throw new \RuntimeException('Unable to retain the previous public build.');
        }
        if (!rename($stage, $output)) {
            if ($hadPrevious) {
                rename($rollback, $output);
            }
            throw new \RuntimeException('Unable to promote the staged public build.');
        }
    }

    private function assertSafeOutputPath(string $path): void
    {
        try {
            PathPolicy::assertManagedRoot($path, $this->projectRoot, 'Public output');
            PathPolicy::assertDisjoint($path, $this->config->privateStorage(), 'Public output', 'Private storage');
            PathPolicy::assertDisjoint($path, $this->config->backupPath(), 'Public output', 'Backup path');
            $rollback = $path.'.rollback';
            PathPolicy::assertManagedRoot($rollback, $this->projectRoot, 'Public rollback');
            PathPolicy::assertDisjoint($rollback, $this->config->privateStorage(), 'Public rollback', 'Private storage');
            PathPolicy::assertDisjoint($rollback, $this->config->backupPath(), 'Public rollback', 'Backup path');

            if ('fixture' === $this->config->mode()) {
                PathPolicy::assertStrictlyInside($path, $this->projectRoot, 'Fixture output', 'the project workspace');
            }
        } catch (\InvalidArgumentException $exception) {
            throw new \RuntimeException('Unsafe public output path: '.$exception->getMessage(), previous: $exception);
        }
    }
}
