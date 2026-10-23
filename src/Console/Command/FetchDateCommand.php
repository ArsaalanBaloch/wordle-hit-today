<?php

declare(strict_types=1);

namespace WordleHitToday\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WordleHitToday\Clock\GlobalDateEligibility;
use WordleHitToday\Console\ExitCode;
use WordleHitToday\Publishing\PublisherLock;
use WordleHitToday\Source\SourceFailure;

#[AsCommand(name: 'fetch-date', description: 'Fetch, validate and store one eligible production puzzle date. Performs one external HTTPS request.')]
final class FetchDateCommand extends ProjectCommand
{
    protected function configure(): void
    {
        $this->configureProjectOptions();
        $this
            ->addArgument('date', InputArgument::REQUIRED, 'Strict YYYY-MM-DD date.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Fetch and validate without writing a private record.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $services = $this->services($input);
            if ('production' !== $services->config->mode()) {
                throw new \InvalidArgumentException('fetch-date requires a production configuration.');
            }
            $date = (string) $input->getArgument('date');
            $now = $services->clock->now();
            if (new GlobalDateEligibility()->earliestPublicationInstant($date) > $now) {
                throw new \InvalidArgumentException('The requested date is not globally eligible for publication yet.');
            }
            $lock = new PublisherLock();
            if (!$lock->acquire($services->config->privateStorage().'/publisher.lock')) {
                $io->note('Another writer holds the publication lock; fetch was safely skipped.');

                return ExitCode::Success->value;
            }
            try {
                if (null !== $services->records->find($date)) {
                    throw new \InvalidArgumentException('A record already exists for this date; use correct for a revision.');
                }
                $client = $services->sourceClient();
                $payload = $client->fetch($date);
                $definition = $services->definitions->referenceFor($payload['solution']);
                $record = $services->productionRecordFactory()->production(
                    $payload,
                    $client->urlFor($date),
                    $now,
                    definitionReference: $definition,
                );
                if (!$input->getOption('dry-run')) {
                    $services->records->save($record);
                }
            } finally {
                $lock->release();
            }
            $io->success(sprintf(
                '%s production record for %s after structural validation.',
                $input->getOption('dry-run') ? 'Validated' : 'Stored',
                $date,
            ));

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
