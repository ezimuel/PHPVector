<?php

declare(strict_types=1);

namespace PHPVector\Benchmark;

use PHPVector\HNSW\Config as HNSWConfig;

final class ResultFormatter
{
    /**
     * @param array<string, array<string, mixed>> $results
     * @return array<int, array{name: string, unit: string, value: float}>
     */
    public static function toGitHubBenchmark(array $results): array
    {
        $formatted = [];

        foreach ($results as $scenarioKey => $scenarioResults) {
            $prefix = count($results) > 1 ? "{$scenarioKey}/" : '';

            foreach ($scenarioResults as $opName => $metrics) {
                if ($opName === 'scenario' || $opName === 'recall') {
                    continue;
                }

                if ($opName === 'persistence') {
                    foreach ($metrics as $subOp => $subMetrics) {
                        if (isset($subMetrics['throughput_mb_s'])) {
                            $formatted[] = [
                                'name' => "{$prefix}{$subOp} (MB/s)",
                                'unit' => 'MB/s',
                                'value' => round($subMetrics['throughput_mb_s'], 2),
                            ];
                        }
                        if (isset($subMetrics['disk_size_mb'])) {
                            $formatted[] = [
                                'name' => "{$prefix}{$subOp} (disk size)",
                                'unit' => 'MB',
                                'value' => round($subMetrics['disk_size_mb'], 2),
                            ];
                        }
                        if (isset($subMetrics['memory_delta_mb'])) {
                            $formatted[] = [
                                'name' => "{$prefix}{$subOp} (memory delta)",
                                'unit' => 'MB',
                                'value' => round($subMetrics['memory_delta_mb'], 2),
                            ];
                        }
                    }
                    continue;
                }

                if (isset($metrics['ops_per_second'])) {
                    $formatted[] = [
                        'name' => "{$prefix}{$opName} (ops/s)",
                        'unit' => 'ops/s',
                        'value' => round($metrics['ops_per_second'], 2),
                    ];
                }
                if (isset($metrics['qps'])) {
                    $formatted[] = [
                        'name' => "{$prefix}{$opName} (QPS)",
                        'unit' => 'queries/s',
                        'value' => round($metrics['qps'], 2),
                    ];
                }
                if (isset($metrics['memory_delta_mb'])) {
                    $formatted[] = [
                        'name' => "{$prefix}{$opName} (memory delta)",
                        'unit' => 'MB',
                        'value' => round($metrics['memory_delta_mb'], 2),
                    ];
                }
            }
        }

        return $formatted;
    }

