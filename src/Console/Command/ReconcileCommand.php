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
use WordleHitToday\Storage\DirectoryIntegrity;

#[AsCommand(name: 'reconcile', description: 'Verify the publication journal against public files; optionally rebuild from authoritative records.')]
final class ReconcileCommand extends ProjectCommand
{
    protected function configure(): void
    {
        $this->configureProjectOptions();
        $this->addOption('repair', null, InputOption::VALUE_NONE, 'Rebuild from validated records when journal checks fail.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $lock = null;
        try {
            $services = $this->services($input);
            $lock = new PublisherLock();
            if (!$lock->acquire($services->config->privateStorage().'/publisher.lock')) {
                $io->note('Another writer holds the publication lock; reconciliation was safely skipped.');

                return ExitCode::Success->value;
            }
            $journalPath = $services->config->privateStorage().'/journal/last-build.json';
            $json = is_file($journalPath) ? file_get_contents($journalPath) : false;
            if (false === $json) {
                throw new \RuntimeException('Publication journal is missing.');
            }
            $services->schemas->validateJson($json, 'journal.schema.json');
            /** @var array<string, mixed> $journal */
            $journal = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            $failures = [];
            if ('published' !== $journal['stage'] || true !== $journal['promoted']) {
                $failures[] = sprintf(
                    'journal state: stage=%s, promoted=%s',
                    (string) $journal['stage'],
                    true === $journal['promoted'] ? 'true' : 'false',
                );
            }
            $files = $journal['files'] ?? null;
            if (!is_array($files)) {
                throw new \RuntimeException('Publication journal file list is invalid.');
            }
            $expected = [];
            foreach ($files as $entry) {
                if (!is_array($entry) || !is_string($entry['path'] ?? null) || !is_string($entry['sha256'] ?? null)) {
                    throw new \RuntimeException('Publication journal contains an invalid file entry.');
                }
                $relative = $entry['path'];
                if (str_contains($relative, '..') || str_starts_with($relative, '/') || str_contains($relative, '\\')) {
                    throw new \RuntimeException('Publication journal contains an unsafe relative path.');
                }
                if (isset($expected[$relative])) {
                    throw new \RuntimeException('Publication journal contains a duplicate file entry.');
                }
                $expected[$relative] = $entry['sha256'];
            }
            $differences = new DirectoryIntegrity()->differences($services->config->publicOutput(), $expected);
            foreach (['missing', 'changed', 'extra'] as $category) {
                foreach ($differences[$category] as $relative) {
                    $failures[] = $category.': '.$relative;
                }
            }
            if ([] === $failures) {
                $io->success(sprintf('Journal reconciled %d public files with no mismatches.', count($files)));

                return ExitCode::Success->value;
            }
            if (!$input->getOption('repair')) {
                $io->error(sprintf('%d public file integrity mismatch(es): %s', count($failures), implode(', ', $failures)));

                return ExitCode::StorageOrBuildFailure->value;
            }
            $now = $services->clock->now();
            $services->publisher->publish($now);
            $services->status->publication($now);
            $io->success(sprintf('Rebuilt the public site after %d reconciliation mismatch(es).', count($failures)));

            return ExitCode::Success->value;
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return ExitCode::InvalidInputOrConfiguration->value;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return ExitCode::StorageOrBuildFailure->value;
        } finally {
            $lock?->release();
        }
    }
}
