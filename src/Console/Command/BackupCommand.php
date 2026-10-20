<?php

declare(strict_types=1);

namespace WordleHitToday\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WordleHitToday\Clock\SystemClock;
use WordleHitToday\Console\ExitCode;
use WordleHitToday\Publishing\PublisherLock;
use WordleHitToday\Storage\BackupManager;

#[AsCommand(name: 'backup', description: 'Create and checksum a retained private-data backup. No external request.')]
final class BackupCommand extends ProjectCommand
{
    protected function configure(): void
    {
        $this->configureProjectOptions();
        $this
            ->addOption('weekly', null, InputOption::VALUE_NONE, 'Create a weekly-retention backup instead of daily.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report source, destination and retention without writing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $services = $this->services($input);
            $kind = $input->getOption('weekly') ? 'weekly' : 'daily';
            $retention = $services->config->backupRetention()[$kind.'_copies'];
            if ($input->getOption('dry-run')) {
                $io->definitionList(
                    ['Source' => $services->config->privateStorage()],
                    ['Backup root' => $services->config->backupPath()],
                    ['Retention class' => sprintf('%s (%d copies)', $kind, $retention)],
                );
                $io->note('No files were written.');

                return ExitCode::Success->value;
            }
            $lock = new PublisherLock();
            if (!$lock->acquire($services->config->privateStorage().'/publisher.lock')) {
                $io->note('Another writer holds the publication lock; backup was safely skipped.');

                return ExitCode::Success->value;
            }
            $path = new BackupManager($services->writer)->create(
                $services->config->privateStorage(),
                $services->config->backupPath(),
                $this->projectPath((string) $input->getOption('config')),
                new SystemClock()->now(),
                $kind,
                $retention,
            );
            $lock->release();
            $io->success('Created verified backup: '.$path);

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
