<?php

declare(strict_types=1);

namespace WordleHitToday\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use WordleHitToday\Console\ProjectServices;

abstract class ProjectCommand extends Command
{
    public function __construct(protected readonly string $projectRoot)
    {
        parent::__construct();
    }

    protected function configureProjectOptions(): void
    {
        $this
            ->addOption('config', null, InputOption::VALUE_REQUIRED, 'Configuration JSON path.', 'config/site.example.json')
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'Expected mode; must match configuration.', 'fixture');
    }

    protected function services(InputInterface $input): ProjectServices
    {
        $configPath = $this->projectPath((string) $input->getOption('config'));
        $mode = $input->getOption('mode');

        return ProjectServices::load($this->projectRoot, $configPath, is_string($mode) ? $mode : null);
    }

    protected function projectPath(string $path): string
    {
        if ($this->isAbsolutePath($path)) {
            return rtrim($path, '\\/');
        }

        return $this->projectRoot.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    protected function isAbsolutePath(string $path): bool
    {
        return 1 === preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path);
    }
}
