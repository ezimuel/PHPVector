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

[Unreleased]: https://github.com/ezimuel/PHPVector/commits/main