    /**
     * @param array<string, array<string, mixed>> $results
     * @param array{k: int, queries: int, recall_samples: int} $options
     */
    public static function toMarkdown(
        array $results,
        HNSWConfig $hnswConfig,
        array $options,
    ): string {
        $k = $options['k'];
        $queries = $options['queries'];
        $recallSamples = $options['recall_samples'];

        $lines = [];

        // Header
        $lines[] = '# PHPVector Benchmark Report';
        $lines[] = '';
        $lines[] = sprintf('> **Generated:** %s  ', date('Y-m-d H:i:s'));
        $lines[] = sprintf('> **PHP:** %s  ', PHP_VERSION);
        $lines[] = sprintf('> **OS:** %s %s  ', PHP_OS_FAMILY, php_uname('m'));
        $lines[] = sprintf(
            '> **HNSW config:** M=%d efConstruction=%d efSearch=%d distance=%s',
            $hnswConfig->M,
            $hnswConfig->efConstruction,
            $hnswConfig->efSearch,
            $hnswConfig->distance->name,
        );
        $lines[] = sprintf(
            '> **Queries:** %s per scenario top-%d %s recall samples',
            number_format($queries), $k, number_format($recallSamples),
        );
        $lines[] = '';

        // Summary table
        $lines[] = '## Summary';
        $lines[] = '';

        $hasRecall = false;
        $hasPersist = false;
        $hasTextSearch = false;
        foreach ($results as $r) {
            if (isset($r['recall'])) {
                $hasRecall = true;
            }
            if (isset($r['persistence'])) {
                $hasPersist = true;
            }
            if (isset($r['text_search'])) {
                $hasTextSearch = true;
            }
        }

        $headers = ['Scenario', 'Vectors', 'Dims', 'Build time', 'Insert/s', 'Vector QPS', 'P99 ms'];
        if ($hasTextSearch) {
            $headers[] = 'Text QPS';
        }
        if ($hasRecall) {
            $headers[] = "Recall@{$k}";
        }

        $lines[] = '| ' . implode(' | ', $headers) . ' |';
        $lines[] = '|' . implode('|', array_fill(0, count($headers), '---')) . '|';

        foreach ($results as $r) {
            $s = $r['scenario'];
            $row = [
                "**{$s['label']}** - {$s['desc']}",
                number_format($s['n']),
                (string) $s['dims'],
                self::fmtTime($r['insert']['total_time_s']),
                number_format((int) $r['insert']['ops_per_second']),
                number_format((int) $r['vector_search']['qps']),
                number_format($r['vector_search']['latency_p99_ms'], 1),
            ];

            if ($hasTextSearch) {
                $row[] = isset($r['text_search'])
                    ? number_format((int) $r['text_search']['qps'])
                    : '-';
            }
            if ($hasRecall) {
                $row[] = isset($r['recall'])
                    ? number_format($r['recall'][$k] * 100.0, 1) . '%'
                    : '-';
            }

            $lines[] = '| ' . implode(' | ', $row) . ' |';
        }

        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';

        // Per-scenario detail
        foreach ($results as $r) {
            $s = $r['scenario'];
            $lines[] = "## {$s['label']} - {$s['desc']}";
            $lines[] = '';

            // Insert
            $lines[] = '### Insert';
            $lines[] = '';
            $lines[] = '| Metric | Value |';
            $lines[] = '|--------|-------|';
            $lines[] = sprintf('| Vectors inserted | %s |', number_format($s['n']));
            $lines[] = sprintf('| Build time | %s |', self::fmtTime($r['insert']['total_time_s']));
            $lines[] = sprintf('| Throughput | %s doc/s |', number_format((int) $r['insert']['ops_per_second']));
            $lines[] = sprintf('| Memory delta | %s |', self::fmtMb($r['insert']['memory_delta_mb']));
            $lines[] = '';

            // Vector search
            $lines[] = sprintf('### Vector search (%s queries, k=%d)', number_format($queries), $k);
            $lines[] = '';
            $lines[] = '| Metric | Value |';
            $lines[] = '|--------|-------|';
            $lines[] = sprintf('| QPS | %s |', number_format((int) $r['vector_search']['qps']));
            $lines[] = sprintf('| P50 | %.2f ms |', $r['vector_search']['latency_p50_ms']);
            $lines[] = sprintf('| P95 | %.2f ms |', $r['vector_search']['latency_p95_ms']);
            $lines[] = sprintf('| P99 | %.2f ms |', $r['vector_search']['latency_p99_ms']);
            $lines[] = '';

            // Text search
            if (isset($r['text_search'])) {
                $lines[] = sprintf('### Text search (%s queries)', number_format($queries));
                $lines[] = '';
                $lines[] = '| Metric | Value |';
                $lines[] = '|--------|-------|';
                $lines[] = sprintf('| QPS | %s |', number_format((int) $r['text_search']['qps']));
                $lines[] = sprintf('| P50 | %.2f ms |', $r['text_search']['latency_p50_ms']);
                $lines[] = sprintf('| P95 | %.2f ms |', $r['text_search']['latency_p95_ms']);
                $lines[] = sprintf('| P99 | %.2f ms |', $r['text_search']['latency_p99_ms']);
                $lines[] = '';
            }

            // Hybrid search
            if (isset($r['hybrid_search'])) {
                $lines[] = sprintf('### Hybrid search (%s queries)', number_format($queries));
                $lines[] = '';
                $lines[] = '| Metric | Value |';
                $lines[] = '|--------|-------|';
                $lines[] = sprintf('| QPS | %s |', number_format((int) $r['hybrid_search']['qps']));
                $lines[] = sprintf('| P50 | %.2f ms |', $r['hybrid_search']['latency_p50_ms']);
                $lines[] = sprintf('| P95 | %.2f ms |', $r['hybrid_search']['latency_p95_ms']);
                $lines[] = sprintf('| P99 | %.2f ms |', $r['hybrid_search']['latency_p99_ms']);
                $lines[] = '';
            }

            // Update
            if (isset($r['update'])) {
                $lines[] = '### Update';
                $lines[] = '';
                $lines[] = '| Metric | Value |';
                $lines[] = '|--------|-------|';
                $lines[] = sprintf('| Operations | %s |', number_format($r['update']['operations']));
                $lines[] = sprintf('| Throughput | %s ops/s |', number_format((int) $r['update']['ops_per_second']));
                $lines[] = '';
            }

            // Delete
            if (isset($r['delete'])) {
                $lines[] = '### Delete';
                $lines[] = '';
                $lines[] = '| Metric | Value |';
                $lines[] = '|--------|-------|';
                $lines[] = sprintf('| Operations | %s |', number_format($r['delete']['operations']));
                $lines[] = sprintf('| Throughput | %s ops/s |', number_format((int) $r['delete']['ops_per_second']));
                $lines[] = '';
            }

            // Recall
            if (isset($r['recall'])) {
                $lines[] = sprintf('### Recall (%s samples)', number_format($recallSamples));
                $lines[] = '';
                $lines[] = '| k | Recall |';
                $lines[] = '|---|--------|';
                foreach ($r['recall'] as $kv => $recall) {
                    $lines[] = sprintf('| %d | %.1f%% |', $kv, $recall * 100.0);
                }
                $lines[] = '';
            }

            // Persistence
            if (isset($r['persistence'])) {
                $p = $r['persistence'];
                $lines[] = '### Persistence';
                $lines[] = '';
                $lines[] = '| Operation | Disk size | Time | Throughput |';
                $lines[] = '|-----------|-----------|------|------------|';
                $lines[] = sprintf('| `save()` | %s | %s | %.1f MB/s |',
                    self::fmtMb($p['save']['disk_size_mb']),
                    self::fmtTime($p['save']['total_time_s']),
                    $p['save']['throughput_mb_s'],
                );
                $lines[] = sprintf('| `open()` | %s | %s | %.1f MB/s |',
                    self::fmtMb($p['save']['disk_size_mb']),
                    self::fmtTime($p['open']['total_time_s']),
                    $p['open']['throughput_mb_s'],
                );
                $lines[] = '';
            }

            $lines[] = '---';
            $lines[] = '';
        }

        $lines[] = '*Benchmark methodology follows [VectorDBBench](https://github.com/zilliztech/VectorDBBench): '
            . 'serial QPS, P99 tail latency, and Recall@k against brute-force ground truth on synthetic '
            . 'unit-normalised vectors (reproducible seed).*';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private static function fmtTime(float $s): string
    {
        if ($s >= 60.0) {
            return sprintf('%dm %ds', (int) ($s / 60), (int) fmod($s, 60));
        }
        if ($s >= 1.0) {
            return number_format($s, 2) . ' s';
        }
        if ($s >= 0.001) {
            return number_format($s * 1_000, 0) . ' ms';
        }
        return number_format($s * 1_000_000, 0) . ' us';
    }

    private static function fmtMb(float $mb): string
    {
        if ($mb >= 1_024.0) {
            return number_format($mb / 1_024.0, 2) . ' GB';
        }
        return number_format($mb, 1) . ' MB';
    }
}
