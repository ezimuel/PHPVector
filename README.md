# PHPVector

A pure-PHP vector database implementing **HNSW** (Hierarchical Navigable Small World) for approximate nearest-neighbour search and **BM25** for full-text retrieval. Both engines can be combined into a single **hybrid search** pipeline.

## Requirements

- PHP 8.2+
- No external PHP extensions required for core functionality
- `ext-pcntl` (optional) — enables asynchronous document writes for lower insert latency

## Installation

```bash
composer require ezimuel/phpvector
```

## Quick start

### 1. Insert documents

A `Document` holds a dense embedding vector, optional raw text for BM25, and any metadata you want returned with results. The `id` field is optional — if omitted, a random UUID v4 is assigned automatically.

```php
use PHPVector\Document;
use PHPVector\VectorDatabase;

$db = new VectorDatabase();

$db->addDocuments([
    new Document(
        id: 1,
        vector: [0.12, 0.85, 0.44, 0.67],
        text: 'PHP vector database with HNSW index',
        metadata: ['url' => 'https://example.com/1', 'lang' => 'en'],
    ),
    new Document(
        id: 2,
        vector: [0.91, 0.23, 0.78, 0.05],
        text: 'Approximate nearest neighbour search in PHP',
        metadata: ['url' => 'https://example.com/2', 'lang' => 'en'],
    ),
    new Document(
        id: 3,
        vector: [0.33, 0.61, 0.19, 0.88],
        text: 'BM25 full-text ranking algorithm explained',
        metadata: ['url' => 'https://example.com/3', 'lang' => 'en'],
    ),
    // No id — a UUID v4 is assigned automatically.
    new Document(
        vector: [0.55, 0.42, 0.71, 0.30],
        text: 'Hybrid search with Reciprocal Rank Fusion',
    ),
]);
```

### 2. Vector search

Find the *k* most similar documents to a query vector using HNSW.

```php
$queryVector = [0.10, 0.80, 0.50, 0.60];

$results = $db->vectorSearch(vector: $queryVector, k: 2);

foreach ($results as $result) {
    echo sprintf(
        "[%d] score=%.4f  %s\n",
        $result->rank,
        $result->score,
        $result->document->metadata['url'],
    );
}
// [1] score=0.9987  https://example.com/1
// [2] score=0.8341  https://example.com/3
```

### 3. Full-text search

Rank documents by BM25 relevance against a text query.

```php
$results = $db->textSearch(query: 'nearest neighbour PHP', k: 2);

foreach ($results as $result) {
    echo sprintf(
        "[%d] score=%.4f  %s\n",
        $result->rank,
        $result->score,
        $result->document->metadata['url'],
    );
}
// [1] score=1.2430  https://example.com/2
// [2] score=0.8761  https://example.com/1
```

### 4. Hybrid search

Fuse vector similarity and BM25 scores into a single ranked list.

#### Reciprocal Rank Fusion (recommended)

RRF is rank-based and scale-invariant — no tuning required.

```php
use PHPVector\HybridMode;

$results = $db->hybridSearch(
    vector: $queryVector,
    text:   'vector database PHP',
    k:      3,
    mode:   HybridMode::RRF,
);

foreach ($results as $result) {
    echo sprintf(
        "[%d] score=%.4f  %s\n",
        $result->rank,
        $result->score,
        $result->document->metadata['url'],
    );
}
```

#### Weighted combination

Normalises both score ranges to [0, 1] then applies explicit weights.

```php
$results = $db->hybridSearch(
    vector:       $queryVector,
    text:         'vector database PHP',
    k:            3,
    mode:         HybridMode::Weighted,
    vectorWeight: 0.7,
    textWeight:   0.3,
);
```

## Configuration

Both the HNSW and BM25 engines are fully configurable. Pass config objects to the `VectorDatabase` constructor.

```php
use PHPVector\BM25\Config as BM25Config;
use PHPVector\BM25\SimpleTokenizer;
use PHPVector\Distance;
use PHPVector\HNSW\Config as HNSWConfig;
use PHPVector\VectorDatabase;

$db = new VectorDatabase(
    hnswConfig: new HNSWConfig(
        M:               16,    // Max connections per node per layer. Higher → better recall, more memory.
        efConstruction:  200,   // Beam width during index build. Higher → better graph quality, slower inserts.
        efSearch:        50,    // Beam width during query. Higher → better recall, slower queries.
        distance:        Distance::Cosine, // Cosine | Euclidean | DotProduct | Manhattan
        useHeuristic:    true,  // Diverse neighbour selection (recommended).
    ),
    bm25Config: new BM25Config(
        k1: 1.5,   // TF saturation. Range 1.2–2.0.
        b:  0.75,  // Length normalisation. 0 = none, 1 = full.
    ),
    tokenizer: new SimpleTokenizer(
        stopWords:      SimpleTokenizer::DEFAULT_STOP_WORDS,
        minTokenLength: 2,
    ),
);
```

