<?php

declare(strict_types=1);

namespace PHPVector\Benchmark;

final class BenchmarkComparator
{
    /**
     * Compare two benchmark result sets and produce a markdown comparison table.
     *
     * @param array<int, array{name: string, unit: string, value: float}> $baseline
     * @param array<int, array{name: string, unit: string, value: float}> $current
     * @param float $warningThreshold Percentage threshold for orange vs red (default: 5.0)
     * @return string Markdown comparison report
     */
    public static function compare(
        array $baseline,
        array $current,
        float $warningThreshold = 5.0,
    ): string {
        if ($baseline === []) {
            return "## Benchmark Comparison\n\n> No baseline available, comparison skipped.\n";
        }

        $baselineMap = [];
        foreach ($baseline as $entry) {
            $baselineMap[$entry['name']] = $entry;
        }

        $currentMap = [];
        foreach ($current as $entry) {
            $currentMap[$entry['name']] = $entry;
        }

        $rows = [];
        $worstStatus = 'green';

        // Compare metrics present in both
        foreach ($currentMap as $name => $cur) {
            if (isset($baselineMap[$name])) {
                $base = $baselineMap[$name];
                $row = self::compareMetric($base, $cur, $warningThreshold);
                $rows[] = $row;
                $worstStatus = self::worsenStatus($worstStatus, $row['status']);
            } else {
                $rows[] = [
                    'name' => $name,
                    'baseline' => '-',
                    'current' => self::formatValue($cur['value'], $cur['unit']),
                    'delta' => '*new*',
                    'emoji' => '🆕',
                    'status' => 'green',
                ];
            }
        }

        // Metrics removed in current
        foreach ($baselineMap as $name => $base) {
            if (!isset($currentMap[$name])) {
                $rows[] = [
                    'name' => $name,
                    'baseline' => self::formatValue($base['value'], $base['unit']),
                    'current' => '-',
                    'delta' => '*removed*',
                    'emoji' => '➖',
                    'status' => 'green',
                ];
            }
        }

        return self::buildMarkdown($rows, $worstStatus, $warningThreshold);
    }

    /**
     * @param array{name: string, unit: string, value: float} $base
     * @param array{name: string, unit: string, value: float} $cur
     * @return array{name: string, baseline: string, current: string, delta: string, emoji: string, status: string}
     */
    private static function compareMetric(array $base, array $cur, float $threshold): array
    {
        $baseVal = $base['value'];
        $curVal = $cur['value'];
        $unit = $cur['unit'];

        if ($baseVal == 0) {
            return [
                'name' => $cur['name'],
                'baseline' => self::formatValue($baseVal, $unit),
                'current' => self::formatValue($curVal, $unit),
                'delta' => 'N/A',
                'emoji' => '🟢',
                'status' => 'green',
            ];
        }

        $deltaPercent = (($curVal - $baseVal) / abs($baseVal)) * 100.0;
        $smallerIsBetter = self::isSmallerBetter($unit);

        // For smaller-is-better metrics, a positive delta is a regression
        $regressionPercent = $smallerIsBetter ? $deltaPercent : -$deltaPercent;

        if ($regressionPercent <= 0.0) {
            $status = 'green';
            $emoji = '🟢';
        } elseif ($regressionPercent <= $threshold) {
            $status = 'orange';
            $emoji = '🟠';
        } else {
            $status = 'red';
            $emoji = '🔴';
        }

        $sign = $deltaPercent >= 0.0 ? '+' : '';

        return [
            'name' => $cur['name'],
            'baseline' => self::formatValue($baseVal, $unit),
            'current' => self::formatValue($curVal, $unit),
            'delta' => sprintf('%s%.1f%%', $sign, $deltaPercent),
            'emoji' => $emoji,
            'status' => $status,
        ];
    }

    private static function isSmallerBetter(string $unit): bool
    {
        return !str_contains($unit, '/');
    }

    private static function formatValue(float $value, string $unit): string
    {
        return number_format($value, 2);
    }

    private static function worsenStatus(string $current, string $new): string
    {
        $order = ['green' => 0, 'orange' => 1, 'red' => 2];

        return ($order[$new] ?? 0) > ($order[$current] ?? 0) ? $new : $current;
    }

    /**
     * @param array<int, array{name: string, baseline: string, current: string, delta: string, emoji: string, status: string}> $rows
     */
    private static function buildMarkdown(array $rows, string $worstStatus, float $threshold): string
    {
        $summary = match ($worstStatus) {
            'red' => sprintf('Significant regressions detected (>%.0f%%)', $threshold),
            'orange' => sprintf('Minor regressions detected (within %.0f%%)', $threshold),
            default => 'All benchmarks passed',
        };

        $summaryEmoji = match ($worstStatus) {
            'red' => '🔴',
            'orange' => '🟠',
            default => '🟢',
        };

        $lines = [];
        $lines[] = '## Benchmark Comparison';
        $lines[] = '';
        $lines[] = sprintf('> %s **%s**', $summaryEmoji, $summary);
        $lines[] = '';
        $lines[] = '| Metric | Baseline | Current | Delta | Status |';
        $lines[] = '|--------|----------|---------|-------|--------|';

        foreach ($rows as $row) {
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s |',
                $row['name'],
                $row['baseline'],
                $row['current'],
                $row['delta'],
                $row['emoji'],
            );
        }

        $lines[] = '';

        return implode("\n", $lines);
    }
}
