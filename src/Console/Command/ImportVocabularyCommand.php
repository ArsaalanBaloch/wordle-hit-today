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

#[AsCommand(name: 'import-vocabulary', description: 'Validate and import a pinned, prefiltered five-letter vocabulary from a local file. No network request.')]
final class ImportVocabularyCommand extends ProjectCommand
{
    protected function configure(): void
    {
        $this->configureProjectOptions();
        $this
            ->addOption('input', null, InputOption::VALUE_REQUIRED, 'Local newline or tab-separated word list.')
            ->addOption('license', null, InputOption::VALUE_REQUIRED, 'Local license text for the exact dataset.')
            ->addOption('sha256', null, InputOption::VALUE_REQUIRED, 'Expected lowercase SHA-256 checksum of the input.')
            ->addOption('source-version', null, InputOption::VALUE_REQUIRED, 'Pinned source/version label.')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Write the normalized private vocabulary; otherwise report only.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $services = $this->services($input);
            $inputPath = $this->requiredFileOption($input, 'input');
            $licensePath = $this->requiredFileOption($input, 'license');
            $expectedHash = (string) $input->getOption('sha256');
            $version = trim((string) $input->getOption('source-version'));
            if (1 !== preg_match('/^[a-f0-9]{64}$/D', $expectedHash) || '' === $version) {
                throw new \InvalidArgumentException('A lowercase SHA-256 and non-empty source-version are required.');
            }
            $actualHash = hash_file('sha256', $inputPath);
            if (!is_string($actualHash) || !hash_equals($expectedHash, $actualHash)) {
                throw new \InvalidArgumentException('Vocabulary checksum does not match --sha256.');
            }
            if (filesize($inputPath) > 16 * 1024 * 1024) {
                throw new \InvalidArgumentException('Vocabulary input exceeds the reviewed 16 MiB import limit.');
            }
            $contents = file_get_contents($inputPath);
            $license = file_get_contents($licensePath);
            if (false === $contents || false === $license || '' === trim($license)) {
                throw new \InvalidArgumentException('Vocabulary input and non-empty license must be readable.');
            }
            $words = [];
            foreach (preg_split('/\R/', $contents) ?: [] as $line) {
                $value = trim(explode("\t", $line, 2)[0]);
                if ('' === $value || str_starts_with($value, '#')) {
                    continue;
                }
                if (1 === preg_match('/^[A-Za-z]{5}$/D', $value)) {
                    $words[strtoupper($value)] = true;
                }
            }
            $words = array_keys($words);
            sort($words, SORT_STRING);
            if (count($words) < 1_000) {
                throw new \InvalidArgumentException('Production vocabulary contains fewer than 1,000 valid five-letter words.');
            }
            $document = [
                'schema_version' => 1,
                'mode' => 'production',
                'coverage_note' => 'Pinned, locally imported general-English list; not an official NYT vocabulary.',
                'source' => [
                    'version' => $version,
                    'input_sha256' => $actualHash,
                    'license_sha256' => hash('sha256', $license),
                    'word_count' => count($words),
                ],
                'candidates' => array_map(
                    static fn (string $word, int $index): array => ['word' => $word, 'rank' => $index + 1],
                    $words,
                    array_keys($words),
                ),
            ];
            $target = $services->config->privateStorage().'/vocabulary/solver.json';
            if ($input->getOption('apply')) {
                $services->writer->write($target, CanonicalJson::encode($document, true));
                $services->writer->write($services->config->privateStorage().'/vocabulary/LICENSE.txt', $license);
            }
            $io->success(sprintf(
                '%s %d unique five-letter words. %s',
                $input->getOption('apply') ? 'Imported' : 'Validated',
                count($words),
                $input->getOption('apply') ? 'Use WORDLE_VOCABULARY_PATH='.$target.' for npm run build.' : 'Re-run with --apply to write.',
            ));

            return ExitCode::Success->value;
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return ExitCode::InvalidInputOrConfiguration->value;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return ExitCode::StorageOrBuildFailure->value;
        }
    }

    private function requiredFileOption(InputInterface $input, string $name): string
    {
        $path = (string) $input->getOption($name);
        if ('' === $path || !is_file($path)) {
            throw new \InvalidArgumentException(sprintf('--%s must reference a readable local file.', $name));
        }

        return $path;
    }
}
