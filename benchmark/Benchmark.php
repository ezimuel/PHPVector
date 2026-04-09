<?php

declare(strict_types=1);

namespace PHPVector\Benchmark;

use FilesystemIterator;
use PHPVector\BM25\Config as BM25Config;
use PHPVector\BM25\SimpleTokenizer;
use PHPVector\Document;
use PHPVector\HNSW\Config as HNSWConfig;
use PHPVector\VectorDatabase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class Benchmark
{
    private const SCENARIOS = [
        'xs'      => ['n' =>   1_000, 'dims' =>  128, 'label' => 'XS',  'desc' => '1 K x 128d'],
        'small'   => ['n' =>  10_000, 'dims' =>  128, 'label' => 'S',   'desc' => '10 K x 128d'],
        'medium'  => ['n' =>  50_000, 'dims' =>  128, 'label' => 'M',   'desc' => '50 K x 128d'],
        'large'   => ['n' => 100_000, 'dims' =>  128, 'label' => 'L',   'desc' => '100 K x 128d'],
        'highdim' => ['n' =>  10_000, 'dims' =>  768, 'label' => 'HD',  'desc' => '10 K x 768d'],
    ];

    private const PROFILES = [
        'quick' => [
            'vector_search' => true,
            'text_search'   => false,
            'hybrid_search' => false,
            'update'        => false,
            'delete'        => false,
            'persistence'   => false,
            'recall'        => false,
        ],
        'full' => [
            'vector_search' => true,
            'text_search'   => true,
            'hybrid_search' => true,
            'update'        => true,
            'delete'        => true,
            'persistence'   => true,
            'recall'        => 'auto',
        ],
        'ci' => [
            'vector_search' => true,
            'text_search'   => true,
            'hybrid_search' => true,
            'update'        => true,
            'delete'        => true,
            'persistence'   => true,
            'recall'        => false,
        ],
    ];

    private const VOCABULARY = [
        'alpha', 'beta', 'gamma', 'delta', 'epsilon', 'zeta', 'eta', 'theta',
        'iota', 'kappa', 'lambda', 'mu', 'nu', 'xi', 'omicron', 'pi', 'rho',
        'sigma', 'tau', 'upsilon', 'phi', 'chi', 'psi', 'omega',
    ];

    private const SEARCH_TERMS = [
        'alpha beta',
        'gamma delta epsilon',
        'omega psi chi',
        'sigma tau',
        'lambda mu nu',
        'theta iota kappa',
        'zeta eta',
        'rho omicron',
    ];

    /** @var array<string, array{n: int, dims: int, label: string, desc: string}> */
    private array $scenarios;

    /** @var array<string, bool|string> */
    private array $profile;

    public function __construct(
        private readonly HNSWConfig $hnswConfig,
        private readonly array $scenarioKeys,
        string $profileName = 'full',
        private readonly int $k = 10,
        private readonly int $queries = 200,
        private readonly int $recallSamples = 50,
        private readonly int $recallThreshold = 50_000,
        private readonly int $seed = 42,
        private readonly ?int $documentOverride = null,
        private readonly ?int $dimensionOverride = null,
        private readonly ?bool $recallOverride = null,
        private readonly ?bool $persistOverride = null,
    ) {
        $this->profile = self::PROFILES[$profileName]
            ?? throw new \InvalidArgumentException("Unknown profile: {$profileName}");

        $this->scenarios = [];
        foreach ($this->scenarioKeys as $key) {
            if (!isset(self::SCENARIOS[$key])) {
                throw new \InvalidArgumentException("Unknown scenario: {$key}");
            }
            $scenario = self::SCENARIOS[$key];
            if ($this->documentOverride !== null) {
                $scenario['n'] = $this->documentOverride;
            }
            if ($this->dimensionOverride !== null) {
                $scenario['dims'] = $this->dimensionOverride;
            }
            $this->scenarios[$key] = $scenario;
        }
    }

    /** @return array<string, string> */
    public static function availableScenarios(): array
    {
        return array_map(
            static fn(array $s): string => $s['desc'],
            self::SCENARIOS,
        );
    }

    /** @return string[] */
    public static function availableProfiles(): array
    {
        return array_keys(self::PROFILES);
    }

    /** @return array<string, array<string, mixed>> */
    public function run(): array
    {
        $allResults = [];
        foreach ($this->scenarios as $key => $scenario) {
            $allResults[$key] = $this->runScenario($key, $scenario);
        }
        return $allResults;
    }

    // ── Scenario runner ──────────────────────────────────────────────────

    /** @param array{n: int, dims: int, label: string, desc: string} $scenario */
    private function runScenario(string $key, array $scenario): array
    {
        $n = $scenario['n'];
        $dims = $scenario['dims'];

        $this->log(sprintf("\n[%s] %s\n", strtoupper($key), $scenario['desc']));

        // 1. Generate data
        $this->log("  Generating {$n} vectors ({$dims}d)...\n");
        [$dataVectors, $queryVectors, $documents] = $this->generateData($n, $dims);

        // 2. Insert
        $this->log("  Building index...\n");
        $insertMeasurement = $this->measure(function () use ($documents, $n): VectorDatabase {
            $db = new VectorDatabase(
                $this->hnswConfig,
                new BM25Config(),
                new SimpleTokenizer([]),
            );
            foreach ($documents as $i => $doc) {
                $db->addDocument($doc);
                if ($i > 0 && $i % 5_000 === 0) {
                    $this->log("    {$i}/{$n}\r");
                }
            }
            $this->log("    {$n}/{$n}   \n");
            return $db;
        });

        /** @var VectorDatabase $db */
        $db = $insertMeasurement['result'];

        $results = [
            'scenario' => $scenario,
            'insert' => [
                'operations' => $n,
                'total_time_s' => $insertMeasurement['elapsed_seconds'],
                'ops_per_second' => $n / $insertMeasurement['elapsed_seconds'],
                'memory_delta_mb' => $insertMeasurement['memory_delta_mb'],
                'memory_current_mb' => $insertMeasurement['memory_current_mb'],
            ],
        ];

        // 3. Warmup
        $warmup = min(20, $this->queries);
        for ($i = 0; $i < $warmup; $i++) {
            $db->vectorSearch($queryVectors[$i], $this->k);
        }

        // 4. Vector search
        $this->log("  Vector search ({$this->queries} queries)...\n");
        $vsm = $this->measureSearchLatencies(
            fn(array $v) => $db->vectorSearch($v, $this->k),
            $queryVectors,
        );
        $results['vector_search'] = $this->buildSearchMetrics($vsm);

        // Conditional operations based on profile
        if ($this->isEnabled('text_search')) {
            $results['text_search'] = $this->benchmarkTextSearch($db);
        }
        if ($this->isEnabled('hybrid_search')) {
            $results['hybrid_search'] = $this->benchmarkHybridSearch($db, $queryVectors);
        }
        if ($this->isEnabled('update')) {
            $results['update'] = $this->benchmarkUpdate($db, $n, $dims);
        }
        if ($this->isEnabled('delete')) {
            $results['delete'] = $this->benchmarkDelete($db, $n);
        }
        if ($this->isRecallEnabled($n)) {
            $results['recall'] = $this->benchmarkRecall($db, $dataVectors, $queryVectors);
        }
        if ($this->isEnabled('persistence')) {
            $results['persistence'] = $this->benchmarkPersistence($documents, $n);
        }

        $this->log("  Done.\n");

        return $results;
    }

    // ── Profile checks ───────────────────────────────────────────────────

    private function isEnabled(string $operation): bool
    {
        if ($operation === 'persistence' && $this->persistOverride !== null) {
            return $this->persistOverride;
        }
        $val = $this->profile[$operation] ?? false;
        return $val === true;
    }

    private function isRecallEnabled(int $n): bool
    {
        if ($this->recallOverride !== null) {
            return $this->recallOverride;
        }
        $val = $this->profile['recall'] ?? false;
        if ($val === 'auto') {
            return $n <= $this->recallThreshold;
        }
        return $val === true;
    }

    // ── Operation benchmarks ─────────────────────────────────────────────

    private function benchmarkTextSearch(VectorDatabase $db): array
    {
        $this->log("  Text search ({$this->queries} queries)...\n");

        $queries = array_map(
            fn(int $i): string => self::SEARCH_TERMS[$i % count(self::SEARCH_TERMS)],
            range(0, $this->queries - 1),
        );

        $m = $this->measureSearchLatencies(
            fn(string $q) => $db->textSearch($q, $this->k),
            $queries,
        );

        return $this->buildSearchMetrics($m);
    }

    /** @param float[][] $queryVectors */
    private function benchmarkHybridSearch(VectorDatabase $db, array $queryVectors): array
    {
        $this->log("  Hybrid search ({$this->queries} queries)...\n");

        $m = $this->measure(function () use ($db, $queryVectors): array {
            $latencies = [];
            foreach ($queryVectors as $i => $vector) {
                $text = self::SEARCH_TERMS[$i % count(self::SEARCH_TERMS)];
                $t0 = hrtime(true);
                $db->hybridSearch($vector, $text, $this->k);
                $latencies[] = (hrtime(true) - $t0) / 1e6;
            }
            sort($latencies);
            return $latencies;
        });

        return $this->buildSearchMetrics([
            'latencies_ms' => $m['result'],
            'elapsed_seconds' => $m['elapsed_seconds'],
            'memory_delta_mb' => $m['memory_delta_mb'],
            'memory_current_mb' => $m['memory_current_mb'],
        ]);
    }

    private function benchmarkUpdate(VectorDatabase $db, int $n, int $dims): array
    {
        $count = $this->calculateOperationCount($n);
        $this->log("  Update ({$count} documents)...\n");

        $m = $this->measure(function () use ($db, $count, $dims): void {
            foreach (range(0, $count - 1) as $i) {
                $db->updateDocument(new Document(
                    id: $i,
                    vector: $this->generateNormalizedVector($dims),
                    text: $this->generateRandomText(),
                    metadata: ['category' => 99, 'updated' => true],
                ));
            }
        });

        return [
            'operations' => $count,
            'total_time_s' => $m['elapsed_seconds'],
            'ops_per_second' => $count / $m['elapsed_seconds'],
            'memory_delta_mb' => $m['memory_delta_mb'],
            'memory_current_mb' => $m['memory_current_mb'],
        ];
    }

    private function benchmarkDelete(VectorDatabase $db, int $n): array
    {
        $count = $this->calculateOperationCount($n);
        $start = $n - $count;
        $this->log("  Delete ({$count} documents)...\n");

        $m = $this->measure(function () use ($db, $start, $n): void {
            foreach (range($start, $n - 1) as $i) {
                $db->deleteDocument($i);
            }
        });

        return [
            'operations' => $count,
            'total_time_s' => $m['elapsed_seconds'],
            'ops_per_second' => $count / $m['elapsed_seconds'],
            'memory_delta_mb' => $m['memory_delta_mb'],
            'memory_current_mb' => $m['memory_current_mb'],
        ];
    }

    /**
     * @param float[][] $dataVectors
     * @param float[][] $queryVectors
     * @return array<int, float>
     */
    private function benchmarkRecall(VectorDatabase $db, array $dataVectors, array $queryVectors): array
    {
        $nRecall = min($this->recallSamples, $this->queries);
        $this->log("  Computing Recall@{$this->k} over {$nRecall} queries...\n");

        $bf = new BruteForce();
        $n = count($dataVectors);
        for ($i = 0; $i < $n; $i++) {
            $bf->add($i, $dataVectors[$i]);
        }

        $kValues = array_values(array_unique([1, min(5, $this->k), $this->k]));
        $totals = array_fill_keys($kValues, 0.0);

        for ($i = 0; $i < $nRecall; $i++) {
            $q = $queryVectors[$i];
            $bfIds = $bf->search($q, $this->k);
            $hnswIds = array_map(
                static fn($r) => $r->document->id,
                $db->vectorSearch($q, $this->k),
            );

            foreach ($kValues as $kv) {
                $bfSlice = array_slice($bfIds, 0, $kv);
                $hnswSlice = array_slice($hnswIds, 0, $kv);
                $totals[$kv] += count(array_intersect($bfSlice, $hnswSlice)) / $kv;
            }
        }

        return array_map(static fn(float $t): float => $t / $nRecall, $totals);
    }

    /**
     * @param Document[] $documents
     */
    private function benchmarkPersistence(array $documents, int $n): array
    {
        $this->log("  Benchmarking save/open...\n");

        $tmpDir = sys_get_temp_dir() . '/phpvbench_' . uniqid('', true);
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0755, true) && !is_dir($tmpDir)) {
            throw new \RuntimeException(sprintf('Failed to create temporary benchmark directory "%s".', $tmpDir));
        }

        // Save
        $saveMeasurement = $this->measure(function () use ($documents, $tmpDir): void {
            $dbSave = new VectorDatabase(
                $this->hnswConfig,
                new BM25Config(),
                new SimpleTokenizer([]),
                $tmpDir,
            );
            foreach ($documents as $doc) {
                $dbSave->addDocument($doc);
            }
            $dbSave->save();
        });

        $folderSizeMb = $this->folderSizeMb($tmpDir);

        // Open
        $openMeasurement = $this->measure(
            fn() => VectorDatabase::open($tmpDir, $this->hnswConfig),
        );

        $this->rrmdir($tmpDir);

        return [
            'save' => [
                'total_time_s' => $saveMeasurement['elapsed_seconds'],
                'disk_size_mb' => $folderSizeMb,
                'throughput_mb_s' => $saveMeasurement['elapsed_seconds'] > 0.0
                    ? $folderSizeMb / $saveMeasurement['elapsed_seconds'] : 0.0,
                'memory_delta_mb' => $saveMeasurement['memory_delta_mb'],
                'memory_current_mb' => $saveMeasurement['memory_current_mb'],
            ],
            'open' => [
                'total_time_s' => $openMeasurement['elapsed_seconds'],
                'throughput_mb_s' => $openMeasurement['elapsed_seconds'] > 0.0
                    ? $folderSizeMb / $openMeasurement['elapsed_seconds'] : 0.0,
                'memory_delta_mb' => $openMeasurement['memory_delta_mb'],
                'memory_current_mb' => $openMeasurement['memory_current_mb'],
            ],
        ];
    }

    // ── Metrics helpers ──────────────────────────────────────────────────

    private function buildSearchMetrics(array $measurement): array
    {
        $latencies = $measurement['latencies_ms'];
        sort($latencies, SORT_NUMERIC);

        return [
            'operations' => $this->queries,
            'total_time_s' => $measurement['elapsed_seconds'],
            'qps' => $this->queries / $measurement['elapsed_seconds'],
            'latency_p50_ms' => Stats::percentile($latencies, 50.0),
            'latency_p95_ms' => Stats::percentile($latencies, 95.0),
            'latency_p99_ms' => Stats::percentile($latencies, 99.0),
            'memory_delta_mb' => $measurement['memory_delta_mb'],
            'memory_current_mb' => $measurement['memory_current_mb'],
        ];
    }

    // ── Data generation ──────────────────────────────────────────────────

    /**
     * @return array{0: float[][], 1: float[][], 2: Document[]}
     */
    private function generateData(int $n, int $dims): array
    {
        mt_srand($this->seed);

        $dataVectors = [];
        $queryVectors = [];
        $documents = [];
        $total = $n + $this->queries;

        for ($i = 0; $i < $total; $i++) {
            $vector = $this->generateNormalizedVector($dims);

            if ($i < $n) {
                $dataVectors[] = $vector;
                $documents[] = new Document(
                    id: $i,
                    vector: $vector,
                    text: $this->generateRandomText(),
                    metadata: ['category' => $i % 10, 'priority' => $i % 5],
                );
            } else {
                $queryVectors[] = $vector;
            }
        }

        return [$dataVectors, $queryVectors, $documents];
    }

    /** @return float[] */
    private function generateNormalizedVector(int $dims): array
    {
        $v = [];
        $norm = 0.0;
        for ($j = 0; $j < $dims; $j++) {
            $x = (mt_rand() / mt_getrandmax()) * 2.0 - 1.0;
            $v[] = $x;
            $norm += $x * $x;
        }
        $norm = sqrt($norm) ?: 1.0;
        return array_map(static fn(float $x): float => $x / $norm, $v);
    }

    private function generateRandomText(): string
    {
        $wordCount = mt_rand(5, 20);
        return implode(' ', array_map(
            fn(): string => self::VOCABULARY[array_rand(self::VOCABULARY)],
            range(1, $wordCount),
        ));
    }

    // ── Measurement helpers ──────────────────────────────────────────────

    /**
     * @template T
     * @param callable(): T $operation
     * @return array{result: T, elapsed_seconds: float, memory_delta_mb: float, memory_current_mb: float}
     */
    private function measure(callable $operation): array
    {
        gc_collect_cycles();
        $memBefore = memory_get_usage(true);
        $t0 = hrtime(true);

        $result = $operation();

        $memAfter = memory_get_usage(true);

        return [
            'result' => $result,
            'elapsed_seconds' => (hrtime(true) - $t0) / 1e9,
            'memory_delta_mb' => ($memAfter - $memBefore) / (1024 * 1024),
            'memory_current_mb' => $memAfter / (1024 * 1024),
        ];
    }

    /**
     * @param callable(mixed): mixed $searchOp
     * @param array<mixed> $inputs
     * @return array{latencies_ms: float[], elapsed_seconds: float, memory_delta_mb: float, memory_current_mb: float}
     */
    private function measureSearchLatencies(callable $searchOp, array $inputs): array
    {
        $m = $this->measure(function () use ($searchOp, $inputs): array {
            $latencies = [];
            foreach ($inputs as $input) {
                $t0 = hrtime(true);
                $searchOp($input);
                $latencies[] = (hrtime(true) - $t0) / 1e6;
            }
            sort($latencies);
            return $latencies;
        });

        return [
            'latencies_ms' => $m['result'],
            'elapsed_seconds' => $m['elapsed_seconds'],
            'memory_delta_mb' => $m['memory_delta_mb'],
            'memory_current_mb' => $m['memory_current_mb'],
        ];
    }

    private function log(string $msg): void
    {
        fwrite(STDERR, $msg);
    }

    private function calculateOperationCount(int $n): int
    {
        return min(1_000, (int) ($n * 0.1));
    }

    // ── Directory utilities ──────────────────────────────────────────────

    private function folderSizeMb(string $dir): float
    {
        if (!is_dir($dir)) {
            return 0.0;
        }
        $bytes = 0;
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iter as $file) {
            $bytes += $file->getSize();
        }
        return $bytes / (1024 * 1024);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach ((array) glob($dir . '/*') as $item) {
            is_dir((string) $item) ? $this->rrmdir((string) $item) : unlink((string) $item);
        }
        rmdir($dir);
    }
}
