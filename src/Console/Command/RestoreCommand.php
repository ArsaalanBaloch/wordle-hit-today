<?php

declare(strict_types=1);

namespace WordleHitToday\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WordleHitToday\Console\ExitCode;
use WordleHitToday\Storage\BackupManager;
use WordleHitToday\Storage\RecordStore;
use WordleHitToday\Support\PathPolicy;

#[AsCommand(name: 'restore', description: 'Verify a configured backup and restore it into a new private path. Never overwrites current data.')]
final class RestoreCommand extends ProjectCommand
{
    protected function configure(): void
    {
        $this->configureProjectOptions();
        $this
            ->addArgument('backup', InputArgument::REQUIRED, 'Backup directory name, such as daily-20261016T120000Z.')
            ->addOption('target', null, InputOption::VALUE_REQUIRED, 'New, non-existing private-storage path.')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Restore after checksum verification; otherwise verify and report only.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $services = $this->services($input);
            $name = (string) $input->getArgument('backup');
            if (1 !== preg_match('/^(?:daily|weekly)-\d{8}T\d{6}Z$/D', $name)) {
                throw new \InvalidArgumentException('Backup must be a simple configured daily/weekly backup directory name.');
            }
            $backup = rtrim($services->config->backupPath(), '\\/').DIRECTORY_SEPARATOR.$name;
            $targetOption = trim((string) $input->getOption('target'));
            if ('' === $targetOption) {
                throw new \InvalidArgumentException('--target is required and must identify a new private path.');
            }
            $target = PathPolicy::resolve($targetOption, $this->projectRoot, 'Restore target');
            $this->assertSafeTarget(
                $target,
                $services->config->publicOutput(),
                $services->config->privateStorage(),
                $services->config->backupPath(),
            );
            $manager = new BackupManager($services->writer);
            $files = $manager->verify($backup);
            if (!$input->getOption('apply')) {
                $io->success(sprintf('Verified %d backup files. Re-run with --apply to restore into %s.', count($files), $target));

                return ExitCode::Success->value;
            }
            $manager->restore($backup, $target);
            $restoredRecords = new RecordStore($target, $services->writer, $services->recordsValidator);
            $count = count($restoredRecords->all());
            $io->success(sprintf('Restored and schema-validated %d record(s) into new path %s.', $count, $target));
            $io->note('Current runtime storage was not changed. Point a reviewed configuration at the restored path, rebuild, smoke-test, then promote separately.');

            return ExitCode::Success->value;
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return ExitCode::InvalidInputOrConfiguration->value;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return ExitCode::StorageOrBuildFailure->value;
        }
    }

    private function assertSafeTarget(string $target, string $publicRoot, string $privateRoot, string $backupRoot): void
    {
        PathPolicy::assertManagedRoot($target, $this->projectRoot, 'Restore target');
        PathPolicy::assertDisjoint($target, $publicRoot, 'Restore target', 'Public output');
        PathPolicy::assertDisjoint($target, $publicRoot.'.rollback', 'Restore target', 'Public rollback');
        PathPolicy::assertDisjoint($target, $privateRoot, 'Restore target', 'Private storage');
        PathPolicy::assertDisjoint($target, $backupRoot, 'Restore target', 'Backup path');

        if (file_exists($target) || is_link($target)) {
            throw new \InvalidArgumentException('Restore target must not already exist.');
        }
    }
}
