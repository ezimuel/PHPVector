# PHPVector Benchmark

A [VectorDBBench](https://github.com/zilliztech/VectorDBBench)-style benchmark for
PHPVector, measuring the four metrics that matter in production:

| Metric | What it tells you |
|--------|-------------------|
| **Index build throughput** | How fast documents can be ingested (doc/s) |
| **Serial QPS** | Sustainable single-process query rate |
| **P99 latency** | Worst-case latency for 99% of queries |
| **Recall@k** | Fraction of true nearest neighbours returned |

A **persistence section** is also included, measuring `save()` and `open()` speed and folder throughput.

---

## Requirements

- **PHP 8.1+** (tested on 8.5)
- **Composer** dependencies installed (`composer install`)
- **Memory:** the script sets `memory_limit = 2G`; override with
  `-d memory_limit=4G` if needed for the `large` scenario
- No external PHP extensions required beyond a standard CLI build

---

## Quick start

```bash
# Default run: XS (1 K) and Small (10 K) scenarios, printed to stdout
php benchmark/benchmark.php

# Save the report to a file
php benchmark/benchmark.php --output=report.md

# Smoke-test only (fastest)
php benchmark/benchmark.php --scenarios=xs

# Full suite
php benchmark/benchmark.php --scenarios=xs,small,medium,large,highdim --output=report.md
```

Progress messages are written to **stderr**; the Markdown report goes to **stdout**
(or the file specified by `--output`). This means piping works cleanly:

```bash
php benchmark/benchmark.php 2>/dev/null > report.md
```

---

## Scenarios

Each scenario generates a fully reproducible synthetic dataset of random
unit-normalised vectors using a fixed seed. The dimensionalities mirror the
ranges used by VectorDBBench (SIFT at 128d, Cohere-style embeddings at 768d).

| Key | Vectors | Dims | Label | Est. RAM | Notes |
|-----|---------|------|-------|----------|-------|
| `xs` | 1,000 | 128 | XS | ~10 MB | Quick smoke test |
| `small` | 10,000 | 128 | S | ~50 MB | SIFT-small scale |
| `medium` | 50,000 | 128 | M | ~250 MB | SIFT-medium scale |
| `large` | 100,000 | 128 | L | ~500 MB | Requires ≥ 512 MB RAM |
| `highdim` | 10,000 | 768 | HD | ~80 MB | Text-embedding scale |

> **Runtime note.** PHPVector is a pure-PHP library; build times are proportional
> to `N × efConstruction`. With the defaults (M=16, efConstruction=200) expect
> several minutes for `medium` and `large`. Use `--ef-construction=50` for faster
> exploratory runs, then benchmark at full quality separately.

The default when no `--scenarios` flag is given is **`xs,small`**.

---

## All options

```
php benchmark/benchmark.php [options]
```

### Dataset and search

| Option | Default | Description |
|--------|---------|-------------|
| `--scenarios=<list>` | `xs,small` | Comma-separated scenario keys to run |
| `--k=<n>` | `10` | Number of nearest neighbours to retrieve per query |
| `--queries=<n>` | `200` | Search queries issued per scenario |
| `--recall-samples=<n>` | `50` | Queries used for brute-force ground-truth recall |
| `--seed=<n>` | `42` | Random seed — same seed → identical dataset across runs |

### HNSW index configuration

| Option | Default | Description |
|--------|---------|-------------|
| `--m=<n>` | `16` | Max bi-directional connections per node per layer |
| `--ef-construction=<n>` | `200` | Beam width during index build |
| `--ef-search=<n>` | `50` | Beam width during queries |

### Output

| Option | Default | Description |
|--------|---------|-------------|
| `--output=<file>` | stdout | Write Markdown report to this path |
| `--no-save` | off | Skip the persistence (`save` / `open`) phase |
| `--no-recall` | off | Skip recall computation (faster for large datasets) |
| `--help`, `-h` | — | Print usage and exit |

---

## HNSW parameter guide

The three HNSW parameters directly control the recall/throughput tradeoff.
Understanding them helps you benchmark what actually matters for your workload.

### `--m` — graph connectivity

`M` is the maximum number of bi-directional edges each node can have per layer
(layer 0 uses `2×M`). Higher values produce a denser, better-connected graph.

| M | Effect |
|---|--------|
| 8 | Lower memory, faster builds, slightly lower recall |
| **16** | **Recommended default — good balance** |
| 32 | Higher recall, ~2× memory, slower inserts |
| 64 | Near-perfect recall for clustered data, very high memory |

### `--ef-construction` — build quality

Controls how thoroughly the algorithm searches for neighbours during insertion.
Higher values improve graph quality and recall, at the cost of longer build time.
Must be ≥ M (enforced automatically).

| efConstruction | Effect |
|----------------|--------|
| 50 | Fast builds; acceptable for exploratory benchmarking |
| **200** | **Default — production-quality graph** |
| 400 | Diminishing returns; only useful for very high-recall targets |

### `--ef-search` — query-time recall vs. speed

Controls how many candidates are explored during a query. This is the primary
query-time knob: increase it to trade speed for recall.

| efSearch | Effect |
|----------|--------|
| 10 | Maximum speed; recall may drop below 90% |
| **50** | **Default — good recall with reasonable latency** |
| 100 | Near-exhaustive; approach 99%+ recall |
| 200 | Effectively exact for most datasets |

> **Tip:** To understand the recall/latency tradeoff for your data, run the
> benchmark twice with `--ef-search=20` and `--ef-search=100` and compare the
> Recall@k and P99 columns in the report.

---

## Metrics explained

### Index build throughput (doc/s)

Total documents inserted divided by wall-clock time. Captures the full
`addDocument()` cost: random-level sampling, graph insertion, and neighbour
selection. Scales sub-linearly with N (each insert searches an increasingly
large graph).

### Serial QPS

Total queries divided by total search wall-clock time, measured after a 20-query
warmup. "Serial" means queries are issued one at a time from a single process —
this matches the most common PHP deployment pattern (FPM workers, CLI scripts)
and avoids measuring concurrency overhead that PHP cannot express.

### P99 latency

The 99th-percentile search latency computed with linear interpolation over all
query timings. This tells you what the slowest 1 in 100 queries looks like —
the number that actually affects user experience in production. The report also
includes min, mean, P50, and P95 for a full latency distribution picture.

Timings use PHP's `hrtime(true)` (nanosecond monotonic clock) to avoid
wall-clock noise.

### Recall@k

```
Recall@k = |HNSW top-k ∩ BruteForce top-k| / k
```

Averaged over `--recall-samples` queries and reported for k=1, k=5, and k=K
(your configured `--k`). The brute-force ground truth uses exact cosine
similarity computed in `BruteForce.php`.

A Recall@10 of 95% means HNSW returns 9–10 of the true 10 nearest neighbours
on average. Values below ~80% suggest `efSearch` is too low for the dataset, or
`M`/`efConstruction` need increasing.

### Persistence (save / open)

Wall-clock time and throughput (MB/s) for `VectorDatabase::save()` and
`VectorDatabase::open()`. `save()` waits for any outstanding async document
writes, then flushes `hnsw.bin` and `bm25.bin`. `open()` reads only those two
index files; individual document files (`docs/{n}.bin`) are loaded lazily after
search, so open time is typically fast regardless of document count.

---

## Methodology

The benchmark follows VectorDBBench's core principles:

1. **Reproducible data.** All vectors are generated deterministically from
   `--seed`. The same seed always produces the same dataset, making runs
   comparable across machines and PHP versions.

2. **Disjoint query set.** Query vectors are generated from the same seed
   sequence but are *not* inserted into the index, so there are no trivially
   exact matches.

3. **Warmup before measurement.** 20 queries are issued before latency
   measurement begins to eliminate cold-start effects (opcode cache, CPU branch
   predictor).

4. **Serial throughput, not burst.** QPS is measured over the full `--queries`
   run, not a short peak window. This reflects sustained throughput rather than
   best-case behaviour.

5. **Tail latency focus.** P99 is highlighted in the summary table because
   average latency masks outliers that real users experience.

6. **Exact recall ground truth.** `BruteForce.php` computes cosine similarity
   exhaustively over all N vectors for each recall query — the same distance
   metric and ranking order as the HNSW index. There is no approximation in the
   ground truth.

---

## Recipes

### Fastest possible run (smoke test)

```bash
php benchmark/benchmark.php --scenarios=xs --queries=50 --recall-samples=10
```

### Compare two efSearch values

```bash
php benchmark/benchmark.php --scenarios=small --ef-search=20  --output=report-ef20.md
php benchmark/benchmark.php --scenarios=small --ef-search=100 --output=report-ef100.md
```

Diff the two files to see the recall vs. latency tradeoff.

### High-recall configuration

```bash
php benchmark/benchmark.php \
  --scenarios=small,medium \
  --m=32 \
  --ef-construction=400 \
  --ef-search=100 \
  --output=report-highrecall.md
```

### Large dataset without recall (brute-force would be slow)

```bash
php -d memory_limit=1G benchmark/benchmark.php \
  --scenarios=large \
  --no-recall \
  --queries=500 \
  --output=report-large.md
```

### Persistence-only benchmark (skip search and recall)

```bash
php benchmark/benchmark.php \
  --scenarios=small,medium \
  --no-recall \
  --queries=1 \
  --output=report-persist.md
```

### Reproducible CI run

```bash
php benchmark/benchmark.php \
  --scenarios=xs,small \
  --seed=42 \
  --queries=200 \
  --recall-samples=50 \
  --output=benchmark-results.md
```

Using a fixed `--seed` guarantees identical datasets across runs, making it safe
to diff reports committed to version control.

---

## Reading the report

The generated Markdown has two sections: a **summary table** and **per-scenario
detail**.

### Summary table

```
| Scenario | Vectors | Dims | Build time | Insert/s | QPS | P99 ms | Recall@10 |
```

- **Build time** — wall-clock duration of the full index build
- **Insert/s** — average document ingestion rate
- **QPS** — serial queries per second (search only, after warmup)
- **P99 ms** — 99th-percentile query latency in milliseconds
- **Recall@10** — fraction of true top-10 results returned on average

### Per-scenario sections

Each scenario expands into four subsections:

**Index build** — vectors inserted, build time, throughput, process RSS after build.

**Search latency** — full latency distribution: min, mean, P50, P95, P99, max.
The spread between P50 and P99 indicates how consistent query times are. A large
gap (e.g. P50=2ms, P99=20ms) suggests HNSW is occasionally traversing many more
nodes for some queries.

**Recall** — breakdown at k=1, k=5, and k=K. Recall@1 is always ≥ Recall@k
because finding the single nearest neighbour is easier than finding the top-K.

**Persistence** — total folder size on disk, time and throughput for `save()` and
`open()`. Useful for planning deployment workflows where the index is built once
and served from disk on each restart. `open()` time is typically much faster than
`save()` because document files are not read eagerly.

---

## Interpreting results

### Good vs. concerning numbers

| Metric | Healthy | Worth investigating |
|--------|---------|---------------------|
| Recall@10 | ≥ 95% | < 85% — increase `efSearch` or `M` |
| P99 / P50 ratio | < 3× | > 5× — graph may have poor connectivity |
| Build throughput | Consistent with N·log(N) growth | Sudden drops may indicate GC pressure |
| save() throughput | > 50 MB/s | Lower suggests filesystem bottleneck |

### The recall/speed tradeoff

HNSW is an *approximate* algorithm. Every parameter change that improves recall
also increases latency, and vice versa. The typical workflow is:

1. Run with default parameters to establish a baseline.
2. Identify whether recall or latency is the bottleneck.
3. If recall is low: increase `--ef-search` first (cheapest), then `--m`.
4. If latency is high: decrease `--ef-search`; consider smaller `--m` if memory
   is also a concern.

### PHP-specific context

PHPVector is a pure-PHP implementation. QPS and build throughput will be
significantly lower than compiled systems (Qdrant, Weaviate, FAISS). The
benchmark makes this explicit: its purpose is to characterise *this library's*
performance envelope, not to compare against other runtimes.

Recall@k and the recall/latency tradeoff curve, however, are algorithm properties
independent of language — they should match reference HNSW implementations given
the same M and efSearch values.

---

## File layout

```
benchmark/
├── run.php                 # CLI entry point for a benchmark run
├── compare.php             # CLI entry point for a baseline comparison
├── Benchmark.php           # Scenario execution and measurement
├── BruteForce.php          # Exact cosine NN for ground-truth recall
├── Stats.php               # Percentile / latency statistics helpers
├── ResultFormatter.php     # Markdown and GitHub benchmark output
└── BenchmarkComparator.php # Baseline versus current comparison report
```

All helper classes live under the `PHPVector\Benchmark` namespace.

## Reading the pull request comparison

Every pull request gets a `Benchmark Comparison` comment. It compares the run
on the branch against the baseline stored on `gh-pages`, which is refreshed by
every push to the default branch.

Rows are grouped into one table per scenario, and each value carries its unit.
The delta is always the change from baseline to current, so a negative delta on
a throughput metric means the branch got slower, while a negative delta on a
memory or disk metric means it got leaner.

| Status | Meaning |
|--------|---------|
| 🟢 | No regression on this metric |
| 🟠 | Regression within the warning threshold (5% by default) |
| 🔴 | Regression beyond the threshold |
| 🆕 | Present in this run, absent from the baseline |
| ➖ | Present in the baseline, absent from this run |

Metrics without a counterpart carry no comparison, so they are collapsed into a
details block rather than mixed in with the real rows.

If the two runs share no metric names at all, the report says **No metrics could
be compared** instead of reporting success. That situation is not a pass: a
regression of any size would go unnoticed. It usually means the baseline
predates a change in metric naming, and it resolves on the next push to the
default branch.
