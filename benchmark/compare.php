#!/usr/bin/env php
<?php

/**
 * PHPVector Benchmark Comparator
 *
 * Compares two benchmark JSON files and outputs a markdown comparison table
 * with color-coded status indicators (green/orange/red).
 *
 * Usage:
 *   php benchmark/compare.php --baseline=base.json --current=pr.json [options]
 *
 * Options:
 *   --baseline=<file>    Path to baseline benchmark JSON
 *   --current=<file>     Path to current benchmark JSON
 *   --threshold=<n>      Warning threshold percentage (default: 5.0)
 *   --output=<file>      Write output to file (default: stdout)
 *   --help, -h           Show this help
 */

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    fwrite(STDERR, "Error: vendor/autoload.php not found - run 'composer install' first.\n");
    exit(1);
}
require $autoload;

use PHPVector\Benchmark\BenchmarkComparator;

$opts = getopt('h', ['baseline:', 'current:', 'threshold:', 'output:', 'help']);

if (isset($opts['help']) || isset($opts['h'])) {
    $src = file_get_contents(__FILE__);
    if (preg_match('#/\*\*(.*?)\*/#s', $src, $m)) {
        echo preg_replace('/^\s*\* ?/m', '', trim($m[1]));
        echo "\n";
    }
    exit(0);
}

$baselineFile = $opts['baseline'] ?? null;
$currentFile = $opts['current'] ?? null;

if ($baselineFile === null || $currentFile === null) {
    fwrite(STDERR, "Error: --baseline and --current are required.\n");
    fwrite(STDERR, "Usage: php benchmark/compare.php --baseline=base.json --current=pr.json\n");
    exit(1);
}

$threshold = (float) ($opts['threshold'] ?? 5.0);
$outputFile = $opts['output'] ?? null;

// Load baseline (empty array if file missing or invalid)
$baseline = [];
if (file_exists($baselineFile)) {
    $content = file_get_contents($baselineFile);
    if ($content !== false) {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $baseline = $decoded;
        }
    }
}

// Load current (required)
if (!file_exists($currentFile)) {
    fwrite(STDERR, "Error: current benchmark file not found: {$currentFile}\n");
    exit(1);
}

$content = file_get_contents($currentFile);
if ($content === false) {
    fwrite(STDERR, "Error: could not read {$currentFile}\n");
    exit(1);
}

$current = json_decode($content, true);
if (!is_array($current)) {
    fwrite(STDERR, "Error: invalid JSON in {$currentFile}\n");
    exit(1);
}

// Compare
$markdown = BenchmarkComparator::compare($baseline, $current, $threshold);

// Output
if ($outputFile !== null) {
    if (file_put_contents($outputFile, $markdown) === false) {
        fwrite(STDERR, "Error: could not write to {$outputFile}\n");
        exit(1);
    }
    fwrite(STDERR, "Comparison written to: {$outputFile}\n");
} else {
    echo $markdown;
}
