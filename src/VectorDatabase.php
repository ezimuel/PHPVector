<?php

declare(strict_types=1);

namespace PHPVector;

use PHPVector\BM25\Config as BM25Config;
use PHPVector\BM25\Index as BM25Index;
use PHPVector\BM25\TokenizerInterface;
use PHPVector\BM25\SimpleTokenizer;
use PHPVector\HNSW\Config as HNSWConfig;
use PHPVector\HNSW\Index as HNSWIndex;
use PHPVector\Metadata\MetadataFilter;
use PHPVector\Metadata\MetadataFilterEvaluator;
use PHPVector\Metadata\SortDirection;
use PHPVector\Persistence\DocumentStore;
use PHPVector\Persistence\IndexSerializer;

/**
 * High-level façade combining HNSW vector search with BM25 full-text search.
 *
 * Supports three retrieval modes:
 *  1. **Vector search** — approximate nearest-neighbour via HNSW.
 *  2. **Text search**   — BM25 ranked full-text search.
 *  3. **Hybrid search** — fuse both result sets with either
 *                         Reciprocal Rank Fusion (RRF) or a weighted linear combination.
 *
 * ### Persistence (folder-based)
 *
 * Pass a `$path` to the constructor to enable on-disk persistence.
 * The folder layout created by `save()` is:
 *
 * ```
 * {path}/
 *   meta.json      — distance code, dimension, nextId, docIdToNodeId
 *   hnsw.bin       — HNSW graph (nodes: vectors + connections)
 *   bm25.bin       — BM25 inverted index
 *   docs/
 *     0.bin        — one file per document (id, text, metadata)
 *     1.bin
 *     …
 * ```
 *
 * Document files are **lazy-loaded**: only the HNSW graph and BM25 index are
 * loaded into memory by `open()`; individual `docs/{n}.bin` files are read on
 * demand when search results are hydrated.
 *
 * Individual document files are written **asynchronously** (via `pcntl_fork`)
 * on each `addDocument()` call when the extension is available.  `save()`
 * waits for all pending writes before flushing the index files.
 *
 * Quick start
 * -----------
 * ```php
 * // In-memory (no persistence):
 * $db = new VectorDatabase();
 * $db->addDocument(new Document(id: 1, vector: [0.1, 0.9, ...], text: 'PHP vector database'));
 *
 * // With persistence:
 * $db = new VectorDatabase(path: '/var/data/mydb');
 * $db->addDocument(new Document(vector: [0.1, 0.9, ...])); // id auto-generated as UUID
 * $db->save();
 *
 * // Load later:
 * $db = VectorDatabase::open('/var/data/mydb');
 * $results = $db->hybridSearch(vector: $queryVec, text: 'vector search php', k: 5);
 * ```
 */
final class VectorDatabase
{
    private readonly HNSWIndex $hnswIndex;
    private readonly BM25Index $bm25Index;
    private readonly HNSWConfig $hnswConfig;

    /**
     * Internal sequence counter.
     * Both HNSWIndex and BM25Index use this integer as node-ID.
     */
    private int $nextId = 0;

    /**
     * Lazy document cache: nodeId → fully-loaded Document.
     *
     * Documents added in the current session are always in this map.
     * After `open()`, this starts empty and is populated on demand.
     *
     * @var array<int, Document>
     */
    private array $nodeIdToDoc = [];

    /** @var array<string|int, int> user document ID → nodeId */
    private array $docIdToNodeId = [];

    /**
     * DocumentStore instance, created lazily when $path is set.
     */
    private ?DocumentStore $documentStore = null;

    public function __construct(
        HNSWConfig $hnswConfig = new HNSWConfig(),
        BM25Config $bm25Config = new BM25Config(),
        TokenizerInterface $tokenizer = new SimpleTokenizer(),
        private readonly ?string $path = null,
        private readonly int $overFetchMultiplier = 5,
    ) {
        if ($overFetchMultiplier < 1) {
            throw new \InvalidArgumentException('overFetchMultiplier must be at least 1.');
        }
        $this->hnswConfig = $hnswConfig;
        $this->hnswIndex  = new HNSWIndex($hnswConfig);
        $this->bm25Index  = new BM25Index($bm25Config, $tokenizer);
    }

