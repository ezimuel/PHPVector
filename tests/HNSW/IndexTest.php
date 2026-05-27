<?php

declare(strict_types=1);

namespace PHPVector\Tests\HNSW;

use PHPUnit\Framework\TestCase;
use PHPVector\Distance;
use PHPVector\Document;
use PHPVector\Exception\DimensionMismatchException;
use PHPVector\HNSW\Config;
use PHPVector\HNSW\Index;

final class IndexTest extends TestCase
{
    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

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

    /**
     * Normalise a vector to unit length for cosine tests.
     *
     * @param float[] $v
     *
     * @return float[]
     */
    private function normalise(array $v): array
    {
        $norm = sqrt(array_sum(array_map(fn($x) => $x * $x, $v)));
        if ($norm === 0.0) {
            return $v;
        }
        return array_map(fn($x) => $x / $norm, $v);
    }

    // ------------------------------------------------------------------
    // Basic insertion & retrieval
    // ------------------------------------------------------------------

    public function testEmptyIndexReturnsNoResults(): void
    {
        $index = $this->makeIndex();
        self::assertSame([], $index->search([0.1, 0.2, 0.3], 5));
    }

    public function testSingleDocumentReturnedForAnyQuery(): void
    {
        $index = $this->makeIndex();
        $doc   = new Document(id: 1, vector: [1.0, 0.0, 0.0]);
        $index->insert($doc);

        $results = $index->search([0.5, 0.5, 0.0], 5);

        self::assertCount(1, $results);
        self::assertSame($doc, $results[0]->document);
        self::assertSame(1, $results[0]->rank);
    }

    public function testNearestNeighbourIsClosest(): void
    {
        $index = $this->makeIndex(Distance::Euclidean);

        $target = new Document(id: 'target', vector: [1.0, 1.0]);
        $far    = new Document(id: 'far', vector: [10.0, 10.0]);
        $close  = new Document(id: 'close', vector: [1.1, 0.9]);

        $index->insert($target);
        $index->insert($far);
        $index->insert($close);

        $query   = [1.05, 0.95];
        $results = $index->search($query, 1);

        self::assertCount(1, $results);
        // 'close' and 'target' are equidistant or close; either is acceptable,
        // but 'far' should NOT be the top result.
        self::assertNotSame('far', $results[0]->document->id);
    }

    public function testTopKResultsAreRankedCorrectly(): void
    {
        $index = $this->makeIndex(Distance::Euclidean);

        // Insert 20 random documents, then query for 5-NN.
        $docs = [];
        for ($i = 0; $i < 20; $i++) {
            $docs[$i] = new Document(id: $i, vector: [$i * 0.1, 0.0]);
            $index->insert($docs[$i]);
        }

        $query   = [0.55, 0.0]; // closest to doc #5 or #6
        $results = $index->search($query, 5);

        self::assertCount(5, $results);

        // Verify ranks are consecutive 1-based integers.
        foreach ($results as $rank => $sr) {
            self::assertSame($rank + 1, $sr->rank);
        }

        // Verify scores are descending.
        for ($i = 1; $i < count($results); $i++) {
            self::assertGreaterThanOrEqual($results[$i]->score, $results[$i - 1]->score);
        }
    }

    public function testKCannotExceedIndexSize(): void
    {
        $index = $this->makeIndex();
        for ($i = 0; $i < 3; $i++) {
            $index->insert(new Document(id: $i, vector: $this->randomVector()));
        }

        $results = $index->search($this->randomVector(), 100);
        self::assertCount(3, $results);
    }

    // ------------------------------------------------------------------
    // Distance metrics
    // ------------------------------------------------------------------

    public function testCosineDistanceFindsMostSimilar(): void
    {
        $index  = new Index(new Config(M: 8, efConstruction: 50, efSearch: 20, distance: Distance::Cosine));
        $query  = $this->normalise([1.0, 0.0, 0.0]);
        $match  = $this->normalise([0.99, 0.01, 0.01]);
        $noMatch = $this->normalise([0.0, 1.0, 0.0]);

        $index->insert(new Document(id: 'match', vector: $match));
        $index->insert(new Document(id: 'nomatch', vector: $noMatch));

        $results = $index->search($query, 1);
        self::assertSame('match', $results[0]->document->id);
    }

    public function testDotProductDistanceFindsMostSimilar(): void
    {
        $index = new Index(new Config(M: 8, efConstruction: 50, efSearch: 20, distance: Distance::DotProduct));

        $high = new Document(id: 'high', vector: [10.0, 10.0]);
        $low  = new Document(id: 'low', vector: [0.1,  0.1]);

        $index->insert($high);
        $index->insert($low);

        $results = $index->search([1.0, 1.0], 1);
        self::assertSame('high', $results[0]->document->id);
    }

