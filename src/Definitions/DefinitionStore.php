<?php

declare(strict_types=1);

namespace WordleHitToday\Definitions;

use WordleHitToday\Storage\AtomicFileWriter;
use WordleHitToday\Support\CanonicalJson;

final class DefinitionStore
{
    /** @var array<string, string>|null */
    private ?array $referencesByLemma = null;

    /** @var array<string, string>|null */
    private ?array $glossesByReference = null;

    public function __construct(
        private readonly string $privateRoot,
        private readonly AtomicFileWriter $writer,
    ) {
    }

    /** @param array<string, mixed> $document */
    public function save(array $document): void
    {
        $this->writer->write($this->path(), CanonicalJson::encode($document, true));
        $this->reload();
    }

    public function reload(): void
    {
        $this->referencesByLemma = null;
        $this->glossesByReference = null;
    }

    public function referenceFor(string $word): ?string
    {
        $this->loadIndexes();

        return $this->referencesByLemma[strtoupper($word)] ?? null;
    }

    public function glossFor(?string $reference): ?string
    {
        if (null === $reference) {
            return null;
        }

        $this->loadIndexes();

        return $this->glossesByReference[$reference] ?? null;
    }

    private function loadIndexes(): void
    {
        if (null !== $this->referencesByLemma && null !== $this->glossesByReference) {
            return;
        }

        if (!is_file($this->path())) {
            $this->referencesByLemma = [];
            $this->glossesByReference = [];

            return;
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

        $referencesByLemma = [];
        $glossesByReference = [];
        foreach ($rawEntries as $entry) {
            if (!is_array($entry)
                || !is_string($entry['reference'] ?? null)
                || !is_string($entry['lemma'] ?? null)
                || !is_string($entry['part_of_speech'] ?? null)
                || !is_string($entry['gloss'] ?? null)) {
                throw new \RuntimeException('Definition store contains an invalid entry.');
            }

            if (!$this->isSafeGloss($entry['lemma'], $entry['gloss'])) {
                continue;
            }
            if (!array_key_exists($entry['lemma'], $referencesByLemma)) {
                $referencesByLemma[$entry['lemma']] = $entry['reference'];
            }
            if (!array_key_exists($entry['reference'], $glossesByReference)) {
                $glossesByReference[$entry['reference']] = $entry['gloss'];
            }
        }

        $this->referencesByLemma = $referencesByLemma;
        $this->glossesByReference = $glossesByReference;
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
