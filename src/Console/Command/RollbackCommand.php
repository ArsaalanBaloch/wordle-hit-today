<?php

declare(strict_types=1);

namespace WordleHitToday\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WordleHitToday\Console\ExitCode;
use WordleHitToday\Publishing\PublisherLock;

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
            if (!is_file($rollback.DIRECTORY_SEPARATOR.'index.html')) {
                throw new \RuntimeException('A complete retained rollback build is not available.');
            }
            $io->definitionList(['Current public root' => $current], ['Retained rollback' => $rollback]);
            if (!$input->getOption('apply')) {
                $io->note('Inspection only. Re-run with --apply to swap directories under the publication lock.');

                return ExitCode::Success->value;
            }
            $lock = new PublisherLock();
            if (!$lock->acquire($services->config->privateStorage().'/publisher.lock')) {
                $io->note('Another writer holds the publication lock; rollback was safely skipped.');

                return ExitCode::Success->value;
            }
            $displaced = $current.'.displaced.'.gmdate('Ymd\THis\Z');
            if (file_exists($current) && !rename($current, $displaced)) {
                throw new \RuntimeException('Unable to retain the displaced public build.');
            }
            if (!rename($rollback, $current)) {
                if (file_exists($displaced)) {
                    rename($displaced, $current);
                }
                throw new \RuntimeException('Unable to promote the retained rollback build.');
            }
            $lock->release();
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
}
