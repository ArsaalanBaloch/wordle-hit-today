<?php

declare(strict_types=1);

namespace WordleHitToday\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WordleHitToday\Clock\SystemClock;
use WordleHitToday\Console\ExitCode;
use WordleHitToday\Publishing\PublisherLock;
use WordleHitToday\Source\SourceFailure;
use WordleHitToday\Source\SourcePayloadValidator;

#[AsCommand(name: 'correct', description: 'Preview or apply a revision from a retained, confirmed source-response file. No network request.')]
final class CorrectCommand extends ProjectCommand
{
    protected function configure(): void
    {
        $this->configureProjectOptions();
        $this
            ->addArgument('date', InputArgument::REQUIRED, 'Existing production record date.')
            ->addOption('expected-revision', null, InputOption::VALUE_REQUIRED, 'Current revision required for optimistic locking.')
            ->addOption('evidence-file', null, InputOption::VALUE_REQUIRED, 'Local JSON response retained as correction evidence.')
            ->addOption('notice', null, InputOption::VALUE_REQUIRED, 'Public correction notice explaining what changed.')
            ->addOption('confirm-evidence', null, InputOption::VALUE_NONE, 'Confirm that the evidence came from the configured official source URL.')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Retain the previous revision, save the new record and rebuild.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $services = $this->services($input);
            if ('production' !== $services->config->mode()) {
                throw new \InvalidArgumentException('correct requires a production configuration.');
            }
            $date = (string) $input->getArgument('date');
            $existing = $services->records->find($date);
            if (null === $existing) {
                throw new \InvalidArgumentException('No existing record was found for the correction date.');
            }
            $expectedRevision = filter_var($input->getOption('expected-revision'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (false === $expectedRevision || $expectedRevision !== $existing['content_revision']) {
                throw new \InvalidArgumentException('Expected revision does not match the current record.');
            }
            $evidencePath = (string) $input->getOption('evidence-file');
            $notice = trim((string) $input->getOption('notice'));
            if (!is_file($evidencePath) || strlen($notice) < 8 || strlen($notice) > 500) {
                throw new \InvalidArgumentException('A readable --evidence-file and an 8-500 character --notice are required.');
            }
            $json = file_get_contents($evidencePath);
            if (false === $json || strlen($json) > 65_536) {
                throw new \InvalidArgumentException('Correction evidence is unreadable or exceeds 64 KiB.');
            }
            $payload = new SourcePayloadValidator()->validate($json, $date);
            $client = $services->sourceClient();
            $record = $services->productionRecordFactory()->production(
                $payload,
                $client->urlFor($date),
                new SystemClock()->now(),
                $expectedRevision + 1,
                $services->definitions->referenceFor($payload['solution']),
                $notice,
            );
            if ($record['content_hash'] === $existing['content_hash']) {
                throw new \InvalidArgumentException('Correction evidence produces no record change.');
            }
            $io->definitionList(
                ['Date' => $date],
                ['Revision' => sprintf('%d -> %d', $expectedRevision, $expectedRevision + 1)],
                ['Solution' => sprintf('%s -> %s', $existing['solution'], $record['solution'])],
                ['Upstream ID' => sprintf('%s -> %s', (string) $existing['upstream_id'], (string) $record['upstream_id'])],
            );
            if (!$input->getOption('apply')) {
                $io->note('Dry-run only. Re-run with --confirm-evidence --apply after reviewing this diff.');

                return ExitCode::Success->value;
            }
            if (!$input->getOption('confirm-evidence')) {
                throw new \InvalidArgumentException('--apply also requires --confirm-evidence.');
            }
            $lock = new PublisherLock();
            if (!$lock->acquire($services->config->privateStorage().'/publisher.lock')) {
                $io->note('Another writer holds the publication lock; correction was safely skipped.');

                return ExitCode::Success->value;
            }
            $services->records->saveRevision($existing);
            $services->records->save($record);
            $now = new SystemClock()->now();
            $services->publisher->publish($now);
            $services->status->publication($now);
            $lock->release();
            $io->success('Correction revision was retained, saved and published.');

            return ExitCode::Success->value;
        } catch (SourceFailure $exception) {
            $io->error(sprintf('Correction evidence %s: %s', $exception->category, $exception->getMessage()));

            return ExitCode::SourceOrNetworkBlocked->value;
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return ExitCode::InvalidInputOrConfiguration->value;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return ExitCode::StorageOrBuildFailure->value;
        }
    }
}
