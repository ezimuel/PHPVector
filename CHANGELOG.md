# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.4.1] - 2026-09-02

Documentation only. No code changes, no behaviour changes.

### Fixed
* The main configuration example in the README used `SimpleTokenizer::DEFAULT_STOP_WORDS`, a constant removed in 0.3.0 when stop words moved behind `StopWordsProviderInterface`. Copying the example raised an undefined constant error. It now passes an `EnglishStopWords` provider.
* The README and `BENCHMARK.md` pointed the benchmark CLI at `benchmark/benchmark.php`, which does not exist. The entry point is `benchmark/run.php`. The wrong path happened to resolve on case insensitive filesystems and failed everywhere else.
* An unclosed code fence in the stop words section of the README rendered the following heading and its rules as PHP source, and left the "no stop words" example without an opening fence.
* The README stated that `open()` reads only `hnsw.bin` and `bm25.bin` into memory, omitting `meta.json`.
* The README presented atomic file replacement and `flock()` as working on Windows. The `rename()` guarantee is POSIX only, so the claim is now limited to Linux and macOS.

### Added
* README section documenting `count()` and `isPersistent()`, both public since 0.3.0 but never documented.

## [0.4.0] - 2026-09-02

### Added
* Atomic replacement of index and document files: writes land on a temporary sibling and are moved into place with `rename()`, so a reader never observes a truncated file.
* `flock()` based folder lock guarding persistence: `save()` holds it exclusively, `open()` holds it in shared mode, so readers never run alongside a writer.
* `lockTimeout` parameter on the `VectorDatabase` constructor and on `VectorDatabase::open()`, defaulting to 10 seconds. Acquisition is non blocking and raises `LockTimeoutException` on expiry.
* `Persistence\AtomicFile`, `Persistence\FileLock` and `Exception\LockTimeoutException`.

### Fixed
* Benchmark comparison reported success when the baseline and the current run shared no metric names, so a regression of any size was rendered as a pass. An empty match set is now reported as such.
* Benchmark metric names are always prefixed with their scenario, instead of only when a run covered more than one, which made a baseline recorded from a single scenario impossible to match.
* Benchmark comparison judged throughput on the same 5% threshold as memory and disk. Measured run to run variance on shared CI hardware exceeds 40% with identical code, so every pull request drew regressions that were not there. Throughput metrics are now reported informationally and gate only beyond 50%, while memory and disk, which repeat exactly, keep the tight threshold.

### Changed
* The privileged half of the benchmark workflow runs in a separate job, so pull requests can no longer rewrite the benchmark baseline.
* Both benchmark jobs run the same scenarios.
* Dependency bumps: `actions/checkout` 7, `actions/cache` 6, `codecov/codecov-action` 7.

### Known limitations
* Per file atomicity is not multi file transactionality. A process killed mid `save()` leaves each file intact but possibly mismatched between `meta.json` and the two index files.
* `flock()` is advisory and host local, so this is not a distributed lock and it is unreliable on NFS and similar network volumes.

## [0.3.0] - 2026-05-28

### Added
* Document lifecycle operations: `update`, `delete`, and metadata patch, with per document term tracking so deletions clean up the BM25 index.
* A `.tombstone` marker guarding the race between a delete and an in flight asynchronous document write.
* Metadata filtering across all search paths, with `exists` and `notExists` operators.
* `Metadata\SortDirection` enum for metadata sorting.
* `StopWordsProviderInterface` with English, Italian, and file based providers.
* `VectorDatabase::isPersistent()` to report whether on-disk persistence is configured.
* Configurable `overFetchMultiplier` on `VectorDatabase`.
* Benchmark suite with per pull request comparison comments.
* Contribution and OSS hygiene docs, including a code of conduct.

### Changed
* Adaptive `efConstruction` scales the beam width with graph size.
* `IndexSerializer` streams `pack()` output straight to disk, lowering serialization memory from O(n²) to O(n).
* PHPStan raised to level 6, and PER-CS 2.0 formatting applied across the codebase via php-cs-fixer.

### Fixed
* Searches returning fewer than `k` results.
* Deleted documents were still visited during metadata search iteration.
* Zero comparison for JSON integers in metadata filters.
* `efConstruction` is validated against `M0` in the HNSW config.

### Breaking
* `MetadataFilter` moved to the `Metadata` namespace.
* Sort direction is now the `SortDirection` enum instead of a validated string.
* `overFetchMultiplier` moved to `VectorDatabase`.
* Minimum PHP raised to 8.2.

## [0.2.0] - 2026-03-17

### Added
* Folder based persistence model. Each database lives in its own directory holding `meta.json`, `hnsw.bin`, `bm25.bin`, and one file per document under `docs/`.
* Lazy document loading: `open()` reads only the HNSW graph and the BM25 index into memory, and individual `docs/{n}.bin` files are read on demand for search results.
* Asynchronous document writes in a forked child process when `ext-pcntl` is available, so `addDocument()` returns immediately.
* `Persistence\DocumentStore` and `Persistence\IndexSerializer`.
* PHPStan, a PHPUnit configuration, and a GitHub Actions test workflow.

### Breaking
* `Persistence\BinarySerializer` and its single file on-disk format are replaced by the folder layout. Databases saved with 0.1.0 must be rebuilt.

## [0.1.0] - 2026-03-11

### Added
* First release. A pure PHP vector database with no extension requirements beyond the standard library.
* HNSW index for approximate nearest neighbour search.
* BM25 full text ranking with a pluggable `TokenizerInterface`.
* Hybrid search combining vector and full text results via Reciprocal Rank Fusion and weighted linear combination.
* Distance metrics: cosine, Euclidean, dot product, and Manhattan.
* Arbitrary key value metadata stored on each `Document` and returned with search results.
* Binary serialization of the index to a single file.

[Unreleased]: https://github.com/ezimuel/PHPVector/compare/0.4.1...HEAD
[0.4.1]: https://github.com/ezimuel/PHPVector/compare/0.4.0...0.4.1
[0.4.0]: https://github.com/ezimuel/PHPVector/compare/0.3.0...0.4.0
[0.3.0]: https://github.com/ezimuel/PHPVector/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/ezimuel/PHPVector/compare/0.1.0...0.2.0
[0.1.0]: https://github.com/ezimuel/PHPVector/releases/tag/0.1.0
