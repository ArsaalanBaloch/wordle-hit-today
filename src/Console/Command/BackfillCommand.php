<?php

declare(strict_types=1);

namespace WordleHitToday\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WordleHitToday\Clock\GlobalDateEligibility;
use WordleHitToday\Clock\SystemClock;
use WordleHitToday\Console\ExitCode;
use WordleHitToday\Publishing\PublisherLock;
use WordleHitToday\Source\SourceFailure;

#[AsCommand(name: 'backfill', description: 'Fetch a confirmed bounded production date range, then publish once. Performs external HTTPS requests.')]
final class BackfillCommand extends ProjectCommand
{
    protected function configure(): void
    {
        $this->configureProjectOptions();
        $this
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'First YYYY-MM-DD date.')
            ->addOption('to', null, InputOption::VALUE_REQUIRED, 'Last YYYY-MM-DD date.')
            ->addOption('confirm', null, InputOption::VALUE_NONE, 'Confirm up to 31 external source requests and private writes.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List the bounded request plan without network or writes.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $services = $this->services($input);
            if ('production' !== $services->config->mode()) {
                throw new \InvalidArgumentException('backfill requires a production configuration.');
            }
            $dates = $this->dateRange((string) $input->getOption('from'), (string) $input->getOption('to'));
            $pending = array_values(array_filter($dates, fn (string $date): bool => null === $services->records->find($date)));
            if ($input->getOption('dry-run')) {
                $io->listing(array_map(static fn (string $date): string => 'GET validated source record for '.$date, $pending));
                $io->success(sprintf('%d request(s) would be made; no network or write occurred.', count($pending)));

                return ExitCode::Success->value;
            }
            if (!$input->getOption('confirm')) {
                throw new \InvalidArgumentException('Use --dry-run first, then --confirm to authorize bounded source requests.');
            }
            $lock = new PublisherLock();
            if (!$lock->acquire($services->config->privateStorage().'/publisher.lock')) {
                $io->note('Another writer holds the publication lock; backfill was safely skipped.');

                return ExitCode::Success->value;
            }
            $now = new SystemClock()->now();
            $eligibility = new GlobalDateEligibility();
            $client = $services->sourceClient();
            $factory = $services->productionRecordFactory();
            $saved = 0;
            $failures = [];
            foreach ($pending as $date) {
                if ($eligibility->earliestPublicationInstant($date) > $now) {
                    $failures[$date] = 'not_yet_eligible';
                    continue;
                }
                try {
                    $payload = $client->fetch($date);
                    $record = $factory->production(
                        $payload,
                        $client->urlFor($date),
                        $now,
                        definitionReference: $services->definitions->referenceFor($payload['solution']),
                    );
                    $services->records->save($record);
                    ++$saved;
                } catch (SourceFailure $exception) {
                    $failures[$date] = $exception->category;
                }
            }
            if ($saved > 0) {
                $services->publisher->publish($now);
                $services->status->publication($now);
            }
            $lock->release();
            if ([] !== $failures) {
                $io->warning(sprintf('Stored %d record(s); failures: %s.', $saved, json_encode($failures, JSON_THROW_ON_ERROR)));

                return ExitCode::SourceOrNetworkBlocked->value;
            }
            $io->success(sprintf('Stored %d record(s) and ran one publication build.', $saved));

            return ExitCode::Success->value;
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return ExitCode::InvalidInputOrConfiguration->value;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return ExitCode::StorageOrBuildFailure->value;
        }
    }

    /** @return list<string> */
    private function dateRange(string $from, string $to): array
    {
        $start = $this->parseDate($from);
        $end = $this->parseDate($to);
        if ($start > $end) {
            throw new \InvalidArgumentException('--from must not be later than --to.');
        }
        $days = (int) $start->diff($end)->format('%a') + 1;
        if ($days > 31) {
            throw new \InvalidArgumentException('Backfill is limited to 31 dates per confirmed run.');
        }
        $dates = [];
        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 day')) {
            $dates[] = $cursor->format('Y-m-d');
        }

        return $dates;
    }

    private function parseDate(string $date): \DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
        if (!$parsed instanceof \DateTimeImmutable || $parsed->format('Y-m-d') !== $date) {
            throw new \InvalidArgumentException('Backfill dates must be real YYYY-MM-DD calendar dates.');
        }

        return $parsed;
    }
}