    // ------------------------------------------------------------------
    // Indexing
    // ------------------------------------------------------------------

    /**
     * Add a single document.
     *
     * If `$document->id` is null a random UUID v4 is assigned automatically.
     * When a folder path is configured the document is written to disk
     * asynchronously (pcntl_fork when available, synchronous otherwise).
     *
     * @throws \RuntimeException if a document with the same ID already exists.
     */
    public function addDocument(Document $document): void
    {
        // Assign UUID if no id supplied.
        if ($document->id === null) {
            $document = new Document(
                id:       $this->generateUuid(),
                vector:   $document->vector,
                text:     $document->text,
                metadata: $document->metadata,
            );
        }

        if (isset($this->docIdToNodeId[$document->id])) {
            throw new \RuntimeException(
                sprintf('Document with id "%s" already exists.', $document->id)
            );
        }

        $nodeId = $this->nextId++;
        $this->nodeIdToDoc[$nodeId]         = $document;
        $this->docIdToNodeId[$document->id] = $nodeId;

        $this->hnswIndex->insert($document);
        $this->bm25Index->addDocument($nodeId, $document);

        // Persist doc file asynchronously when a path is configured.
        if ($this->path !== null) {
            $this->ensureDocsDir();
            $this->getDocumentStore()->write(
                nodeId:   $nodeId,
                docId:    $document->id,
                text:     $document->text,
                metadata: $document->metadata,
                async:    true,
            );
        }
    }

    /**
     * Add multiple documents in one call.
     *
     * @param Document[] $documents
     */
    public function addDocuments(array $documents): void
    {
        foreach ($documents as $doc) {
            $this->addDocument($doc);
        }
    }

    /**
     * Delete a document by its user-visible ID.
     *
     * The document is soft-deleted from HNSW (excluded from results but kept
     * for graph connectivity) and fully removed from the BM25 index.
     *
     * When persistence is enabled, a tombstone marker is written immediately so
     * the deletion survives a crash.  The physical doc file is removed during
     * the next `save()` call, after the indexes are fully updated on disk.
     * Call `save()` afterward to persist the updated index state.
     *
     * @param string|int $id The document ID to delete.
     * @return bool True if the document was deleted, false if it didn't exist.
     */
    public function deleteDocument(string|int $id): bool
    {
        if (!isset($this->docIdToNodeId[$id])) {
            return false;
        }

        $nodeId = $this->docIdToNodeId[$id];

        // Soft-delete from HNSW (node stays for connectivity, excluded from results).
        $deletedFromHnsw = $this->hnswIndex->delete($nodeId);
        if ($deletedFromHnsw !== true) {
            throw new \RuntimeException(
                sprintf('Failed to delete node "%s" from HNSW index.', (string) $nodeId)
            );
        }

        // Fully remove from BM25.
        $this->bm25Index->removeDocument($nodeId);

        // Remove from local caches.
        unset($this->nodeIdToDoc[$nodeId]);
        unset($this->docIdToNodeId[$id]);

        // Mark the document for physical deletion when persistence is enabled.
        if ($this->path !== null) {
            // An async pcntl_fork child may still be writing {nodeId}.bin.
            // Wait for it to finish so the file is fully on disk before we
            // record the tombstone — this keeps the pair (bin + tombstone)
            // consistent from the moment the tombstone is created.
            $this->getDocumentStore()->waitForNode($nodeId);

            // Write a tombstone instead of immediately removing the doc file.
            // The physical removal happens in save() AFTER the index files have
            // been updated, giving us crash-safety:
            //   • crash before save()  → open() finds the tombstone and
            //                            re-applies the deletion in memory.
            //   • crash during save()  → at worst the doc file is an orphan;
            //                            the indexes already reflect the deletion.
            $tombstone = $this->path . '/docs/' . $nodeId . '.tombstone';
            if (file_put_contents($tombstone, '') === false) {
                throw new \RuntimeException("Failed to write tombstone file: {$tombstone}");
            }
        }

        return true;
    }

