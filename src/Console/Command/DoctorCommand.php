<?php

declare(strict_types=1);

namespace WordleHitToday\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WordleHitToday\Console\ExitCode;

#[AsCommand(name: 'doctor', description: 'Validate the local runtime, configuration and required project files.')]
final class DoctorCommand extends ProjectCommand
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
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return ExitCode::InvalidInputOrConfiguration->value;
        }

        $checks = [];
        $checks[] = ['PHP >= 8.4', version_compare(PHP_VERSION, '8.4.0', '>=')];
        foreach (['curl', 'json', 'openssl'] as $extension) {
            $checks[] = ['PHP extension '.$extension, extension_loaded($extension)];
        }
        foreach (['composer.lock', 'package-lock.json', 'schemas/config.schema.json', 'bin/wordle'] as $file) {
            $checks[] = [$file, is_file($this->projectRoot.'/'.$file)];
        }
        $checks[] = ['Private and public paths differ', $services->config->privateStorage() !== $services->config->publicOutput()];

        $failed = false;
        foreach ($checks as [$label, $passed]) {
            $io->writeln(sprintf('%s %s', $passed ? '<info>PASS</info>' : '<error>FAIL</error>', $label));
            $failed = $failed || !$passed;
        }

        if ('fixture' === $services->config->mode()) {
            $io->note('Fixture mode is intentionally using a placeholder origin and synthetic records.');
        }

        if ($failed) {
            $io->error('One or more required checks failed.');

            return ExitCode::InvalidInputOrConfiguration->value;
        }

        $io->success(sprintf('Environment is ready for %s mode.', $services->config->mode()));

        return ExitCode::Success->value;
    }
}
