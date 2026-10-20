<?php

declare(strict_types=1);

namespace WordleHitToday\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WordleHitToday\Clock\SystemClock;
use WordleHitToday\Console\ExitCode;
use WordleHitToday\Publishing\PublisherLock;
use WordleHitToday\Source\PuzzleNumberResolver;
use WordleHitToday\Source\RecordFactory;
use WordleHitToday\Source\SourceFailure;

#[AsCommand(name: 'fetch-preview-date', description: 'Fetch one source-validated date for a private, non-indexable fixture preview.')]
final class FetchPreviewDateCommand extends ProjectCommand
{
    protected function configure(): void
    {
        $this->configureProjectOptions();
        $this->addArgument('date', InputArgument::REQUIRED, 'Strict YYYY-MM-DD date.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $services = $this->services($input);
            if ('fixture' !== $services->config->mode()) {
                throw new \InvalidArgumentException('fetch-preview-date requires a fixture configuration.');
            }
            $date = (string) $input->getArgument('date');
            $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
            if (!$parsedDate instanceof \DateTimeImmutable || $parsedDate->format('Y-m-d') !== $date) {
                throw new \InvalidArgumentException('Date must be a real calendar date in YYYY-MM-DD form.');
            }
            if ($parsedDate > new \DateTimeImmutable('today', new \DateTimeZone('UTC'))) {
                throw new \InvalidArgumentException('A private preview cannot fetch a future date.');
            }
            if (null !== $services->records->find($date)) {
                throw new \InvalidArgumentException('A record already exists for this date.');
            }

            $lock = new PublisherLock();
            if (!$lock->acquire($services->config->privateStorage().'/publisher.lock')) {
                $io->note('Another writer holds the publication lock; preview fetch was safely skipped.');

                return ExitCode::Success->value;
            }
            try {
                $client = $services->sourceClient();
                $payload = $client->fetch($date);
                $factory = new RecordFactory(
                    new PuzzleNumberResolver($payload['print_date'], $payload['days_since_launch']),
                    $services->hints,
                );
                $record = $factory->privatePreview(
                    $payload,
                    $client->urlFor($date),
                    new SystemClock()->now(),
                    $services->definitions->referenceFor($payload['solution']),
                );
                $services->records->save($record);
            } finally {
                $lock->release();
            }
            $io->success(sprintf('Stored a source-validated private-preview record for %s.', $date));

            return ExitCode::Success->value;
        } catch (SourceFailure $exception) {
            $io->error(sprintf('Source %s: %s', $exception->category, $exception->getMessage()));

            return ExitCode::SourceOrNetworkBlocked->value;
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return ExitCode::InvalidInputOrConfiguration->value;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return ExitCode::StorageOrBuildFailure->value;
        }
    }
}
