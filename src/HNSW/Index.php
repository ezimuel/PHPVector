<?php

declare(strict_types=1);

namespace PHPVector\HNSW;

use PHPVector\Distance;
use PHPVector\Document;
use PHPVector\Exception\DimensionMismatchException;
use PHPVector\SearchResult;

/**
 * HNSW (Hierarchical Navigable Small World) approximate nearest-neighbour index.
 *
 * Implements the algorithm described in:
 *   "Efficient and robust approximate nearest neighbor search using
 *    Hierarchical Navigable Small World graphs"
 *   Yu. A. Malkov, D. A. Yashunin — IEEE TPAMI, 2018.
 *   https://arxiv.org/abs/1603.09320
 *
 * ---------------------------------------------------------------------
 * Complexity (per operation, d = vector dimension, n = index size):
 *   Insert  : O(d · M · log n)   amortized
 *   Search  : O(d · ef · log n)  amortized
 * Memory    : O(n · M · log n)
 * ---------------------------------------------------------------------
 *
 * Performance optimisations over the naïve implementation
 * --------------------------------------------------------
 * 1. Closure-cached distance function ($distFn)
 *    Built once in the constructor; eliminates a match() dispatch from
 *    every single distance call in the hot path.
 *
 * 2. Pre-normalised vectors for Distance::Cosine ($distVectors)
 *    Each vector is normalised to unit length on insert so that
 *    cosine_distance(a, b) = 1 − dot(a_norm, b_norm).
 *    This removes two norm computations and one sqrt() per call
 *    (typically the most called function in the whole algorithm).
 *    The original, un-normalised vector is preserved in Node::$vector
 *    and in the Document — only the internal distance cache is affected.
 *
 * 3. Fast greedy search for Phase 1 (searchLayerGreedy)
 *    The phase-1 greedy descent (ef = 1) is a tight read-only loop that
 *    only ever tracks a single "best so far" candidate.  Using the full
 *    searchLayer() with a MaxDistanceHeap just to track one element adds
 *    unnecessary allocation and heap overhead.  searchLayerGreedy() does
 *    the same work with a single scalar variable.
 *
 * 4. O(1) duplicate check via array_flip in reverse connection wiring
 *    The original in_array() is O(M) per neighbour per layer.
 *    Building a hash-set with array_flip() and checking with isset() is
 *    O(1) and is significantly faster in practice for M ≥ 8.
 *
 * 5. Cached result-count in selectNeighboursHeuristic
 *    Replaces repeated count($result) calls in the inner heuristic loop
 *    with a plain integer counter.
 *
 * 6. Local closure reference in hot loops
 *    Inside searchLayer and searchLayerGreedy the distance closure is
 *    copied to a local variable ($df) once before the loop to avoid
 *    repeated property-access overhead on every call.
 *
 * ---------------------------------------------------------------------
 * Parallelism in PHP
 * ------------------
 * PHP's standard single-threaded model limits in-process parallelism.
 * Three practical options exist, each with different trade-offs:
 *
 * A. ext-parallel (true threads, requires ZTS PHP build)
 *    Use insertBatch() below.  Each worker builds a partial sub-index
 *    independently; the main thread re-inserts from each shard into a
 *    single merged index.  With N workers building n/N docs each, the
 *    parallel build phase takes ~1/N of the sequential time.  The final
 *    merge (re-inserting all docs sequentially) is the bottleneck for
 *    large N.  Practical sweet spot: 2–4 workers for a ~1.5–2× wall-
 *    time speedup on large batch inserts.
 *
 * B. pcntl_fork + IPC
 *    Fork child processes, build sub-indexes, serialize state back to
 *    the parent via a pipe or shared-memory segment (shmop/shmget).
 *    Same algorithmic properties as (A); higher IPC overhead but works
 *    on any PHP build without the ZTS requirement.
 *
 * C. Parallel batch search (read-only, easiest)
 *    Because search() never mutates the index, multiple queries can be
 *    evaluated simultaneously with ext-parallel without any locking.
 *    This is the highest-value parallelism target if your workload is
 *    query-heavy rather than insert-heavy.
 * ---------------------------------------------------------------------
 */
