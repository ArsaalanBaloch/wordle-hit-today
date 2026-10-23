<?php

declare(strict_types=1);

namespace WordleHitToday\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WordleHitToday\Console\ExitCode;
use WordleHitToday\Publishing\PublicBuildIntegrity;
use WordleHitToday\Publishing\PublisherLock;
use WordleHitToday\Support\PathPolicy;

#[AsCommand(name: 'rollback', description: 'Promote the retained previous public build without modifying authoritative private records.')]
final class RollbackCommand extends ProjectCommand
{
    protected function configure(): void
    {
        $this->configureProjectOptions();
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Confirm the public directory swap; otherwise inspect only.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $services = $this->services($input);
            $current = $services->config->publicOutput();
            $rollback = $current.'.rollback';
            $io->definitionList(['Current public root' => $current], ['Retained rollback' => $rollback]);
            if (!$input->getOption('apply')) {
                $this->assertRollbackAvailable($rollback);
                $io->note('Inspection only. Re-run with --apply to swap directories under the publication lock.');

                return ExitCode::Success->value;
            }
            $lock = new PublisherLock();
            if (!$lock->acquire($services->config->privateStorage().'/publisher.lock')) {
                $io->note('Another writer holds the publication lock; rollback was safely skipped.');

                return ExitCode::Success->value;
            }
            try {
                $this->assertRollbackAvailable($rollback);
                $timestamp = $services->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
                $displaced = $current.'.displaced.'.$timestamp;
                PathPolicy::assertManagedRoot($displaced, $this->projectRoot, 'Displaced public build');
                PathPolicy::assertDisjoint($displaced, $services->config->privateStorage(), 'Displaced public build', 'Private storage');
                PathPolicy::assertDisjoint($displaced, $services->config->backupPath(), 'Displaced public build', 'Backup path');
                if (file_exists($displaced) || is_link($displaced)) {
                    throw new \RuntimeException('A displaced public build already exists for this timestamp.');
                }
                if (file_exists($current) && !rename($current, $displaced)) {
                    throw new \RuntimeException('Unable to retain the displaced public build.');
                }
                if (!rename($rollback, $current)) {
                    if (file_exists($displaced)) {
                        rename($displaced, $current);
                    }
                    throw new \RuntimeException('Unable to promote the retained rollback build.');
                }
            } finally {
                $lock->release();
            }
            $io->success('Promoted the retained previous public build. Displaced build: '.$displaced);
            $io->note('Authoritative private records were not rolled back. Reconcile and rebuild after diagnosing the release issue.');

            return ExitCode::Success->value;
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return ExitCode::InvalidInputOrConfiguration->value;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return ExitCode::StorageOrBuildFailure->value;
        }
    }

    private function assertRollbackAvailable(string $rollback): void
    {
        if (!is_dir($rollback)) {
            throw new \RuntimeException('A retained rollback build is not available.');
        }
        new PublicBuildIntegrity()->verify($rollback);
    }
}
