<?php

declare(strict_types=1);

namespace PHPVector\Benchmark;

/**
 * Exact nearest-neighbour search via brute-force cosine similarity.
 * Used to compute ground-truth results for recall measurement.
 */
final class BruteForce
{
    /** @var array<int, float[]> id → unit vector */
    private array $vectors = [];

    /** @var array<int, float> id → precomputed L2 norm */
    private array $norms = [];

    /** @param float[] $vector */
    public function add(int $id, array $vector): void
    {
        $this->vectors[$id] = $vector;

        $norm = 0.0;
        foreach ($vector as $v) {
            $norm += $v * $v;
        }
        $this->norms[$id] = sqrt($norm);
    }

    /**
     * Return the IDs of the k most similar vectors, sorted by cosine similarity
     * descending (best match first). This mirrors HNSW's cosine distance ranking.
     *
     * @param float[] $query
     *
     * @return int[]
     */
    public function search(array $query, int $k): array
    {
        $queryNorm = 0.0;
        foreach ($query as $v) {
            $queryNorm += $v * $v;
        }
        $queryNorm = sqrt($queryNorm);

        $scores = [];
        $dim    = count($query);

        foreach ($this->vectors as $id => $vec) {
            $dot = 0.0;
            for ($i = 0; $i < $dim; $i++) {
                $dot += $query[$i] * $vec[$i];
            }
            $denom      = $queryNorm * $this->norms[$id];
            $scores[$id] = $denom > 0.0 ? $dot / $denom : 0.0;
        }

        arsort($scores);

        return array_slice(array_keys($scores), 0, $k);
    }
}