    /**
     * Update a document by replacing it entirely.
     *
     * This is equivalent to deleteDocument() followed by addDocument() with the
     * same ID. The document gets a new internal nodeId, so this is effectively
     * a delete + insert operation.
     *
     * @param Document $document The updated document. Must have the same ID as an existing document.
     * @return bool True if the document was updated, false if it didn't exist.
     * @throws \RuntimeException if the document has no ID.
     */
    public function updateDocument(Document $document): bool
    {
        if ($document->id === null) {
            throw new \RuntimeException('Cannot update a document without an ID.');
        }

        if (!isset($this->docIdToNodeId[$document->id])) {
            return false;
        }

        // Delete the old document.
        $this->deleteDocument($document->id);

        // Insert the new version.
        $this->addDocument($document);

        return true;
    }

    // ------------------------------------------------------------------
    // Search
    // ------------------------------------------------------------------

    /**
     * Pure vector search via HNSW.
     *
     * @param float[]  $vector    Query embedding.
     * @param int      $k         Number of results.
     * @param int|null $ef        Candidate list size (≥ k; null = use index default).
     * @param array<MetadataFilter|array<MetadataFilter>> $filters Metadata filters to apply (AND/OR groups).
     * @param int|null $overFetch Over-fetch multiplier for filtering (null = use config default).
     *
     * @return SearchResult[]
     */
    public function vectorSearch(
        array $vector,
        int $k = 10,
        ?int $ef = null,
        array $filters = [],
        ?int $overFetch = null,
    ): array {
        if (empty($filters)) {
            $raw = $this->hnswIndex->search($vector, $k, $ef);
            return array_map(function (SearchResult $sr): SearchResult {
                $nodeId = $this->docIdToNodeId[$sr->document->id];
                return new SearchResult(
                    document: $this->loadDocument($nodeId),
                    score:    $sr->score,
                    rank:     $sr->rank,
                );
            }, $raw);
        }

        $overFetch ??= $this->overFetchMultiplier;
        $fetchK = $k * $overFetch;

        $raw = $this->hnswIndex->search($vector, $fetchK, $ef);

        // Hydrate stub documents from HNSW into full documents
        $hydrated = array_map(function (SearchResult $sr): SearchResult {
            $nodeId = $this->docIdToNodeId[$sr->document->id];
            return new SearchResult(
                document: $this->loadDocument($nodeId),
                score:    $sr->score,
                rank:     $sr->rank,
            );
        }, $raw);

        return $this->applyMetadataFilters($hydrated, $filters, $k);
    }

    /**
     * Pure BM25 full-text search.
     *
     * @param string   $query     Query text.
     * @param int      $k         Number of results.
     * @param array<MetadataFilter|array<MetadataFilter>> $filters Metadata filters to apply (AND/OR groups).
     * @param int|null $overFetch Over-fetch multiplier for filtering (null = use config default).
     *
     * @return SearchResult[]
     */
    public function textSearch(
        string $query,
        int $k = 10,
        array $filters = [],
        ?int $overFetch = null,
    ): array {
        if (empty($filters)) {
            // Always use scoreAll() path to hydrate documents from VectorDatabase's cache,
            $scores = $this->bm25Index->scoreAll($query);
            if (empty($scores)) {
                return [];
            }

            $topK = array_slice($scores, 0, $k, true);
            return $this->buildSearchResults($topK);
        }

        $overFetch ??= $this->overFetchMultiplier;
        $fetchK = $k * $overFetch;

        $scores = $this->bm25Index->scoreAll($query);
        if (empty($scores)) {
            return [];
        }

        $topScores = array_slice($scores, 0, $fetchK, true);
        $candidates = $this->buildSearchResults($topScores);

        return $this->applyMetadataFilters($candidates, $filters, $k);
    }