    public function testManhattanDistanceFindsNearest(): void
    {
        $index = new Index(new Config(M: 8, efConstruction: 50, efSearch: 20, distance: Distance::Manhattan));

        $index->insert(new Document(id: 'near', vector: [1.0, 1.0]));
        $index->insert(new Document(id: 'far', vector: [9.0, 9.0]));

        // L1 distance from [1.2, 0.8]: near = 0.4, far = 16.0.
        $results = $index->search([1.2, 0.8], 1);
        self::assertSame('near', $results[0]->document->id);
    }

    // ------------------------------------------------------------------
    // ef / M effects
    // ------------------------------------------------------------------

    public function testHigherEfSearchGivesAtLeastEqualRecall(): void
    {
        mt_srand(7);
        $dim = 32;
        $n   = 300;
        $k   = 10;
        $index = new Index(new Config(M: 8, efConstruction: 100, efSearch: 50, distance: Distance::Euclidean));

        $vectors = [];
        for ($i = 0; $i < $n; $i++) {
            $v = $this->randomVector($dim);
            $vectors[$i] = $v;
            $index->insert(new Document(id: $i, vector: $v));
        }

        $query = $this->randomVector($dim);

        $dists = [];
        foreach ($vectors as $id => $v) {
            $dists[$id] = sqrt(array_sum(array_map(fn($a, $b) => ($a - $b) ** 2, $query, $v)));
        }
        asort($dists);
        $groundTruth = array_slice(array_keys($dists), 0, $k);

        $recallAt = function (int $ef) use ($index, $query, $k, $groundTruth): int {
            $ids = array_map(fn($sr) => $sr->document->id, $index->search($query, $k, $ef));
            return count(array_intersect($ids, $groundTruth));
        };

        $low  = $recallAt(10);   // ef == k
        $high = $recallAt(200);

        self::assertGreaterThanOrEqual(
            $low,
            $high,
            sprintf('Higher ef should not reduce recall: low=%d high=%d', $low, $high),
        );
    }

    public function testLowEfStillReturnsK(): void
    {
        mt_srand(7);
        $index = new Index(new Config(M: 8, efConstruction: 100, efSearch: 50, distance: Distance::Euclidean));
        for ($i = 0; $i < 50; $i++) {
            $index->insert(new Document(id: $i, vector: $this->randomVector(16)));
        }

        $results = $index->search($this->randomVector(16), 10, 10); // ef == k
        self::assertCount(10, $results);
    }

    public function testLayerZeroDegreeRespectsMaxConnections(): void
    {
        mt_srand(7);
        $config = new Config(M: 8, efConstruction: 100, efSearch: 50, distance: Distance::Euclidean);
        $index  = new Index($config);
        for ($i = 0; $i < 100; $i++) {
            $index->insert(new Document(id: $i, vector: $this->randomVector(16)));
        }

        $state = $index->exportState();
        foreach ($state['nodes'] as $node) {
            if (isset($node['connections'][0])) {
                self::assertLessThanOrEqual($config->M0, count($node['connections'][0]));
            }
        }
    }

    // ------------------------------------------------------------------
    // Dimension validation
    // ------------------------------------------------------------------

    public function testInsertWrongDimensionThrows(): void
    {
        $index = $this->makeIndex();
        $index->insert(new Document(id: 1, vector: [1.0, 2.0, 3.0]));

        $this->expectException(DimensionMismatchException::class);
        $index->insert(new Document(id: 2, vector: [1.0, 2.0]));
    }

    public function testSearchWrongDimensionThrows(): void
    {
        $index = $this->makeIndex();
        $index->insert(new Document(id: 1, vector: [1.0, 2.0, 3.0]));

        $this->expectException(DimensionMismatchException::class);
        $index->search([1.0, 2.0], 1);
    }

    // ------------------------------------------------------------------
    // Recall smoke-test (probabilistic)
    // ------------------------------------------------------------------

    public function testRecallIsHighOnSyntheticData(): void
    {
        mt_srand(42);

        $dim   = 32;
        $n     = 200;
        $k     = 10;
        $index = new Index(new Config(M: 16, efConstruction: 200, efSearch: 50, distance: Distance::Euclidean));

        $vectors = [];
        for ($i = 0; $i < $n; $i++) {
            $v        = $this->randomVector($dim);
            $vectors[$i] = $v;
            $index->insert(new Document(id: $i, vector: $v));
        }

        $query       = $this->randomVector($dim);
        $hnswResults = $index->search($query, $k);

        // Brute-force k-NN for ground truth.
        $dists = [];
        foreach ($vectors as $id => $v) {
            $dists[$id] = sqrt(array_sum(array_map(
                fn($a, $b) => ($a - $b) ** 2,
                $query,
                $v,
            )));
        }
        asort($dists);
        $groundTruth = array_slice(array_keys($dists), 0, $k);

        $hnswIds  = array_map(fn($sr) => $sr->document->id, $hnswResults);
        $recalled = count(array_intersect($hnswIds, $groundTruth));

        // Expect at least 70 % recall (typically >95 % with these settings).
        self::assertGreaterThanOrEqual(
            (int) ceil($k * 0.7),
            $recalled,
            sprintf('HNSW recall too low: %d/%d', $recalled, $k),
        );
    }
}
