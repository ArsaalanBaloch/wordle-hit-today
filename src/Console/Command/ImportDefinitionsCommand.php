<?php

declare(strict_types=1);

namespace WordleHitToday\Console\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WordleHitToday\Console\ExitCode;

#[AsCommand(name: 'import-definitions', description: 'Validate and import local licensed definition JSON. No network request.')]
final class ImportDefinitionsCommand extends ProjectCommand
{
    protected function configure(): void
    {
        $this->configureProjectOptions();
        $this
            ->addOption('input', null, InputOption::VALUE_REQUIRED, 'JSON array of lemma, part_of_speech, sense_id and gloss objects.')
            ->addOption('license', null, InputOption::VALUE_REQUIRED, 'Local source license text.')
            ->addOption('sha256', null, InputOption::VALUE_REQUIRED, 'Expected lowercase SHA-256 of the JSON input.')
            ->addOption('source-version', null, InputOption::VALUE_REQUIRED, 'Pinned definition source/version label.', 'Princeton WordNet 3.0')
            ->addOption('apply', null, InputOption::VALUE_NONE, 'Write the normalized private definition store; otherwise report only.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $services = $this->services($input);
            $path = (string) $input->getOption('input');
            $licensePath = (string) $input->getOption('license');
            $expectedHash = (string) $input->getOption('sha256');
            if (!is_file($path) || !is_file($licensePath) || 1 !== preg_match('/^[a-f0-9]{64}$/D', $expectedHash)) {
                throw new \InvalidArgumentException('Readable --input/--license files and a lowercase SHA-256 are required.');
            }
            if (filesize($path) > 64 * 1024 * 1024) {
                throw new \InvalidArgumentException('Definition input exceeds the reviewed 64 MiB import limit.');
            }
            $json = file_get_contents($path);
            $license = file_get_contents($licensePath);
            if (false === $json || false === $license || '' === trim($license)) {
                throw new \InvalidArgumentException('Definition input and non-empty license must be readable.');
            }
            $actualHash = hash('sha256', $json);
            if (!hash_equals($expectedHash, $actualHash)) {
                throw new \InvalidArgumentException('Definition checksum does not match --sha256.');
            }
            /** @var mixed $decoded */
            $decoded = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || !array_is_list($decoded)) {
                throw new \InvalidArgumentException('Definition input must be a JSON array.');
            }
            $entries = [];
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $lemma = strtoupper(trim((string) ($item['lemma'] ?? '')));
                $pos = trim((string) ($item['part_of_speech'] ?? ''));
                $sense = trim((string) ($item['sense_id'] ?? ''));
                $gloss = trim((string) ($item['gloss'] ?? ''));
                if (1 !== preg_match('/^[A-Z]{5}$/D', $lemma)
                    || 1 !== preg_match('/^[A-Za-z0-9._:-]{1,80}$/D', $sense)
                    || !in_array($pos, ['noun', 'verb', 'adjective', 'adverb'], true)
                    || strlen($gloss) < 8
                    || strlen($gloss) > 300) {
                    continue;
                }
                if (1 === preg_match('/\b'.preg_quote(strtolower($lemma), '/').'(?:s|ed|ing)?\b/i', $gloss)) {
                    continue;
                }
                $entries[] = [
                    'reference' => 'wordnet:'.$sense,
                    'lemma' => $lemma,
                    'part_of_speech' => $pos,
                    'gloss' => $gloss,
                ];
            }
            if ([] === $entries) {
                throw new \InvalidArgumentException('No safe five-letter definition entries passed validation.');
            }
            $document = [
                'schema_version' => 1,
                'source' => [
                    'version' => trim((string) $input->getOption('source-version')),
                    'input_sha256' => $actualHash,
                    'license_sha256' => hash('sha256', $license),
                ],
                'entries' => $entries,
            ];
            if ($input->getOption('apply')) {
                $services->definitions->save($document);
                $services->writer->write($services->config->privateStorage().'/definitions/LICENSE.txt', $license);
            }
            $io->success(sprintf(
                '%s %d safe definition entries; %d input rows were omitted.',
                $input->getOption('apply') ? 'Imported' : 'Validated',
                count($entries),
                count($decoded) - count($entries),
            ));

            return ExitCode::Success->value;
        } catch (\JsonException|\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return ExitCode::InvalidInputOrConfiguration->value;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return ExitCode::StorageOrBuildFailure->value;
        }
    }
}
