#!/usr/bin/env php
<?php

/**
 * PHPVector Benchmark
 *
 * Unified benchmark for local development and CI pipelines. Measures insert
 * throughput, search QPS/latency, CRUD operations, recall, and persistence.
 *
 * Usage:
 *   php benchmark/run.php [options]
 *
 * Options:
 *   --profile=<name>        quick, full, ci                    (default: full)
 *   --scenarios=<list>      xs,small,medium,large,highdim      (default: xs,small)
 *   --format=<fmt>          markdown, json                     (default: markdown)
 *   --github-bench=<file>   Also write github-bench JSON to file
 *   --output=<file>         Write primary output to file        (default: stdout)
 *   --documents=<n>         Override scenario document count
 *   --dimensions=<n>        Override scenario dimensions
 *   --k=<n>                 Nearest neighbours                  (default: 10)
 *   --queries=<n>           Search queries per scenario          (default: 200)
 *   --recall-samples=<n>    Ground-truth samples                 (default: 50)
 *   --recall-threshold=<n>  Auto-recall doc limit                (default: 50000)
 *   --recall                Force recall on
 *   --no-recall             Force recall off
 *   --no-save               Skip persistence benchmarks
 *   --ef-search=<n>         HNSW efSearch                        (default: 50)
 *   --ef-construction=<n>   HNSW efConstruction                  (default: 200)
 *   --m=<n>                 HNSW M parameter                     (default: 16)
 *   --seed=<n>              Random seed                          (default: 42)
 *   --help, -h              Show this help
 *
 * Profiles:
 *   quick    Insert + vector search only. Fast local sanity check.
 *   full     All operations. Recall auto-enabled below 50K docs.
 *   ci       All operations except recall. Designed for CI pipelines.
 *
 * Examples:
 *   php benchmark/run.php
 *   php benchmark/run.php --profile=quick --scenarios=xs
 *   php benchmark/run.php --profile=ci --scenarios=medium,large --github-bench=results.json
 *   php benchmark/run.php --scenarios=small --recall --format=json --output=report.json
 */

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "Error: vendor/autoload.php not found - run 'composer install' first.\n");
    exit(1);
}
require $autoload;

ini_set('memory_limit', '2G');

use PHPVector\Benchmark\Benchmark;
use PHPVector\Benchmark\ResultFormatter;
use PHPVector\HNSW\Config as HNSWConfig;

// ── CLI parsing ──────────────────────────────────────────────────────────────

$opts = getopt('h', [
    'profile:', 'scenarios:', 'format:', 'github-bench:', 'output:',
    'documents:', 'dimensions:', 'k:', 'queries:', 'recall-samples:',
    'recall-threshold:', 'recall', 'no-recall', 'no-save',
    'ef-search:', 'ef-construction:', 'm:', 'seed:',
    'help',
]);

if (isset($opts['help']) || isset($opts['h'])) {
    $src = file_get_contents(__FILE__);
    if (preg_match('#/\*\*(.*?)\*/#s', $src, $m)) {
        echo preg_replace('/^\s*\* ?/m', '', trim($m[1]));
        echo "\n";
    }
    exit(0);
}

$profileName     = $opts['profile'] ?? 'full';
$scenarioKeys    = array_filter(
    explode(',', $opts['scenarios'] ?? 'xs,small'),
    static fn(string $k): bool => in_array($k, array_keys(Benchmark::availableScenarios()), true),
);

if (empty($scenarioKeys)) {
    fwrite(STDERR, "Error: no valid scenarios. Choose from: "
        . implode(', ', array_keys(Benchmark::availableScenarios())) . "\n");
    exit(1);
}

if (!in_array($profileName, Benchmark::availableProfiles(), true)) {
    fwrite(STDERR, "Error: unknown profile '{$profileName}'. Choose from: "
        . implode(', ', Benchmark::availableProfiles()) . "\n");
    exit(1);
}

$m         = max(2, (int) ($opts['m'] ?? 16));
$efConstr  = max(1, (int) ($opts['ef-construction'] ?? 200));
$efSearch  = max(1, (int) ($opts['ef-search'] ?? 50));

$hnswConfig = new HNSWConfig(
    M: $m,
    efConstruction: max($efConstr, $m),
    efSearch: $efSearch,
);

// Resolve recall override: --recall / --no-recall / null (profile default)
$recallOverride = null;
if (isset($opts['recall'])) {
    $recallOverride = true;
} elseif (isset($opts['no-recall'])) {
    $recallOverride = false;
}

$persistOverride = isset($opts['no-save']) ? false : null;

$k             = max(1, (int) ($opts['k'] ?? 10));
$queries       = max(1, (int) ($opts['queries'] ?? 200));
$recallSamples = max(1, (int) ($opts['recall-samples'] ?? 50));

$benchmark = new Benchmark(
    hnswConfig: $hnswConfig,
    scenarioKeys: $scenarioKeys,
    profileName: $profileName,
    k: $k,
    queries: $queries,
    recallSamples: $recallSamples,
    recallThreshold: max(1, (int) ($opts['recall-threshold'] ?? 50_000)),
    seed: (int) ($opts['seed'] ?? 42),
    documentOverride: isset($opts['documents']) ? max(1, (int) $opts['documents']) : null,
    dimensionOverride: isset($opts['dimensions']) ? max(1, (int) $opts['dimensions']) : null,
    recallOverride: $recallOverride,
    persistOverride: $persistOverride,
);

// ── Run ──────────────────────────────────────────────────────────────────────

$results = $benchmark->run();

// ── Output ───────────────────────────────────────────────────────────────────

$format     = $opts['format'] ?? 'markdown';
$outputFile = $opts['output'] ?? null;
$ghBenchFile = $opts['github-bench'] ?? null;

// Primary output
$primaryOutput = match ($format) {
    'json' => json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
    default => ResultFormatter::toMarkdown($results, $hnswConfig, [
        'k' => $k,
        'queries' => $queries,
        'recall_samples' => $recallSamples,
    ]),
};

if ($outputFile !== null) {
    if (file_put_contents($outputFile, $primaryOutput) === false) {
        fwrite(STDERR, "Error: could not write to {$outputFile}\n");
        exit(1);
    }
    fwrite(STDERR, "Report written to: {$outputFile}\n");
} else {
    echo $primaryOutput;
}

// GitHub benchmark output (independent of primary format)
if ($ghBenchFile !== null) {
    $ghData = json_encode(
        ResultFormatter::toGitHubBenchmark($results),
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    );
    if (file_put_contents($ghBenchFile, $ghData) === false) {
        fwrite(STDERR, "Error: could not write to {$ghBenchFile}\n");
        exit(1);
    }
    fwrite(STDERR, "GitHub benchmark written to: {$ghBenchFile}\n");
}

fwrite(STDERR, "\nBenchmark complete.\n");
