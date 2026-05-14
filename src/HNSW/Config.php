<?php

declare(strict_types=1);

namespace PHPVector\HNSW;

use PHPVector\Distance;

/**
 * Immutable configuration for an HNSW index.
 *
 * Sensible defaults follow the values recommended in the original paper:
 * "Efficient and robust approximate nearest neighbor search using
 *  Hierarchical Navigable Small World graphs" – Malkov & Yashunin, 2018.
 */
final class Config
{
    /**
     * Maximum number of bi-directional connections per node per layer (M).
     * Higher M → better recall, higher memory and build time.
     * Typical range: 8–64. Default: 16.
     */
    public readonly int $M;

    /**
     * Maximum connections at layer 0 (M₀).
     * The paper recommends 2 × M for the base layer.
     */
    public readonly int $M0;

    /**
     * Level multiplier: mL = 1 / ln(M).
     * Controls the probability of a node being promoted to higher layers.
     * Derived automatically from M; can be overridden for tuning.
     */
    public readonly float $mL;

    /**
     * Dynamic candidate list size during construction (ef_construction).
     * Higher value → better recall and graph quality, slower insertions.
     * Must be ≥ M. Default: 200.
     */
    public readonly int $efConstruction;

    /**
     * Default candidate list size during search (ef_search / ef).
     * Must be ≥ k (the number of results requested).
     * Higher value → better recall, slower queries.
     * Default: 50.
     */
    public readonly int $efSearch;

    /**
     * Whether to use the neighbour-selection heuristic (Algorithm 4 in the paper).
     * Produces better-connected graphs, especially in clustered data.
     * Slight extra cost on inserts. Default: true.
     */
    public readonly bool $useHeuristic;

    /**
     * Whether the heuristic should extend candidate set from neighbour-of-neighbours.
     * Only meaningful when $useHeuristic = true.
     */
    public readonly bool $extendCandidates;

    /**
     * Whether the heuristic should backfill with pruned candidates to reach M.
     */
    public readonly bool $keepPrunedConnections;

    public function __construct(
        int $M = 16,
        ?int $M0 = null,
        ?float $mL = null,
        int $efConstruction = 200,
        int $efSearch = 50,
        public readonly Distance $distance = Distance::Cosine,
        bool $useHeuristic = true,
        bool $extendCandidates = false,
        bool $keepPrunedConnections = true,
    ) {
        if ($M < 2) {
            throw new \InvalidArgumentException('M must be at least 2.');
        }
        $resolvedM0 = $M0 ?? ($M * 2);

        if ($efConstruction < $resolvedM0) {
            throw new \InvalidArgumentException("efConstruction must be ≥ M0 ({$resolvedM0}).");
        }
        $this->M = $M;
        $this->M0 = $resolvedM0;
        $this->mL = $mL ?? (1.0 / log($M));
        $this->efConstruction = $efConstruction;
        $this->efSearch = $efSearch;
        $this->useHeuristic = $useHeuristic;
        $this->extendCandidates = $extendCandidates;
        $this->keepPrunedConnections = $keepPrunedConnections;
    }
}
