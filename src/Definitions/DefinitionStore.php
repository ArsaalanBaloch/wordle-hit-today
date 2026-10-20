<?php

declare(strict_types=1);

namespace WordleHitToday\Definitions;

use WordleHitToday\Storage\AtomicFileWriter;
use WordleHitToday\Support\CanonicalJson;

final readonly class DefinitionStore
{
    public function __construct(private string $privateRoot, private AtomicFileWriter $writer)
    {
    }

    /** @param array<string, mixed> $document */
    public function save(array $document): void
    {
        $this->writer->write($this->path(), CanonicalJson::encode($document, true));
    }

    public function referenceFor(string $word): ?string
    {
        $upper = strtoupper($word);
        foreach ($this->entries() as $entry) {
            if ($entry['lemma'] === $upper && $this->isSafeGloss($upper, $entry['gloss'])) {
                return $entry['reference'];
            }
        }

        return null;
    }

    public function glossFor(?string $reference): ?string
    {
        if (null === $reference) {
            return null;
        }
        foreach ($this->entries() as $entry) {
            if ($entry['reference'] === $reference && $this->isSafeGloss($entry['lemma'], $entry['gloss'])) {
                return $entry['gloss'];
            }
        }

        return null;
    }

    /** @return list<array{reference: string, lemma: string, part_of_speech: string, gloss: string}> */
    private function entries(): array
    {
        if (!is_file($this->path())) {
            return [];
        }
        $json = file_get_contents($this->path());
        if (false === $json) {
            throw new \RuntimeException('Definition store is not readable.');
        }
        /** @var array<string, mixed> $document */
        $document = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        $rawEntries = $document['entries'] ?? null;
        if (!is_array($rawEntries)) {
            throw new \RuntimeException('Definition store has an invalid entries collection.');
        }

        $entries = [];
        foreach ($rawEntries as $entry) {
            if (!is_array($entry)
                || !is_string($entry['reference'] ?? null)
                || !is_string($entry['lemma'] ?? null)
                || !is_string($entry['part_of_speech'] ?? null)
                || !is_string($entry['gloss'] ?? null)) {
                throw new \RuntimeException('Definition store contains an invalid entry.');
            }
            $entries[] = [
                'reference' => $entry['reference'],
                'lemma' => $entry['lemma'],
                'part_of_speech' => $entry['part_of_speech'],
                'gloss' => $entry['gloss'],
            ];
        }

        return $entries;
    }

    private function isSafeGloss(string $word, string $gloss): bool
    {
        $pattern = '/\b'.preg_quote(strtolower($word), '/').'(?:s|ed|ing)?\b/i';

        return strlen($gloss) >= 8 && strlen($gloss) <= 300 && 1 !== preg_match($pattern, $gloss);
    }

    private function path(): string
    {
        return $this->privateRoot.DIRECTORY_SEPARATOR.'definitions'.DIRECTORY_SEPARATOR.'definitions.json';
    }
}