    /**
     * Hybrid search: fuse vector similarity and BM25 results.
     *
     * @param float[]    $vector        Query embedding (used for HNSW leg).
     * @param string     $text          Query text (used for BM25 leg).
     * @param int        $k             Final number of results.
     * @param int        $fetchK        Number of candidates fetched from each leg before fusion.
     *                                  Higher values improve recall at the cost of latency.
     *                                  Defaults to max(k * 3, 50).
     * @param HybridMode $mode          Fusion strategy.
     * @param float      $vectorWeight  Weight for vector scores (Weighted mode only).
     * @param float      $textWeight    Weight for BM25 scores (Weighted mode only).
     * @param int        $rrfK          RRF constant k (RRF mode only). Typical value: 60.
     * @param array<MetadataFilter|array<MetadataFilter>> $filters Metadata filters to apply (AND/OR groups).
     * @param int|null   $overFetch     Over-fetch multiplier for filtering (null = use config default).
     *
     * @return SearchResult[]
     */
    public function hybridSearch(
        array $vector,
        string $text,
        int $k = 10,
        ?int $fetchK = null,
        HybridMode $mode = HybridMode::RRF,
        float $vectorWeight = 0.5,
        float $textWeight = 0.5,
        int $rrfK = 60,
        array $filters = [],
        ?int $overFetch = null,
    ): array {
        if (empty($filters)) {
            $fetchK ??= max($k * 3, 50);

            $vectorResults = $this->hnswIndex->search($vector, $fetchK);
            $textScores    = $this->bm25Index->scoreAll($text);

            return match ($mode) {
                HybridMode::RRF      => $this->fuseRRF($vectorResults, $textScores, $k, $rrfK),
                HybridMode::Weighted => $this->fuseWeighted($vectorResults, $textScores, $k, $vectorWeight, $textWeight),
            };
        }

        $overFetch ??= $this->overFetchMultiplier;
        $fusionK = $k * $overFetch;
        $fetchK ??= max($fusionK * 3, 50);

        $vectorResults = $this->hnswIndex->search($vector, $fetchK);
        $textScores    = $this->bm25Index->scoreAll($text);

        $fusedResults = match ($mode) {
            HybridMode::RRF      => $this->fuseRRF($vectorResults, $textScores, $fusionK, $rrfK),
            HybridMode::Weighted => $this->fuseWeighted($vectorResults, $textScores, $fusionK, $vectorWeight, $textWeight),
        };

        return $this->applyMetadataFilters($fusedResults, $filters, $k);
    }

    /**
     * Query documents by metadata alone (no vector or text query required).
     *
     * Returns all documents matching the metadata filters, with optional sorting
     * by a metadata key. All results have a score of 1.0 (no ranking).
     *
     * @param array<MetadataFilter|array<MetadataFilter>> $filters       Metadata filters to apply (AND/OR groups).
     * @param int|null                                     $limit         Maximum number of results (null = all).
     * @param string|null                                  $sortBy        Metadata key to sort by (null = insertion order).
     * @param SortDirection                                $sortDirection Sort direction.
     *
     * @return SearchResult[]
     */
    public function metadataSearch(
        array $filters = [],
        ?int $limit = null,
        ?string $sortBy = null,
        SortDirection $sortDirection = SortDirection::Asc,
    ): array {
        $evaluator = new MetadataFilterEvaluator();
        $matchingDocs = [];

        foreach ($this->docIdToNodeId as $docId => $nodeId) {
            $doc = $this->loadDocument($nodeId);
            if ($evaluator->matches($doc, $filters)) {
                $matchingDocs[$nodeId] = $doc;
            }
        }

        if ($sortBy !== null) {
            uasort(
                $matchingDocs,
                static function (Document $a, Document $b) use ($sortBy, $sortDirection): int {
                    $aHasKey = array_key_exists($sortBy, $a->metadata);
                    $bHasKey = array_key_exists($sortBy, $b->metadata);

                    // Documents missing the sort key go to the end
                    if (!$aHasKey && !$bHasKey) {
                        return 0;
                    }
                    if (!$aHasKey) {
                        return 1;
                    }
                    if (!$bHasKey) {
                        return -1;
                    }

                    $aVal = $a->metadata[$sortBy];
                    $bVal = $b->metadata[$sortBy];

                    $cmp = $aVal <=> $bVal;

                    return $sortDirection === SortDirection::Asc ? $cmp : -$cmp;
                }
            );
        }

        if ($limit !== null) {
            $matchingDocs = array_slice($matchingDocs, 0, $limit, true);
        }

        $results = [];
        $rank = 1;
        foreach ($matchingDocs as $doc) {
            $results[] = new SearchResult(
                document: $doc,
                score:    1.0,
                rank:     $rank++,
            );
        }

        return $results;
    }

