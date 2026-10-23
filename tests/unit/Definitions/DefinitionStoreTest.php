<?php

declare(strict_types=1);

namespace WordleHitToday\Tests\Unit\Definitions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use WordleHitToday\Definitions\DefinitionStore;
use WordleHitToday\Storage\AtomicFileWriter;

#[CoversClass(DefinitionStore::class)]
final class DefinitionStoreTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        $this->temporaryRoot = dirname(__DIR__, 3).'/var/test-definitions-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->temporaryRoot);
    }

    public function testLookupsUseIndexedSafeDefinitionsAndPreserveFirstLemmaMatch(): void
    {
        $store = $this->store();
        $store->save($this->document([
            $this->entry('wordnet:apple:first', 'APPLE', 'A round fruit with firm flesh.'),
            $this->entry('wordnet:apple:second', 'APPLE', 'A cultivated fruit from an orchard.'),
            $this->entry('wordnet:crane:unsafe', 'CRANE', 'A crane used to lift heavy loads.'),
        ]));

        self::assertSame('wordnet:apple:first', $store->referenceFor('apple'));
        self::assertSame('A round fruit with firm flesh.', $store->glossFor('wordnet:apple:first'));
        self::assertSame('A cultivated fruit from an orchard.', $store->glossFor('wordnet:apple:second'));
        self::assertNull($store->referenceFor('CRANE'));
        self::assertNull($store->glossFor('wordnet:crane:unsafe'));
        self::assertNull($store->glossFor(null));
    }

    public function testEntriesAreLoadedOnceAndSaveInvalidatesBothIndexes(): void
    {
        $store = $this->store();
        $store->save($this->document([
            $this->entry('wordnet:apple', 'APPLE', 'A round fruit with firm flesh.'),
        ]));

        self::assertSame('wordnet:apple', $store->referenceFor('APPLE'));
        file_put_contents(
            $this->temporaryRoot.'/definitions/definitions.json',
            "{\"entries\":\"changed outside this store\"}\n",
        );
        self::assertSame('A round fruit with firm flesh.', $store->glossFor('wordnet:apple'));

        $store->save($this->document([
            $this->entry('wordnet:level', 'LEVEL', 'A position on a scale of height.'),
        ]));

        self::assertNull($store->referenceFor('APPLE'));
        self::assertSame('wordnet:level', $store->referenceFor('level'));
        self::assertSame('A position on a scale of height.', $store->glossFor('wordnet:level'));
    }

    public function testReloadObservesAStoreUpdatedByAnotherProcess(): void
    {
        $store = $this->store();
        $store->save($this->document([
            $this->entry('wordnet:apple', 'APPLE', 'A round fruit with firm flesh.'),
        ]));
        self::assertSame('wordnet:apple', $store->referenceFor('APPLE'));

        $otherProcess = $this->store();
        $otherProcess->save($this->document([
            $this->entry('wordnet:level', 'LEVEL', 'A position on a scale of height.'),
        ]));
        self::assertNull($store->referenceFor('LEVEL'));

        $store->reload();
        self::assertNull($store->referenceFor('APPLE'));
        self::assertSame('wordnet:level', $store->referenceFor('LEVEL'));
    }

    /**
     * @param list<array{reference: string, lemma: string, part_of_speech: string, gloss: string}> $entries
     *
     * @return array<string, mixed>
     */
    private function document(array $entries): array
    {
        return [
            'schema_version' => 1,
            'source' => ['version' => 'test'],
            'entries' => $entries,
        ];
    }

    /** @return array{reference: string, lemma: string, part_of_speech: string, gloss: string} */
    private function entry(string $reference, string $lemma, string $gloss): array
    {
        return [
            'reference' => $reference,
            'lemma' => $lemma,
            'part_of_speech' => 'noun',
            'gloss' => $gloss,
        ];
    }

    private function store(): DefinitionStore
    {
        return new DefinitionStore($this->temporaryRoot, new AtomicFileWriter());
    }
}
