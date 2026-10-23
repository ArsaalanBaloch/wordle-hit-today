<?php

declare(strict_types=1);

namespace WordleHitToday\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WordleHitToday\Clock\GlobalDateEligibility;
use WordleHitToday\Console\ExitCode;
use WordleHitToday\Console\ProjectServices;
use WordleHitToday\Publishing\PublisherLock;

#[AsCommand(name: 'build', description: 'Generate, validate and promote the static public site.')]
final class BuildCommand extends ProjectCommand
{
    protected function configure(): void
    {
        $this->configureProjectOptions();
        $this->addOption(
            'built-at',
            null,
            InputOption::VALUE_REQUIRED,
            'Optional ISO-8601 build instant for a fixture preview.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $services = $this->services($input);
            $lock = new PublisherLock();
            if (!$lock->acquire($services->config->privateStorage().'/publisher.lock')) {
                $io->note('Another writer holds the publication lock; build was safely skipped.');

                return ExitCode::Success->value;
            }
            try {
                $builtAtOption = $input->getOption('built-at');
                if (is_string($builtAtOption) && '' !== trim($builtAtOption)) {
                    if ('fixture' !== $services->config->mode()) {
                        throw new \InvalidArgumentException('--built-at is available only for fixture previews.');
                    }
                    try {
                        $builtAt = new \DateTimeImmutable($builtAtOption);
                    } catch (\Exception) {
                        throw new \InvalidArgumentException('--built-at must be a valid ISO-8601 instant.');
                    }
                } else {
                    $builtAt = 'fixture' === $services->config->mode()
                        ? $this->defaultFixtureBuildInstant($services)
                        : $services->clock->now();
                }
                $result = $services->publisher->publish($builtAt);
                $services->status->publication($services->clock->now());
            } finally {
                $lock->release();
            }
            $io->success(sprintf(
                'Published %d pages to %s (homepage date %s).',
                $result->pageCount,
                $result->outputDirectory,
                $result->homepageDate,
            ));

            return ExitCode::Success->value;
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return ExitCode::InvalidInputOrConfiguration->value;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return ExitCode::StorageOrBuildFailure->value;
        }
    }

    private function defaultFixtureBuildInstant(ProjectServices $services): \DateTimeImmutable
    {
        $deterministicInstant = new \DateTimeImmutable('2026-10-16T12:00:00Z');
        $eligibility = new GlobalDateEligibility();
        foreach ($services->records->all() as $record) {
            if ($eligibility->earliestPublicationInstant((string) $record['puzzle_date']) > $deterministicInstant) {
                return $services->clock->now();
            }
        }

        return $deterministicInstant;
    }
}
