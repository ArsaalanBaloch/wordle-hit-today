<?php

declare(strict_types=1);

namespace WordleHitToday\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WordleHitToday\Console\ExitCode;
use WordleHitToday\Fixtures\FixtureFactory;
use WordleHitToday\Publishing\PublisherLock;

#[AsCommand(name: 'build-fixtures', description: 'Create deterministic synthetic private records for local development.')]
final class BuildFixturesCommand extends ProjectCommand
{
    protected function configure(): void
    {
        $this->configureProjectOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $services = $this->services($input);
            if ('fixture' !== $services->config->mode()) {
                throw new \InvalidArgumentException('build-fixtures is available only in fixture mode.');
            }
            $lock = new PublisherLock();
            if (!$lock->acquire($services->config->privateStorage().'/publisher.lock')) {
                $io->note('Another writer holds the publication lock; fixture generation was safely skipped.');

                return ExitCode::Success->value;
            }
            try {
                $records = new FixtureFactory($services->hints)->records();
                foreach ($records as $record) {
                    $services->records->save($record);
                }
            } finally {
                $lock->release();
            }
            $io->success(sprintf('Wrote %d validated synthetic records to private storage.', count($records)));

            return ExitCode::Success->value;
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return ExitCode::InvalidInputOrConfiguration->value;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return ExitCode::StorageOrBuildFailure->value;
        }
    }
}