### Distance metrics

| Metric | Best for |
|--------|----------|
| `Distance::Cosine` | Text embeddings, normalised vectors |
| `Distance::Euclidean` | Raw, unnormalized vectors |
| `Distance::DotProduct` | Unit-normalized vectors (faster than Cosine) |
| `Distance::Manhattan` | Sparse vectors, robustness to outliers |

### HNSW tuning cheat-sheet

| Goal | Knob |
|------|------|
| Better recall | Increase `efSearch` or `efConstruction` |
| Faster queries | Decrease `efSearch` |
| Less memory | Decrease `M` |
| Better graph on clustered data | Keep `useHeuristic: true` |

## Persistence

PHPVector uses a **folder-based** persistence model. Each database lives in its own directory containing separate files for the HNSW graph, the BM25 index, and one file per document. This design has two key advantages:

- **Low memory footprint on load** — only the HNSW graph and BM25 index are loaded into memory. Individual document files (`docs/{n}.bin`) are read lazily, only for the documents that appear in search results.
- **Low insert latency** — document files are written to disk asynchronously in a forked child process (requires `ext-pcntl`), so `addDocument()` returns immediately.

### Folder layout

```
/var/data/mydb/
  meta.json       — distance metric, dimension, document ID map
  hnsw.bin        — HNSW graph (vectors + connections)
  bm25.bin        — BM25 inverted index
  docs/
    0.bin         — document 0 (id, text, metadata)
    1.bin         — document 1
    …
```

### Saving

Pass a `path` to the constructor to enable persistence. Each `addDocument()` call writes the document file to `docs/` (asynchronously when `ext-pcntl` is available). Call `save()` once to flush the HNSW graph and BM25 index — it waits for any outstanding async writes before proceeding.

```php
use PHPVector\Document;
use PHPVector\VectorDatabase;

$db = new VectorDatabase(path: '/var/data/mydb');

$db->addDocuments([
    new Document(id: 1, vector: [0.12, 0.85, 0.44], text: 'PHP vector search', metadata: ['source' => 'blog']),
    new Document(id: 2, vector: [0.91, 0.23, 0.78], text: 'Approximate nearest neighbour'),
    // ... thousands more
]);

// Flush HNSW graph + BM25 index to disk (document files already written).
$db->save();
```

### Loading

Use `VectorDatabase::open()` to load a previously saved folder. Only `hnsw.bin` and `bm25.bin` are read into memory; document files are loaded on demand after search.

Pass the same `HNSWConfig` (including the same `distance` metric) that was used when building the index — a `RuntimeException` is thrown on mismatch.

```php
use PHPVector\VectorDatabase;

$db = VectorDatabase::open('/var/data/mydb');

// All three search modes work immediately.
$results = $db->vectorSearch(vector: $queryVector, k: 5);
$results = $db->textSearch(query: 'nearest neighbour', k: 5);
$results = $db->hybridSearch(vector: $queryVector, text: 'nearest neighbour', k: 5);
```

### Custom configuration on open

```php
use PHPVector\BM25\Config as BM25Config;
use PHPVector\Distance;
use PHPVector\HNSW\Config as HNSWConfig;
use PHPVector\VectorDatabase;

$db = VectorDatabase::open(
    path:       '/var/data/mydb',
    hnswConfig: new HNSWConfig(
        M:        16,
        efSearch: 100,
        distance: Distance::Euclidean,  // must match the value used on save()
    ),
    bm25Config: new BM25Config(k1: 1.2, b: 0.8),
    tokenizer:  new MyCustomTokenizer(),
);
```

> **Note:** Only `efSearch` and `bm25Config`/`tokenizer` affect query-time behaviour and can differ from build time. `distance` and the graph parameters (`M`, `efConstruction`) are fixed at build time — `distance` is validated on `open()` and must match.

### Incremental updates

You can add new documents to a database that was loaded from disk, then call `save()` again. The existing document files are left in place; only the new ones are written along with updated index files.

```php
$db = VectorDatabase::open('/var/data/mydb');
$db->addDocument(new Document(vector: [0.55, 0.42, 0.71], text: 'New document'));
$db->save(); // writes docs/N.bin + updated hnsw.bin, bm25.bin, meta.json
```

### Typical workflow: build once, serve many

```php
// build.php — run once (or nightly)
$db = new VectorDatabase(
    hnswConfig: new HNSWConfig(M: 32, efConstruction: 400),
    path: '/var/data/mydb',
);
foreach (fetchDocumentsFromDatabase() as $doc) {
    $db->addDocument($doc);
}
$db->save();

// serve.php — loaded on every request or worker boot
$db = VectorDatabase::open('/var/data/mydb', new HNSWConfig(M: 32));
$results = $db->vectorSearch($queryVector, k: 10);
```