    /**
     * Filter pre-scored candidates by metadata and return up to $k results.
     *
     * @param SearchResult[] $candidates Pre-scored results ordered by relevance.
     * @param array<MetadataFilter|array<MetadataFilter>> $filters Metadata filters (AND/OR groups).
     * @param int $k Maximum results to return.
     *
     * @return SearchResult[]
     */
    private function applyMetadataFilters(array $candidates, array $filters, int $k): array
    {
        $evaluator = new MetadataFilterEvaluator();
        $results = [];
        $rank = 1;

        foreach ($candidates as $sr) {
            if ($evaluator->matches($sr->document, $filters)) {
                $results[] = new SearchResult(
                    document: $sr->document,
                    score:    $sr->score,
                    rank:     $rank++,
                );

                if (count($results) >= $k) {
                    break;
                }
            }
        }

        return $results;
    }

    // ------------------------------------------------------------------
    // Update Operations
    // ------------------------------------------------------------------

    /**
     * Update specific metadata keys on a document without re-indexing.
     *
     * Merges `$patch` into the existing metadata. Keys with `null` value in
     * `$patch` are removed from the document's metadata.
     *
     * Does NOT touch the HNSW index or BM25 index. If persistence is enabled,
     * rewrites only the affected `docs/{nodeId}.bin` file.
     *
     * @param string|int       $id    Document identifier.
     * @param array<string, mixed> $patch  Key-value pairs to merge. Keys with null values will be removed.
     *
     * @return bool  True if document found and updated, false if not found.
     */
    public function patchMetadata(string|int $id, array $patch): bool
    {
        if (!isset($this->docIdToNodeId[$id])) {
            return false;
        }

        $nodeId = $this->docIdToNodeId[$id];

        $currentDoc = $this->loadDocument($nodeId);

        // Merge metadata
        $newMetadata = $currentDoc->metadata;
        foreach ($patch as $key => $value) {
            if ($value === null) {
                unset($newMetadata[$key]);
            } else {
                $newMetadata[$key] = $value;
            }
        }

        $updatedDoc = new Document(
            id:       $currentDoc->id,
            vector:   $currentDoc->vector,
            text:     $currentDoc->text,
            metadata: $newMetadata,
        );

        $this->nodeIdToDoc[$nodeId] = $updatedDoc;

        // Persist to disk
        if ($this->path !== null) {
            $this->ensureDocsDir();
            $this->getDocumentStore()->write(
                nodeId:   $nodeId,
                docId:    $updatedDoc->id,
                text:     $updatedDoc->text,
                metadata: $updatedDoc->metadata,
                async:    false, // Synchronous write for immediate visibility.
            );
        }

        return true;
    }

    // ------------------------------------------------------------------
    // Persistence
    // ------------------------------------------------------------------

