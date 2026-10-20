<?php

declare(strict_types=1);

namespace WordleHitToday\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WordleHitToday\Console\ExitCode;

#[AsCommand(name: 'preview', description: 'Serve the generated site with local routing, headers and real status codes.')]
final class PreviewCommand extends ProjectCommand
{
    protected function configure(): void
    {
        $this->configureProjectOptions();
        $this
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Loopback host.', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'TCP port.', '8080');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $services = $this->services($input);
            $host = (string) $input->getOption('host');
            $port = filter_var($input->getOption('port'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1024, 'max_range' => 65535]]);
            if (!in_array($host, ['127.0.0.1', 'localhost'], true) || false === $port) {
                throw new \InvalidArgumentException('Preview host must be loopback and port must be between 1024 and 65535.');
            }
            if (!is_file($services->config->publicOutput().'/index.html')) {
                throw new \RuntimeException('Generated site is missing. Run the build command first.');
            }
            $address = sprintf('%s:%d', $host, $port);
            $io->success('Preview available at http://'.$address.'/ — stop with Ctrl+C.');
            $command = sprintf(
                '%s -S %s %s',
                escapeshellarg(PHP_BINARY),
                escapeshellarg($address),
                escapeshellarg($this->projectRoot.'/scripts/router.php'),
            );
            $environment = $_ENV;
            $environment['WORDLE_PUBLIC_ROOT'] = $services->config->publicOutput();
            $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $this->projectRoot, $environment);
            if (!is_resource($process)) {
                throw new \RuntimeException('Unable to start the preview server.');
            }

            return proc_close($process);
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return ExitCode::InvalidInputOrConfiguration->value;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return ExitCode::StorageOrBuildFailure->value;
        }
    }
}