## Multi-language stop words

Stop words are provided via `StopWordsProviderInterface`. Built-in providers:

```php
use PHPVector\BM25\SimpleTokenizer;
use PHPVector\BM25\StopWords\EnglishStopWords;
use PHPVector\BM25\StopWords\ItalianStopWords;
use PHPVector\BM25\StopWords\FileStopWords;
use PHPVector\VectorDatabase;

// English (default)
$db = new VectorDatabase();

// Italian
$db = new VectorDatabase(
    tokenizer: new SimpleTokenizer(new ItalianStopWords()),
);

// Load from file (one word per line, # for comments)
$db = new VectorDatabase(
    tokenizer: new SimpleTokenizer(new FileStopWords('/path/to/stopwords.txt')),
);

### Stop words file format (`FileStopWords`)

Use a plain UTF-8 text file with one stop word per line.

Rules:
- Empty lines are ignored
- Lines starting with `#` are treated as comments
- Words are normalized to lowercase when loaded

Example (`stopwords-it.txt`):

```txt
# Italian stop words
e
di
a
che
il
la
```

// No stop words
$db = new VectorDatabase(
    tokenizer: new SimpleTokenizer(stopWords: []),
);
```

Available providers:
- `EnglishStopWords` - English stop words (default)
- `ItalianStopWords` - Italian stop words
- `FileStopWords` - Load from file

## Deleting and updating documents

```php
// Delete a document by ID
$deleted = $db->deleteDocument(1);  // returns true if found, false otherwise

// Update a document (delete + insert with same ID)
$updated = $db->updateDocument(new Document(
    id: 1,
    vector: [0.5, 0.5, 0.3, 0.2],
    text: 'Updated content here',
    metadata: ['version' => 2],
));

// After modifications, call save() to persist
$db->save();
```

Deleted documents are soft-deleted from the HNSW graph (kept for connectivity but excluded from results) and fully removed from the BM25 index. Document files are deleted from disk immediately.

## Metadata filtering

Filter search results by document metadata. Filters can be combined with any search method — vector, text, or hybrid.

### Creating filters

Use the `MetadataFilter` value object. All eleven operators are supported:

```php
use PHPVector\Metadata\MetadataFilter;

// Equality / inequality
$filter = MetadataFilter::eq('status', 'published');
$filter = MetadataFilter::neq('type', 'draft');

// Comparison operators
$filter = MetadataFilter::lt('price', 100);
$filter = MetadataFilter::lte('price', 100);
$filter = MetadataFilter::gt('rating', 4.0);
$filter = MetadataFilter::gte('rating', 4.0);

// Set membership
$filter = MetadataFilter::in('category', ['tech', 'science', 'engineering']);
$filter = MetadataFilter::notIn('status', ['deleted', 'archived']);

// Array containment — checks if metadata array contains the value
$filter = MetadataFilter::contains('tags', 'php');  // matches ['tags' => ['php', 'vector']]

// Existence checks — does a metadata key exist (regardless of value)?
$filter = MetadataFilter::exists('thumbnail');
$filter = MetadataFilter::notExists('deleted_at');
```

### Filtering search results

Pass filters to any search method. Multiple filters are ANDed together by default.

```php
use PHPVector\Metadata\MetadataFilter;

// Vector search with filters
$results = $db->vectorSearch(
    vector: $queryVector,
    k: 10,
    filters: [
        MetadataFilter::eq('lang', 'en'),
        MetadataFilter::gt('year', 2020),
    ],
);

// Text search with filters
$results = $db->textSearch(
    query: 'machine learning',
    k: 10,
    filters: [
        MetadataFilter::in('category', ['tech', 'science']),
    ],
);

// Hybrid search with filters
$results = $db->hybridSearch(
    vector: $queryVector,
    text: 'machine learning',
    k: 10,
    filters: [
        MetadataFilter::eq('status', 'published'),
    ],
);
```

### OR groups (nested arrays)

Wrap filters in a nested array to create OR groups. Filters at the top level are ANDed; filters inside a nested array are ORed.

```php
// (category = 'tech' OR category = 'science') AND status = 'published'
$results = $db->vectorSearch(
    vector: $queryVector,
    k: 10,
    filters: [
        [
            MetadataFilter::eq('category', 'tech'),
            MetadataFilter::eq('category', 'science'),
        ],  // OR group
        MetadataFilter::eq('status', 'published'),  // ANDed with the OR group
    ],
);
```

### Over-fetching for filtered queries

When filters are applied, the search may need to examine more candidates than `k` to find enough matching documents. By default, the search fetches `k * 5` candidates, then filters. You can tune this:

```php
// Fetch 10× candidates before filtering (useful when filters are very selective)
$results = $db->vectorSearch(
    vector: $queryVector,
    k: 10,
    filters: [MetadataFilter::eq('rare_tag', 'value')],
    overFetch: 10,
);

