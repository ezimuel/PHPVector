# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
* HNSW index for approximate nearest neighbour search.
* BM25 full text ranking with pluggable tokenizers and stop word providers (English, Italian, file based).
* Hybrid search combining vector and full text results via Reciprocal Rank Fusion and weighted linear combination.
* Metadata storage, filtering, and sorting.
* Distance metrics: Cosine, Euclidean, dot product, Manhattan.
* Document CRUD: add, update, delete, and metadata patch.
* Persistence layer with index serialization and a document store.
* `VectorDatabase::isPersistent()` to report whether on-disk persistence is configured.
* Atomic replacement of index and document files: writes land on a temporary sibling and are moved into place with `rename()`, so a reader never observes a truncated file.
* `flock()` based folder lock guarding persistence: `save()` holds it exclusively, `open()` holds it in shared mode, so readers never run alongside a writer.
* `lockTimeout` parameter on the `VectorDatabase` constructor and on `VectorDatabase::open()`, defaulting to 10 seconds. Acquisition is non blocking and raises `LockTimeoutException` on expiry.
* `Persistence\AtomicFile`, `Persistence\FileLock` and `Exception\LockTimeoutException`.

### Fixed
* Benchmark comparison reported success when the baseline and the current run shared no metric names, so a regression of any size was rendered as a pass. An empty match set is now reported as such.
* Benchmark metric names are always prefixed with their scenario, instead of only when a run covered more than one, which made a baseline recorded from a single scenario impossible to match.
* Benchmark comparison judged throughput on the same 5% threshold as memory and disk. Measured run to run variance on shared CI hardware exceeds 40% with identical code, so every pull request drew regressions that were not there. Throughput metrics are now reported informationally and gate only beyond 50%, while memory and disk, which repeat exactly, keep the tight threshold.

[Unreleased]: https://github.com/ezimuel/PHPVector/commits/main