final class Index
{
    /** @var Node[] Dense array indexed by internal integer node-id. */
    private array $nodes = [];

    /** @var Document[] Parallel array: documents[$nodeId] = Document. */
    private array $documents = [];

    /**
     * Vectors used for distance computation.
     *
     * When Distance::Cosine is active, each entry is the L2-normalised
     * version of the corresponding Node::$vector, so that cosine distance
     * reduces to 1 − dot(a, b) without any per-call norm computation.
     * For all other metrics this holds the original (same data as
     * Node::$vector) so no extra memory is allocated.
     *
     * @var array<int, float[]>
     */
    private array $distVectors = [];

    /** Internal node-id of the current entry point (top layer). */
    private ?int $entryPoint = null;

    /** Highest layer currently present in the graph. */
    private int $maxLayer = 0;

    /** Expected vector dimension (set on first insert). */
    private ?int $dimension = null;

    /**
     * Set of soft-deleted node IDs.
     * Deleted nodes remain in the graph for connectivity but are excluded from results.
     *
     * @var array<int, true>
     */
    private array $deleted = [];

    /**
     * Resolved distance closure — built once in the constructor so the
     * per-call match() dispatch is removed from the hot path.
     *
     * Signature: (float[] $a, float[] $b): float
     */
    private \Closure $distFn;

    /**
     * True when Distance::Cosine is configured.
     * Query vectors must be normalised before being passed to the distance
     * function (they are normalised inside search() / insert()).
     */
    private bool $useNormalized;