    /**
     * Persist the database to its configured folder.
     *
     * Writes (in order):
     *  1. Waits for all outstanding async document writes.
     *  2. `meta.json`   — distance code, dimension, nextId, docIdToNodeId.
     *  3. `hnsw.bin`    — HNSW graph (vectors + connections).
     *  4. `bm25.bin`    — BM25 inverted index.
     *  5. Removes `docs/{n}.bin` + `docs/{n}.tombstone` for every pending deletion.
     *
     * Individual `docs/{n}.bin` files are written incrementally by `addDocument()`
     * and are NOT re-written by this method.  Deletion of doc files is deferred
     * to this method so the on-disk state is always consistent.
     *
     * @throws \RuntimeException if no path was configured or on I/O failure.
     */
    public function save(): void
    {
        if ($this->path === null) {
            throw new \RuntimeException(
                'Cannot save: no path configured. Pass $path to the constructor or use open().'
            );
        }

        // Ensure directory structure exists.
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
        $this->ensureDocsDir();

        // Wait for all async document writes before flushing index files.
        if ($this->documentStore !== null) {
            $this->documentStore->waitAll();
        }

        $hnswState = $this->hnswIndex->exportState();

        // meta.json
        $meta = [
            'distance'      => self::encodeDistance($this->hnswConfig->distance),
            'dimension'     => $hnswState['dimension'] ?? 0,
            'nextId'        => $this->nextId,
            'docIdToNodeId' => $this->docIdToNodeId,
            'entryPoint'    => $hnswState['entryPoint'],
            'maxLayer'      => $hnswState['maxLayer'],
            'deleted'       => $hnswState['deleted'],
        ];
        if (file_put_contents($this->path . '/meta.json', json_encode($meta, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
            throw new \RuntimeException("Failed to write meta.json in: {$this->path}");
        }

        $serializer = new IndexSerializer();
        $serializer->writeHnsw($this->path . '/hnsw.bin', $hnswState);
        $serializer->writeBm25($this->path . '/bm25.bin', $this->bm25Index->exportState());

        // Now that all index files reflect the current state, it is safe to
        // physically remove doc files for pending tombstone deletions.
        $docsDir = $this->path . '/docs';
        foreach (glob($docsDir . '/*.tombstone') ?: [] as $tombstoneFile) {
            $nodeId  = (int) basename($tombstoneFile, '.tombstone');
            $binFile = $docsDir . '/' . $nodeId . '.bin';
            if (file_exists($binFile)) {
                @unlink($binFile);
            }
            @unlink($tombstoneFile);
        }
    }

    /**
     * Load a VectorDatabase from a previously saved folder.
     *
     * Only `meta.json`, `hnsw.bin`, and `bm25.bin` are loaded into memory.
     * Individual document files in `docs/` are read lazily when search results
     * are returned.
     *
     * The supplied `$hnswConfig` must use the same distance metric as when the
     * folder was written; a `RuntimeException` is thrown on mismatch.
     *
     * @throws \RuntimeException on I/O failure or distance metric mismatch.
     */
    public static function open(
        string $path,
        HNSWConfig $hnswConfig = new HNSWConfig(),
        BM25Config $bm25Config = new BM25Config(),
        TokenizerInterface $tokenizer = new SimpleTokenizer(),
        int $overFetchMultiplier = 5,
    ): self {
        $metaPath = $path . '/meta.json';
        if (!file_exists($metaPath)) {
            throw new \RuntimeException("Not a PHPVector folder (meta.json missing): {$path}");
        }

        $meta = json_decode(file_get_contents($metaPath), true, 512, JSON_THROW_ON_ERROR);

        // Validate distance metric.
        $distCode = self::encodeDistance($hnswConfig->distance);
        if ($distCode !== (int) $meta['distance']) {
            throw new \RuntimeException(sprintf(
                'Distance mismatch: config uses %s (code %d) but folder was built with code %d.',
                $hnswConfig->distance->name,
                $distCode,
                (int) $meta['distance'],
            ));
        }

        $db = new self($hnswConfig, $bm25Config, $tokenizer, $path, $overFetchMultiplier);
        $db->nextId        = (int) $meta['nextId'];
        $db->docIdToNodeId = $meta['docIdToNodeId'];

        // Build a nodeId → typed-docId map from the JSON-decoded docIdToNodeId.
        // JSON always produces string keys; restore integer type where appropriate.
        $nodeIdToDocId = [];
        foreach ($meta['docIdToNodeId'] as $rawDocId => $nodeId) {
            $typedDocId = is_numeric($rawDocId) && (string)(int)$rawDocId === (string)$rawDocId
                ? (int) $rawDocId
                : (string) $rawDocId;
            $nodeIdToDocId[(int) $nodeId] = $typedDocId;
        }

        $serializer = new IndexSerializer();

        // ── Restore HNSW graph ────────────────────────────────────────────
        $hnswData = $serializer->readHnsw($path . '/hnsw.bin');

        // Build stub Documents for HNSW (id + vector only; no text/metadata).
        // HNSW needs these in $documents[] to return SearchResult objects.
        $hnswState              = $hnswData;
        $hnswState['documents'] = [];
        $hnswState['deleted']   = $meta['deleted'] ?? [];
        foreach ($hnswData['nodes'] as $nodeId => $nodeData) {
            $docId = $nodeIdToDocId[$nodeId] ?? $nodeId;
            $hnswState['documents'][$nodeId] = [
                'id'       => $docId,
                'text'     => null,
                'metadata' => [],
            ];
        }
        $db->hnswIndex->importState($hnswState);

        // ── Restore BM25 index ────────────────────────────────────────────
        $bm25Data = $serializer->readBm25($path . '/bm25.bin');

        // Build BM25 stub Documents (id only) — needed for scoreAll()/search()
        // guards that check empty($this->documents).
        $bm25Stubs = [];
        foreach ($bm25Data['docLengths'] as $nodeId => $_len) {
            $docId              = $nodeIdToDocId[$nodeId] ?? $nodeId;
            $bm25Stubs[$nodeId] = new Document(id: $docId);
        }
        $db->bm25Index->importState($bm25Data, $bm25Stubs);

        // $db->nodeIdToDoc intentionally starts EMPTY — documents are lazy-loaded.

        // ── Reconcile crash-interrupted deletions ─────────────────────────
        // A tombstone file docs/{nodeId}.tombstone is written by deleteDocument()
        // before save() is called.  If the process crashed between those two
        // steps the tombstone survives but the indexes were not yet updated.
        // Re-apply the pending deletion now so the loaded state is consistent.
        $docsDir = $path . '/docs';
        if (is_dir($docsDir)) {
            foreach (glob($docsDir . '/*.tombstone') ?: [] as $tombstoneFile) {
                $nodeId = (int) basename($tombstoneFile, '.tombstone');

                // Apply the deletion only when the node is still present in the
                // loaded indexes (i.e., save() had not yet been called).
                if (isset($nodeIdToDocId[$nodeId])) {
                    $docId = $nodeIdToDocId[$nodeId];
                    $db->hnswIndex->delete($nodeId);
                    $db->bm25Index->removeDocument($nodeId);
                    unset($db->docIdToNodeId[$docId]);
                }

                // Always clean up — covers the edge case where the process
                // crashed after indexes were written but before file removal.
                @unlink($docsDir . '/' . $nodeId . '.bin');
                @unlink($tombstoneFile);
            }
        }

        return $db;
    }

    // ------------------------------------------------------------------
    // Utilities
    // ------------------------------------------------------------------

    /** Total number of active (non-deleted) documents stored. */
    public function count(): int
    {
        return $this->hnswIndex->count();
    }

    // ------------------------------------------------------------------
    // Fusion strategies
    // ------------------------------------------------------------------

    /**
     * Reciprocal Rank Fusion.
     *
     *   RRF(d) = Σᵣ  1 / (k + rankᵣ(d))
     *
     * @param SearchResult[]       $vectorResults
     * @param array<int, float>    $textScores     nodeId → BM25 score (pre-sorted desc)
     * @param int                  $k
     * @param int                  $rrfK
     *
     * @return SearchResult[]
     */
    private function fuseRRF(
        array $vectorResults,
        array $textScores,
        int $k,
        int $rrfK,
    ): array {
        $fused = [];

        // Vector ranks (1-based).
        foreach ($vectorResults as $sr) {
            $nodeId = $this->docIdToNodeId[$sr->document->id];
            $fused[$nodeId] = ($fused[$nodeId] ?? 0.0) + 1.0 / ($rrfK + $sr->rank);
        }

        // BM25 ranks (1-based; $textScores is already sorted descending by scoreAll()).
        $bm25Rank = 1;
        foreach ($textScores as $nodeId => $score) {
            $fused[$nodeId] = ($fused[$nodeId] ?? 0.0) + 1.0 / ($rrfK + $bm25Rank);
            $bm25Rank++;
        }

        arsort($fused);

        return $this->buildSearchResults(array_slice($fused, 0, $k, true));
    }

    /**
     * Weighted linear combination of min-max normalised scores.
     *
     *   combined(d) = α · vecNorm(d) + β · bm25Norm(d)
     *
     * @param SearchResult[]    $vectorResults
     * @param array<int, float> $textScores
     *
     * @return SearchResult[]
     */
    private function fuseWeighted(
        array $vectorResults,
        array $textScores,
        int $k,
        float $vectorWeight,
        float $textWeight,
    ): array {
        // Normalise vector scores to [0, 1].
        $vecNorm = $this->minMaxNormalise(
            array_combine(
                array_map(fn($sr) => $this->docIdToNodeId[$sr->document->id], $vectorResults),
                array_column($vectorResults, 'score'),
            )
        );

        // Normalise BM25 scores to [0, 1].
        $bm25Norm = $this->minMaxNormalise($textScores);

        // Collect all candidate node IDs from both legs.
        $allIds = array_unique(array_merge(array_keys($vecNorm), array_keys($bm25Norm)));

        $fused = [];
        foreach ($allIds as $nodeId) {
            $fused[$nodeId] =
                $vectorWeight * ($vecNorm[$nodeId] ?? 0.0) +
                $textWeight   * ($bm25Norm[$nodeId] ?? 0.0);
        }

        arsort($fused);

        return $this->buildSearchResults(array_slice($fused, 0, $k, true));
    }

    /**
     * Min-max normalise a nodeId → score map to [0, 1].
     *
     * @param array<int, float> $scores
     * @return array<int, float>
     */
    private function minMaxNormalise(array $scores): array
    {
        if (empty($scores)) {
            return [];
        }

        $min = min($scores);
        $max = max($scores);

        if ($max === $min) {
            return array_fill_keys(array_keys($scores), 1.0);
        }

        $range = $max - $min;
        $out   = [];
        foreach ($scores as $nodeId => $score) {
            $out[$nodeId] = ($score - $min) / $range;
        }
        return $out;
    }

    /**
     * Convert a nodeId → fusedScore map into ranked SearchResult objects,
     * hydrating each Document lazily from disk when needed.
     *
     * @param array<int, float> $fused  Already sorted descending, sliced to k.
     * @return SearchResult[]
     */
    private function buildSearchResults(array $fused): array
    {
        $results = [];
        $rank    = 1;
        foreach ($fused as $nodeId => $score) {
            $results[] = new SearchResult(
                document: $this->loadDocument((int) $nodeId),
                score:    $score,
                rank:     $rank++,
            );
        }
        return $results;
    }

    // ------------------------------------------------------------------
    // Lazy document loading
    // ------------------------------------------------------------------

    /**
     * Return the fully-loaded Document for $nodeId.
     *
     * Returns from the in-memory cache when available.
     * Otherwise reads `docs/{nodeId}.bin`, combines with the vector from HNSW,
     * and caches the result.
     */
    private function loadDocument(int $nodeId): Document
    {
        if (isset($this->nodeIdToDoc[$nodeId])) {
            return $this->nodeIdToDoc[$nodeId];
        }

        // Load text + metadata from the doc file.
        [$docId, $text, $metadata] = $this->getDocumentStore()->read($nodeId);

        // Combine with the vector stored in the HNSW graph.
        $vector = $this->hnswIndex->getVector($nodeId);

        $doc = new Document(
            id:       $docId,
            vector:   $vector,
            text:     $text,
            metadata: $metadata,
        );
        $this->nodeIdToDoc[$nodeId] = $doc;
        return $doc;
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    /** Lazy accessor for the DocumentStore (only used when $path is set). */
    private function getDocumentStore(): DocumentStore
    {
        if ($this->documentStore === null) {
            $this->documentStore = new DocumentStore($this->path . '/docs');
        }
        return $this->documentStore;
    }

    /** Create the docs/ subdirectory if it doesn't exist yet. */
    private function ensureDocsDir(): void
    {
        $docsDir = $this->path . '/docs';
        if (!is_dir($docsDir)) {
            mkdir($docsDir, 0755, true);
        }
    }

    /**
     * Generate a random UUID v4 string.
     *
     * Uses cryptographically secure random bytes (random_bytes).
     */
    private function generateUuid(): string
    {
        $bytes    = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    // ------------------------------------------------------------------
    // Distance codec
    // ------------------------------------------------------------------

    private static function encodeDistance(Distance $d): int
    {
        return match ($d) {
            Distance::Cosine     => 0,
            Distance::Euclidean  => 1,
            Distance::DotProduct => 2,
            Distance::Manhattan  => 3,
        };
    }
}
