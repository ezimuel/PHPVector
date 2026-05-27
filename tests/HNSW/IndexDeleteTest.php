<?php

declare(strict_types=1);

namespace PHPVector\Tests\HNSW;

use PHPUnit\Framework\TestCase;
use PHPVector\Distance;
use PHPVector\Document;
use PHPVector\HNSW\Config;
use PHPVector\HNSW\Index;

final class IndexDeleteTest extends TestCase
{
    private function makeIndex(Distance $dist = Distance::Euclidean): Index
    {
        return new Index(new Config(M: 8, efConstruction: 50, efSearch: 20, distance: $dist));
    }

    /** @return float[] */
    private function randomVector(int $dim = 8): array
    {
        $v = [];
        for ($i = 0; $i < $dim; $i++) {
            $v[] = (float) mt_rand(-100, 100) / 100.0;
        }
        return $v;
    }

    public function testDeleteReturnsTrueThenFalse(): void
    {
        $index = $this->makeIndex();
        $index->insert(new Document(id: 1, vector: [1.0, 0.0, 0.0])); // node 0

        self::assertTrue($index->delete(0));   // first delete succeeds
        self::assertFalse($index->delete(0));  // already deleted
        self::assertFalse($index->delete(99)); // non-existent node
    }

    public function testDeletedNodeExcludedFromSearch(): void
    {
        $index = $this->makeIndex(Distance::Euclidean);
        $index->insert(new Document(id: 'a', vector: [0.0, 0.0])); // node 0, otherwise closest
        $index->insert(new Document(id: 'b', vector: [5.0, 5.0])); // node 1

        self::assertTrue($index->delete(0));

        $ids = array_map(
            fn($sr) => $sr->document->id,
            $index->search([0.1, 0.1], 5),
        );

        self::assertNotContains('a', $ids);
        self::assertContains('b', $ids);
    }

    public function testIsDeletedReflectsState(): void
    {
        $index = $this->makeIndex();
        $index->insert(new Document(id: 1, vector: [1.0, 0.0, 0.0]));

        self::assertFalse($index->isDeleted(0));
        $index->delete(0);
        self::assertTrue($index->isDeleted(0));
    }

    public function testCountDecrementsOnDelete(): void
    {
        $index = $this->makeIndex();
        for ($i = 0; $i < 5; $i++) {
            $index->insert(new Document(id: $i, vector: $this->randomVector()));
        }
        self::assertSame(5, $index->count());

        $index->delete(2);
        self::assertSame(4, $index->count());
    }

    public function testDeleteAllReturnsEmptyResults(): void
    {
        $index = $this->makeIndex();
        for ($i = 0; $i < 5; $i++) {
            $index->insert(new Document(id: $i, vector: $this->randomVector()));
        }
        for ($node = 0; $node < 5; $node++) {
            $index->delete($node);
        }

        self::assertSame(0, $index->count());
        self::assertSame([], $index->search($this->randomVector(), 5));
    }
}
