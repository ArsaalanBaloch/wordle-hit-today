<?php

declare(strict_types=1);

namespace WordleHitToday\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WordleHitToday\Console\ExitCode;
use WordleHitToday\Support\CanonicalJson;

#[AsCommand(name: 'status', description: 'Show sanitized scheduler and publication state. No external request or write.')]
final class StatusCommand extends ProjectCommand
{
    protected function configure(): void
    {
        $this->configureProjectOptions();
        $this->addOption('json', null, InputOption::VALUE_NONE, 'Emit machine-readable JSON.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $services = $this->services($input);
            $status = $services->status->snapshot();
            if ($input->getOption('json')) {
                $output->write(CanonicalJson::encode($status, true));

                return ExitCode::Success->value;
            }
            $io->title('Publisher status');
            $io->definitionList(
                ['Mode' => $services->config->mode()],
                ['Heartbeat' => (string) ($status['heartbeat_at_utc'] ?? 'never')],
                ['Last source success' => (string) ($status['last_source_success_at_utc'] ?? 'never')],
                ['Last publication' => (string) ($status['last_publication_at_utc'] ?? 'never')],
                ['Tracked dates' => (string) count(is_array($status['dates']) ? $status['dates'] : [])],
            );

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
