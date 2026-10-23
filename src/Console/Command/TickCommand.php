<?php

declare(strict_types=1);

namespace WordleHitToday\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WordleHitToday\Clock\GlobalDateEligibility;
use WordleHitToday\Console\ExitCode;
use WordleHitToday\Publishing\PublisherLock;
use WordleHitToday\Source\SourceFailure;

#[AsCommand(name: 'tick', description: 'Run one bounded scheduler tick: heartbeat, eligible-date source checks and publication.')]
final class TickCommand extends ProjectCommand
{
    protected function configure(): void
    {
        $this->configureProjectOptions();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $started = hrtime(true);
        $runId = bin2hex(random_bytes(8));
        $lock = null;
        try {
            $services = $this->services($input);
            $lock = new PublisherLock();
            if (!$lock->acquire($services->config->privateStorage().'/publisher.lock')) {
                $io->note('Another writer holds the publication lock; tick was safely skipped.');

                return ExitCode::Success->value;
            }
            $now = $services->clock->now();
            $services->status->heartbeat($now);
            if ('fixture' === $services->config->mode()) {
                $services->logger->write($now, [
                    'run_id' => $runId,
                    'eligible_date' => null,
                    'stage' => 'heartbeat',
                    'result' => 'fixture_no_fetch',
                    'retry_count' => 0,
                    'duration_ms' => $this->elapsedMilliseconds($started),
                    'public_publication_time' => null,
                ]);
                $io->success('Fixture tick updated the heartbeat; no external source request was made.');

                return ExitCode::Success->value;
            }

            $client = $services->sourceClient();
            $factory = $services->productionRecordFactory();
            $changed = false;
            $sourceFailed = false;
            foreach (new GlobalDateEligibility()->datesAt($now) as $date) {
                if (!$services->status->shouldAttempt($date, $now)) {
                    continue;
                }
                try {
                    $payload = $client->fetch($date);
                    $existing = $services->records->find($date);
                    $record = $factory->production(
                        $payload,
                        $client->urlFor($date),
                        $now,
                        definitionReference: $services->definitions->referenceFor($payload['solution']),
                    );
                    if (null !== $existing) {
                        if ($existing['solution'] !== $record['solution'] || $existing['upstream_id'] !== $record['upstream_id']) {
                            throw new SourceFailure('anomaly', 'A recheck conflicts with the retained canonical record.');
                        }
                    } else {
                        $services->records->save($record);
                        $changed = true;
                    }
                    $services->status->success($date, $now);
                    $services->logger->write($now, [
                        'run_id' => $runId,
                        'eligible_date' => $date,
                        'stage' => null === $existing ? 'validated' : 'rechecked',
                        'result' => 'success',
                        'retry_count' => 0,
                        'duration_ms' => $this->elapsedMilliseconds($started),
                        'public_publication_time' => null,
                    ]);
                } catch (SourceFailure $exception) {
                    $sourceFailed = true;
                    $services->status->failure($date, $now, $exception->category, $exception->retryAfterSeconds);
                    $services->logger->write($now, [
                        'run_id' => $runId,
                        'eligible_date' => $date,
                        'stage' => 'source',
                        'result' => $exception->category,
                        'retry_count' => 1,
                        'duration_ms' => $this->elapsedMilliseconds($started),
                        'public_publication_time' => null,
                    ]);
                }
            }
            $publicationNeeded = $changed || $services->publisher->needsPublication();
            if ($publicationNeeded) {
                $result = $services->publisher->publish($now);
                $services->status->publication($now);
                $services->logger->write($now, [
                    'run_id' => $runId,
                    'eligible_date' => $result->homepageDate,
                    'stage' => 'published',
                    'result' => 'success',
                    'retry_count' => 0,
                    'duration_ms' => $this->elapsedMilliseconds($started),
                    'public_publication_time' => $now->format('Y-m-d\TH:i:s\Z'),
                ]);
            }
            if ($sourceFailed) {
                $io->warning('Tick completed, but one or more source dates failed; retry state was persisted.');

                return ExitCode::SourceOrNetworkBlocked->value;
            }
            $io->success($publicationNeeded ? 'Tick published authoritative records.' : 'Tick completed; no publication was needed.');

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

    private function elapsedMilliseconds(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
