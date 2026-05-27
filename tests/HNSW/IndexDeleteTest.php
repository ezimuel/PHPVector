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

    public function testSearchStillReturnsKLiveResultsAfterScatteredDeletes(): void
    {
        mt_srand(42);
        $index = new Index(new Config(M: 16, efConstruction: 200, efSearch: 50, distance: Distance::Euclidean));

        for ($i = 0; $i < 50; $i++) {
            $index->insert(new Document(id: $i, vector: $this->randomVector(8)));
        }

        $deleted = [3, 8, 13, 19, 24, 30, 36, 41, 45, 49];
        foreach ($deleted as $node) {
            self::assertTrue($index->delete($node));
        }

        $results = $index->search($this->randomVector(8), 10);

        self::assertCount(10, $results);
        foreach ($results as $sr) {
            self::assertNotContains($sr->document->id, $deleted);
        }
    }

    public function testChurnPreservesRecall(): void
    {
        mt_srand(42);
        $dim = 32;
        $k   = 10;
        $index = new Index(new Config(M: 16, efConstruction: 200, efSearch: 100, distance: Distance::Euclidean));

        $vectors = []; // live set: id => vector

        for ($i = 0; $i < 200; $i++) {
            $v = $this->randomVector($dim);
            $vectors[$i] = $v;
            $index->insert(new Document(id: $i, vector: $v));
        }

        // Delete a contiguous middle block (node ids 50..99), avoiding the entry point (node 0).
        for ($node = 50; $node < 100; $node++) {
            $index->delete($node);
            unset($vectors[$node]);
        }

        // Insert 50 new documents (ids and node ids 200..249).
        for ($i = 200; $i < 250; $i++) {
            $v = $this->randomVector($dim);
            $vectors[$i] = $v;
            $index->insert(new Document(id: $i, vector: $v));
        }

        $query = $this->randomVector($dim);
        $hnswIds = array_map(fn($sr) => $sr->document->id, $index->search($query, $k));

        // Brute-force ground truth over the live set.
        $dists = [];
        foreach ($vectors as $id => $v) {
            $dists[$id] = sqrt(array_sum(array_map(fn($a, $b) => ($a - $b) ** 2, $query, $v)));
        }
        asort($dists);
        $groundTruth = array_slice(array_keys($dists), 0, $k);

        $recalled = count(array_intersect($hnswIds, $groundTruth));

        self::assertGreaterThanOrEqual(
            (int) ceil($k * 0.7),
            $recalled,
            sprintf('Churn recall too low: %d/%d', $recalled, $k),
        );
    }
}