// Or set the default multiplier at construction time
$db = new VectorDatabase(
    overFetchMultiplier: 10,
);
```

> **Note:** Filtered queries may return fewer than `k` results if not enough documents match.

### Updating metadata

Update metadata on existing documents without re-indexing vectors or text:

```php
// Add or update metadata keys
$db->patchMetadata(id: 1, patch: [
    'status' => 'archived',
    'updated_at' => '2026-03-24',
]);

// Remove metadata keys by setting to null
$db->patchMetadata(id: 1, patch: [
    'deprecated_field' => null,  // key will be removed
]);

// patchMetadata returns false if document not found
if (!$db->patchMetadata(id: 999, patch: ['key' => 'value'])) {
    echo "Document not found\n";
}
```

The `patchMetadata()` method:
- Merges patch into existing metadata (existing keys preserved unless overwritten)
- Does NOT touch HNSW or BM25 indexes (fast, metadata-only operation)
- Persists immediately when database has a path configured

### Metadata-only search

Query documents by metadata alone, without a vector or text query:

```php
use PHPVector\Metadata\SortDirection;

// Find all documents matching filters
$results = $db->metadataSearch(
    filters: [MetadataFilter::eq('status', 'published')],
);

// With limit
$results = $db->metadataSearch(
    filters: [MetadataFilter::gt('year', 2020)],
    limit: 100,
);

// With sorting by metadata key
$results = $db->metadataSearch(
    filters: [MetadataFilter::eq('status', 'published')],
    sortBy: 'created_at',
    sortDirection: SortDirection::Desc,
);

// Empty filters returns all documents
$allDocs = $db->metadataSearch(filters: [], limit: 50);
```

> **Note:** Documents missing the `sortBy` key are placed at the end of results. All results have `score = 1.0` (no ranking).

### Strict type comparison

Metadata filtering uses **strict type comparison** (PHP `===`). This means:
- String `'5'` does NOT match integer `5`
- Float `1.0` does NOT match integer `1`

```php
// Document with metadata: ['year' => 2024] (integer)
MetadataFilter::eq('year', 2024);    // ✓ matches
MetadataFilter::eq('year', '2024');  // ✗ does not match (string vs int)

// Document with metadata: ['rating' => 4.5] (float)
MetadataFilter::gt('rating', 4);     // ✓ matches (4.5 > 4)
MetadataFilter::eq('rating', 4.5);   // ✓ matches
MetadataFilter::eq('rating', '4.5'); // ✗ does not match (string vs float)
```

## Custom tokenizer

Implement `TokenizerInterface` to plug in stemming, lemmatization, or any language-specific logic.

```php
use PHPVector\BM25\TokenizerInterface;

final class PorterStemTokenizer implements TokenizerInterface
{
    public function tokenize(string $text): array
    {
        $tokens = preg_split('/\s+/', mb_strtolower(trim($text)), -1, PREG_SPLIT_NO_EMPTY);
        return array_map(fn($t) => porter_stem($t), $tokens); // your stemmer here
    }
}

$db = new VectorDatabase(tokenizer: new PorterStemTokenizer());
```

## Benchmark

A [VectorDBBench](https://github.com/zilliztech/VectorDBBench)-style CLI benchmark lives in `benchmark/`. It measures index build throughput, serial QPS, P99 tail latency, Recall@k against brute-force ground truth, and persistence speed.

```bash
# Quick run (1 K and 10 K vectors, 128 dimensions)
php benchmark/benchmark.php

# Full run — save report to a file
php benchmark/benchmark.php --scenarios=xs,small,medium,large,highdim --output=report.md

# Large dataset, skip recall (brute-force would be slow)
php benchmark/benchmark.php --scenarios=large --no-recall --queries=500

# Tune HNSW parameters
php benchmark/benchmark.php --scenarios=small --ef-search=100 --m=32

# All options
php benchmark/benchmark.php --help
```

**Available scenarios**

| Key | Vectors | Dims | Notes |
|-----|---------|------|-------|
| `xs` | 1,000 | 128 | Quick smoke test |
| `small` | 10,000 | 128 | SIFT-small scale |
| `medium` | 50,000 | 128 | SIFT-medium scale |
| `large` | 100,000 | 128 | Requires ~512 MB RAM |
| `highdim` | 10,000 | 768 | Text-embedding scale (Cohere-style) |

The report is printed as Markdown to stdout (or a file via `--output`). Progress messages go to stderr so piping works cleanly: `php benchmark/benchmark.php > report.md`.

## Running the tests

```bash
composer install
./vendor/bin/phpunit
```

## Copyright

(C) 2026 by [Enrico Zimuel](https://www.zimuel.it)