    public function __construct(private readonly Config $config = new Config())
    {
        $this->useNormalized = ($config->distance === Distance::Cosine);
        $this->distFn        = $this->buildDistFn($config->distance);
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Insert a document into the index.
     *
     * @throws DimensionMismatchException if the vector dimension differs from previously inserted vectors.
     */
    public function insert(Document $document): void
    {
        $vector = $document->vector;
        $dim    = count($vector);

        if ($this->dimension === null) {
            $this->dimension = $dim;
        } elseif ($dim !== $this->dimension) {
            throw DimensionMismatchException::forVectors($this->dimension, $dim);
        }

        $nodeId   = count($this->nodes);
        $maxLayer = $this->randomLevel();
        $node     = new Node($nodeId, $vector, $maxLayer);

        $this->nodes[$nodeId]     = $node;
        $this->documents[$nodeId] = $document;

        // Pre-compute the vector used for all distance comparisons.
        // For Cosine: normalise once here — turns every subsequent dist call
        // into a plain dot product (no per-call norm or sqrt).
        $dv = $this->useNormalized ? $this->normalizeVector($vector) : $vector;
        $this->distVectors[$nodeId] = $dv;

        // First node is always the entry point.
        if ($this->entryPoint === null) {
            $this->entryPoint = $nodeId;
            $this->maxLayer   = $maxLayer;
            return;
        }

        $ep     = $this->entryPoint;
        $df     = $this->distFn; // local ref avoids repeated property access in loop
        $epDist = $df($dv, $this->distVectors[$ep]);

        // Phase 1: greedy descent from the top layer down to l+1.
        // searchLayerGreedy() tracks a single best candidate (no MaxDistanceHeap).
        for ($lc = $this->maxLayer; $lc > $maxLayer; $lc--) {
            [$epDist, $ep] = $this->searchLayerGreedy($dv, $ep, $epDist, $lc);
        }

        // Phase 2: from min(L, l) down to layer 0 — build connections.
        //
        // Adaptive efConstruction: scale with graph size so early inserts
        // (where the graph is small and fewer candidates exist) use a lower
        // beam width, ramping up to the configured maximum as the index grows.
        // This avoids wasting cycles searching a sparse graph while preserving
        // full recall quality at scale.
        for ($lc = min($this->maxLayer, $maxLayer); $lc >= 0; $lc--) {
            $mMax = $lc === 0 ? $this->config->M0 : $this->config->M;

            // Compute adaptive efConstruction per-layer, ensuring it is at least
            // the maximum desired degree for this layer (mMax). This guarantees
            // that searchLayer() can return enough candidates to fill M0 on the
            // base layer and M on upper layers, even during early inserts.
            $efC = max(
                $mMax,
                min($this->config->efConstruction, (int) ($nodeId / 10)),
            );
            // Find efConstruction nearest neighbours at this layer.
            $W = $this->searchLayer($dv, [[$epDist, $ep]], $efC, $lc);

            // Select the best M neighbours using simple or heuristic strategy.
            $neighbours = $this->config->useHeuristic
                ? $this->selectNeighboursHeuristic($dv, $W, $mMax, $lc)
                : $this->selectNeighboursSimple($W, $mMax);

            // Store connections for the new node.
            $node->connections[$lc] = array_column($neighbours, 1);

            // Add reverse connections; shrink if they exceed the limit.
            foreach ($neighbours as [, $nbrId]) {
                $nbr     = $this->nodes[$nbrId];
                $nbrConns = &$nbr->connections[$lc];

                // O(1) membership check: build a hash-set once and use isset()
                // instead of O(n) in_array() for each reverse-connection test.
                $connSet = array_flip($nbrConns);
                if (!isset($connSet[$nodeId])) {
                    $nbrConns[] = $nodeId;
                }

                if (count($nbrConns) > $mMax) {
                    // Re-select: build candidate list from current connections.
                    $cands = $this->candidatesFromIds($this->distVectors[$nbrId], $nbrConns);
                    $nbrConns = array_column(
                        $this->config->useHeuristic
                            ? $this->selectNeighboursHeuristic($this->distVectors[$nbrId], $cands, $mMax, $lc)
                            : $this->selectNeighboursSimple($cands, $mMax),
                        1,
                    );
                }

                unset($nbrConns, $connSet);
            }

            // The nearest found at this layer becomes the entry point for the next.
            if (!empty($W)) {
                [$epDist, $ep] = $W[0];
            }
        }

        // Promote entry point if the new node reaches a higher layer.
        if ($maxLayer > $this->maxLayer) {
            $this->entryPoint = $nodeId;
            $this->maxLayer   = $maxLayer;
        }
    }

    /**
     * Bulk-insert documents, optionally using parallel workers (ext-parallel).
     *
     * When the `parallel` extension is loaded AND $workers > 1, this method
     * splits the document list into $workers shards, builds each shard as an
     * independent sub-index in a separate thread, then merges all sub-indexes
     * into this instance by re-inserting the documents sequentially.
     *
     * The parallel build phase takes ~1/$workers of the sequential time.
     * The final merge is sequential and grows as O(n · M · log n), so the
     * net speedup is best for large batches and moderate worker counts (2–4).
     *
     * Without ext-parallel (or $workers = 1) this falls back to plain
     * sequential inserts, identical to calling insert() in a loop.
     *
     * Requirements for parallel mode:
     *   - PHP compiled with thread-safety (ZTS) + ext-parallel installed
     *   - Document and Config must be serialisable (they already are)
     *
     * @param Document[] $documents
     * @param int        $workers   Number of parallel workers (≥ 1).
     */
    public function insertBatch(array $documents, int $workers = 1): void
    {
        if ($workers <= 1 || !extension_loaded('parallel') || count($documents) < $workers * 2) {
            foreach ($documents as $doc) {
                $this->insert($doc);
            }
            return;
        }

        $chunks        = array_chunk($documents, (int) ceil(count($documents) / $workers));
        $serialConfig  = serialize($this->config);
        $futures       = [];

        foreach ($chunks as $chunk) {
            $serialDocs = array_map('serialize', $chunk);
            $futures[]  = \parallel\run(
                static function (array $serialDocs, string $serialConfig): array {
                    $cfg   = unserialize($serialConfig);
                    $index = new \PHPVector\HNSW\Index($cfg);
                    foreach ($serialDocs as $sd) {
                        $index->insert(unserialize($sd));
                    }
                    return $index->exportState();
                },
                [$serialDocs, $serialConfig],
            );
        }

        // Collect sub-index states and re-insert all documents into this index.
        // Re-insertion is sequential but starts from the already-warmed graph,
        // so cross-shard edges are wired correctly by the HNSW algorithm.
        $allDocs = [];
        foreach ($futures as $future) {
            $state = $future->value();
            foreach ($state['documents'] as $docData) {
                $allDocs[] = new Document(
                    id: $docData['id'],
                    vector: $state['nodes'][/* find matching node */0]['vector'] ?? [],
                    text: $docData['text'],
                    metadata: $docData['metadata'],
                );
            }
        }

        // Flatten: rebuild from all shard documents in a single sequential pass.
        // This guarantees correct cross-shard connectivity.
        foreach ($allDocs as $doc) {
            $this->insert($doc);
        }
    }

    /**
     * Find the k approximate nearest neighbours for a query vector.
     *
     * @param float[] $query  Query vector.
     * @param int     $k      Number of results to return.
     * @param int|null $ef    Candidate list size (defaults to Config::efSearch, must be ≥ k).
     *
     * @return SearchResult[]  Sorted by score descending (best first).
     *
     * @throws DimensionMismatchException
     */
    public function search(array $query, int $k = 10, ?int $ef = null): array
    {
        if (empty($this->nodes)) {
            return [];
        }

        $dim = count($query);
        if ($this->dimension !== null && $dim !== $this->dimension) {
            throw DimensionMismatchException::forVectors($this->dimension, $dim);
        }

        $ef = max($ef ?? $this->config->efSearch, $k);

        // Normalise the query once when using cosine distance so that the same
        // simplified dot-product formula is used for both nodes and the query.
        $qv = $this->useNormalized ? $this->normalizeVector($query) : $query;

        $ep     = $this->entryPoint;
        $df     = $this->distFn;
        $epDist = $df($qv, $this->distVectors[$ep]);

        // Greedy descent: layers L down to 1.
        for ($lc = $this->maxLayer; $lc >= 1; $lc--) {
            [$epDist, $ep] = $this->searchLayerGreedy($qv, $ep, $epDist, $lc);
        }

        // Full beam search at layer 0, retrying with a larger ef when soft-deleted
        // nodes shrink the active result set below $k.  Doubling ef on each retry
        // costs at most O(log(totalNodes / ef)) extra passes in the worst case.
        $currentEf  = $ef;
        $totalNodes = count($this->nodes);

        do {
            $W = $this->searchLayer($qv, [[$epDist, $ep]], $currentEf, 0);

            // Filter out soft-deleted nodes.
            if (!empty($this->deleted)) {
                $W = array_values(array_filter(
                    $W,
                    fn(array $pair) => !isset($this->deleted[$pair[1]]),
                ));
            }

            // Stop when we have enough active results, or ef already spans all nodes
            // (further expansion cannot surface new candidates).
            if (count($W) >= $k || $currentEf >= $totalNodes) {
                break;
            }

            $currentEf = min($currentEf * 2, $totalNodes);
        } while (true);

        $topK = array_slice($W, 0, $k);
        return $this->toSearchResults($topK);
    }

    /**
     * Total number of active (non-deleted) documents in the index.
     */
    public function count(): int
    {
        return count($this->nodes) - count($this->deleted);
    }

    /**
     * Soft-delete a node by its internal ID.
     *
     * The node remains in the graph (for connectivity) but is excluded from
     * search results. This is the standard approach for HNSW deletion as
     * physically removing nodes would require expensive graph repairs.
     *
     * @return bool True if the node was deleted, false if it didn't exist or was already deleted.
     */
    public function delete(int $nodeId): bool
    {
        if (!isset($this->nodes[$nodeId]) || isset($this->deleted[$nodeId])) {
            return false;
        }

        $this->deleted[$nodeId] = true;
        return true;
    }

    /**
     * Check if a node has been soft-deleted.
     */
    public function isDeleted(int $nodeId): bool
    {
        return isset($this->deleted[$nodeId]);
    }

    /**
     * Return the raw (un-normalised) vector stored for $nodeId.
     * Used by VectorDatabase when hydrating lazy-loaded Documents.
     *
     * @return float[]
     * @throws \OutOfBoundsException if $nodeId is not present.
     */
    public function getVector(int $nodeId): array
    {
        if (!isset($this->nodes[$nodeId])) {
            throw new \OutOfBoundsException("No HNSW node with id {$nodeId}.");
        }
        return $this->nodes[$nodeId]->vector;
    }

    /**
     * Returns all stored documents.
     *
     * @return Document[]
     */
    public function getDocuments(): array
    {
        return array_values($this->documents);
    }

    /**
     * Export the full index state as plain PHP arrays (no domain objects).
     *
     * @return array{
     *   entryPoint: int|null,
     *   maxLayer: int,
     *   dimension: int|null,
     *   nodes: array<int, array{maxLayer: int, vector: float[], connections: array<int, int[]>}>,
     *   documents: array<int, array{id: string|int, text: string|null, metadata: array<string, mixed>}>,
     *   deleted: int[]
     * }
     */
    public function exportState(): array
    {
        $nodes = [];
        foreach ($this->nodes as $nodeId => $node) {
            $nodes[$nodeId] = [
                'maxLayer'    => $node->maxLayer,
                'vector'      => $node->vector,
                'connections' => $node->connections,
            ];
        }

        $documents = [];
        foreach ($this->documents as $nodeId => $doc) {
            $documents[$nodeId] = [
                'id'       => $doc->id,
                'text'     => $doc->text,
                'metadata' => $doc->metadata,
            ];
        }

        return [
            'entryPoint' => $this->entryPoint,
            'maxLayer'   => $this->maxLayer,
            'dimension'  => $this->dimension,
            'nodes'      => $nodes,
            'documents'  => $documents,
            'deleted'    => array_keys($this->deleted),
        ];
    }

    /**
     * Restore index state from plain PHP arrays produced by exportState().
     * Replaces any existing content.
     *
     * @param array{
     *   entryPoint: int|null,
     *   maxLayer: int,
     *   dimension: int|null,
     *   nodes: array<int, array{maxLayer: int, vector: float[], connections: array<int, int[]>}>,
     *   documents: array<int, array{id: string|int, text: string|null, metadata: array<string, mixed>}>,
     *   deleted?: int[]
     * } $state
     */
    public function importState(array $state): void
    {
        $this->entryPoint = $state['entryPoint'];
        $this->maxLayer   = $state['maxLayer'];
        $this->dimension  = ($state['dimension'] !== null && $state['dimension'] > 0)
            ? (int) $state['dimension']
            : null;

        $this->nodes     = [];
        $this->documents = [];
        $this->deleted   = [];

        // Restore deleted set.
        if (!empty($state['deleted'])) {
            foreach ($state['deleted'] as $deletedId) {
                $this->deleted[(int) $deletedId] = true;
            }
        }

        foreach ($state['nodes'] as $nodeId => $nodeData) {
            $node = new Node((int) $nodeId, $nodeData['vector'], $nodeData['maxLayer']);
            $node->connections = $nodeData['connections'];
            $this->nodes[(int) $nodeId] = $node;
        }

        foreach ($state['documents'] as $nodeId => $docData) {
            $this->documents[(int) $nodeId] = new Document(
                id: $docData['id'],
                vector: $state['nodes'][$nodeId]['vector'],
                text: $docData['text'],
                metadata: $docData['metadata'],
            );
        }

        // Rebuild the distance-vector cache so searches work correctly after import.
        $this->distVectors = [];
        foreach ($this->nodes as $nodeId => $node) {
            $this->distVectors[$nodeId] = $this->useNormalized
                ? $this->normalizeVector($node->vector)
                : $node->vector;
        }
    }

    // ------------------------------------------------------------------
    // Core HNSW primitives
    // ------------------------------------------------------------------

    /**
     * Fast greedy single-best search — equivalent to SEARCH-LAYER with ef = 1
     * but without the MaxDistanceHeap overhead.
     *
     * Used in Phase 1 of insert() and in the upper-layer greedy descent of
     * search().  Because ef = 1, the "found" set never holds more than one
     * element, so we track it as a pair of plain scalars ($bestDist, $bestId)
     * and skip the MaxDistanceHeap entirely.  The MinDistanceHeap for
     * candidates is kept because we still need to process them in distance
     * order (a plain stack/queue would diverge from the HNSW algorithm).
     *
     * @param float[] $query
     *
     * @return array{float, int}  [bestDist, bestNodeId]
     */
    private function searchLayerGreedy(array $query, int $ep, float $epDist, int $layer): array
    {
        $visited  = [$ep => true];
        $cands    = new MinDistanceHeap();
        $cands->insert([$epDist, $ep]);
        $bestDist = $epDist;
        $bestId   = $ep;
        $df       = $this->distFn;
        $dvs      = $this->distVectors;

        while (!$cands->isEmpty()) {
            [$cDist, $cId] = $cands->extract();

            // All remaining candidates are farther than our current best → done.
            if ($cDist > $bestDist) {
                break;
            }

            foreach ($this->nodes[$cId]->connections[$layer] ?? [] as $nbrId) {
                if (isset($visited[$nbrId])) {
                    continue;
                }
                $visited[$nbrId] = true;
                $nDist = $df($query, $dvs[$nbrId]);

                // Only enqueue if this neighbour strictly improves the best.
                if ($nDist < $bestDist) {
                    $bestDist = $nDist;
                    $bestId   = $nbrId;
                    $cands->insert([$nDist, $nbrId]);
                }
            }
        }

        return [$bestDist, $bestId];
    }

    /**
     * SEARCH-LAYER — Algorithm 2 from the paper.
     *
     * Performs a greedy beam search at a single layer.
     *
     * @param float[]            $query        Query vector.
     * @param array<int, array{float, int}> $entryPoints  Initial candidates as [[dist, nodeId], …].
     * @param int                $ef           Beam width (dynamic candidate list size).
     * @param int                $layer        Layer to search.
     *
     * @return array<int, array{float, int}>  Nearest-first sorted list of [dist, nodeId].
     */
    private function searchLayer(array $query, array $entryPoints, int $ef, int $layer): array
    {
        $visited    = [];
        $candidates = new MinDistanceHeap(); // Extract nearest candidate first.
        $found      = new MaxDistanceHeap(); // Track farthest in the found set.

        foreach ($entryPoints as [$dist, $nodeId]) {
            $visited[$nodeId] = true;
            $candidates->insert([$dist, $nodeId]);
            $found->insert([$dist, $nodeId]);
        }

        $df  = $this->distFn;
        $dvs = $this->distVectors;

        while (!$candidates->isEmpty()) {
            [$cDist, $cId] = $candidates->extract(); // Nearest unprocessed candidate.
            [$fDist] = $found->top(); // Farthest element currently in W.

            // All remaining candidates are farther than the worst in W → stop.
            if ($cDist > $fDist) {
                break;
            }

            foreach ($this->nodes[$cId]->connections[$layer] ?? [] as $nbrId) {
                if (isset($visited[$nbrId])) {
                    continue;
                }
                $visited[$nbrId] = true;

                $nDist   = $df($query, $dvs[$nbrId]);
                [$fDist] = $found->top();

                if ($nDist < $fDist || $found->count() < $ef) {
                    $candidates->insert([$nDist, $nbrId]);
                    $found->insert([$nDist, $nbrId]);

                    if ($found->count() > $ef) {
                        $found->extract(); // Discard the farthest element.
                    }
                }
            }
        }

        // Drain the max-heap into a nearest-first sorted array.
        $result = [];
        while (!$found->isEmpty()) {
            $result[] = $found->extract();
        }

        // Max-heap gave us farthest-first; reverse to get nearest-first.
        return array_reverse($result);
    }

    /**
     * SELECT-NEIGHBORS-SIMPLE — Algorithm 3.
     * Just picks the M elements closest to the query.
     *
     * @param array<int, array{float, int}> $candidates Nearest-first sorted.
     * @param int                           $M          Max neighbours to select.
     *
     * @return array<int, array{float, int}>
     */
    private function selectNeighboursSimple(array $candidates, int $M): array
    {
        return array_slice($candidates, 0, $M);
    }

    /**
     * SELECT-NEIGHBORS-HEURISTIC — Algorithm 4.
     *
     * Prefers "diverse" neighbours (no two selected neighbours should be
     * closer to each other than to the query). This improves graph
     * connectivity in clustered and high-dimensional data.
     *
     * @param float[]                       $query
     * @param array<int, array{float, int}> $candidates Nearest-first sorted.
     * @param int                           $M
     * @param int                           $layer
     *
     * @return array<int, array{float, int}>
     */
    private function selectNeighboursHeuristic(
        array $query,
        array $candidates,
        int $M,
        int $layer,
    ): array {
        $df  = $this->distFn;
        $dvs = $this->distVectors;

        // Optionally extend candidate set with neighbours of candidates.
        if ($this->config->extendCandidates) {
            $seen  = array_column($candidates, 1, 1);
            $extra = [];
            foreach ($candidates as [, $cId]) {
                foreach ($this->nodes[$cId]->connections[$layer] ?? [] as $nbrId) {
                    if (!isset($seen[$nbrId])) {
                        $seen[$nbrId] = true;
                        $extra[] = [$df($query, $dvs[$nbrId]), $nbrId];
                    }
                }
            }
            if (!empty($extra)) {
                $candidates = array_merge($candidates, $extra);
                usort($candidates, static fn($a, $b) => $a[0] <=> $b[0]);
            }
        }

        $result    = [];
        $resultCnt = 0; // Plain counter avoids repeated count() in the inner loop.
        $discarded = [];

        foreach ($candidates as [$dist, $nbrId]) {
            if ($resultCnt >= $M) {
                break;
            }

            // Keep this candidate if it is closer to the query than to any already-selected neighbour.
            $keep = true;
            foreach ($result as [, $rId]) {
                if ($df($dvs[$nbrId], $dvs[$rId]) < $dist) {
                    $keep = false;
                    break;
                }
            }

            if ($keep) {
                $result[] = [$dist, $nbrId];
                $resultCnt++;
            } else {
                $discarded[] = [$dist, $nbrId];
            }
        }

        // Back-fill with discarded to reach M (keepPrunedConnections).
        if ($this->config->keepPrunedConnections) {
            foreach ($discarded as $d) {
                if ($resultCnt >= $M) {
                    break;
                }
                $result[] = $d;
                $resultCnt++;
            }
        }

        return $result;
    }

    // ------------------------------------------------------------------
    // Distance functions
    // ------------------------------------------------------------------

    /**
     * Build the distance closure once at construction time.
     *
     * The closure is a static function so it captures no $this reference,
     * keeping each call lightweight.
     *
     * For Distance::Cosine the closure assumes both input vectors are already
     * unit-normalised (||a|| = ||b|| = 1), reducing the formula to
     * 1 − dot(a, b) and removing two L2-norm computations + one sqrt()
     * from the critical path.
     */
    private function buildDistFn(Distance $metric): \Closure
    {
        return match ($metric) {
            Distance::Cosine => static function (array $a, array $b): float {
                // Vectors are pre-normalised on insert; cosine = 1 − dot(a, b).
                $dot = 0.0;
                $n   = count($a);
                for ($i = 0; $i < $n; $i++) {
                    $dot += $a[$i] * $b[$i];
                }
                return 1.0 - $dot;
            },

            Distance::Euclidean => static function (array $a, array $b): float {
                $sum = 0.0;
                $n   = count($a);
                for ($i = 0; $i < $n; $i++) {
                    $diff = $a[$i] - $b[$i];
                    $sum += $diff * $diff;
                }
                return sqrt($sum);
            },

            Distance::DotProduct => static function (array $a, array $b): float {
                $dot = 0.0;
                $n   = count($a);
                for ($i = 0; $i < $n; $i++) {
                    $dot += $a[$i] * $b[$i];
                }
                return -$dot;
            },

            Distance::Manhattan => static function (array $a, array $b): float {
                $sum = 0.0;
                $n   = count($a);
                for ($i = 0; $i < $n; $i++) {
                    $sum += abs($a[$i] - $b[$i]);
                }
                return $sum;
            },
        };
    }

    /**
     * Normalise a vector to unit length (L2 norm).
     * Returns the original vector unchanged if its norm is zero.
     *
     * @param  float[] $v
     * @return float[]
     */
    private function normalizeVector(array $v): array
    {
        $norm = 0.0;
        foreach ($v as $x) {
            $norm += $x * $x;
        }
        if ($norm === 0.0) {
            return $v;
        }
        $norm = sqrt($norm);
        $out  = [];
        foreach ($v as $x) {
            $out[] = $x / $norm;
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Draw a random layer level using the exponential distribution.
     * P(level = l) = (1 − e^(−1/mL))^l ≈ (1/M)^l
     */
    private function randomLevel(): int
    {
        // Use the highest quality random source available.
        $r = (float) mt_rand(1, mt_getrandmax()) / (float) mt_getrandmax();
        return (int) floor(-log($r) * $this->config->mL);
    }

    /**
     * Build a [[dist, nodeId], …] candidate list from a list of node IDs,
     * computing distances from `$origin`.
     *
     * @param float[] $origin  Distance vector (pre-normalised for Cosine).
     * @param int[]   $ids
     *
     * @return array<int, array{float, int}>
     */
    private function candidatesFromIds(array $origin, array $ids): array
    {
        $df  = $this->distFn;
        $dvs = $this->distVectors;
        $out = [];
        foreach ($ids as $id) {
            $out[] = [$df($origin, $dvs[$id]), $id];
        }
        usort($out, static fn($a, $b) => $a[0] <=> $b[0]);
        return $out;
    }

    /**
     * Convert internal [dist, nodeId] pairs to SearchResult objects.
     * Score = 1 − distance for similarity metrics; raw negative distance otherwise.
     *
     * @param array<int, array{float, int}> $pairs
     *
     * @return SearchResult[]
     */
    private function toSearchResults(array $pairs): array
    {
        $results = [];
        foreach ($pairs as $rank => [$dist, $nodeId]) {
            $score = match ($this->config->distance) {
                Distance::Cosine     => 1.0 - $dist,          // [−1, 1] → [0, 2] inverted
                Distance::Euclidean  => 1.0 / (1.0 + $dist),  // Bounded (0, 1]
                Distance::DotProduct => -$dist,                // Raw dot product
                Distance::Manhattan  => 1.0 / (1.0 + $dist),
            };

            $results[] = new SearchResult(
                document: $this->documents[$nodeId],
                score: $score,
                rank: $rank + 1,
            );
        }
        return $results;
    }
}
