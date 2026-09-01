<?php

declare(strict_types=1);

namespace PHPVector\Benchmark;

final class BenchmarkComparator
{
    /**
     * Compare two benchmark result sets and produce a markdown comparison table.
     *
     * Metric names may carry a `{scenario}/` prefix, in which case rows are
     * grouped into one table per scenario. Names without a prefix are rendered
     * in a single unlabelled table.
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
        float $volatileThreshold = 50.0,
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

        $compared = [];
        $unmatched = [];
        $worstStatus = 'green';

        // Metrics present in both: the only rows that carry any signal.
        foreach ($currentMap as $name => $cur) {
            if (isset($baselineMap[$name])) {
                $row = self::compareMetric($baselineMap[$name], $cur, $warningThreshold, $volatileThreshold);
                $compared[] = $row;
                $worstStatus = self::worsenStatus($worstStatus, $row['status']);
            } else {
                $unmatched[] = [
                    'name' => $name,
                    'baseline' => '-',
                    'current' => self::formatValue($cur['value'], $cur['unit']),
                    'delta' => '*new*',
                    'emoji' => '🆕',
                    'status' => 'green',
                ];
            }
        }

        // Metrics that vanished from the current run.
        foreach ($baselineMap as $name => $base) {
            if (!isset($currentMap[$name])) {
                $unmatched[] = [
                    'name' => $name,
                    'baseline' => self::formatValue($base['value'], $base['unit']),
                    'current' => '-',
                    'delta' => '*removed*',
                    'emoji' => '➖',
                    'status' => 'green',
                ];
            }
        }

        return self::buildMarkdown($compared, $unmatched, $worstStatus, $warningThreshold, $volatileThreshold);
    }

    /**
     * @param array{name: string, unit: string, value: float} $base
     * @param array{name: string, unit: string, value: float} $cur
     * @return array{name: string, baseline: string, current: string, delta: string, emoji: string, status: string}
     */
    private static function compareMetric(
        array $base,
        array $cur,
        float $threshold,
        float $volatileThreshold,
    ): array {
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
        $kind = self::classify($unit);

        // For smaller-is-better metrics, a positive delta is a regression
        $regressionPercent = $kind['smallerIsBetter'] ? $deltaPercent : -$deltaPercent;

        if ($regressionPercent <= 0.0) {
            $status = 'green';
            $emoji = '🟢';
        } elseif ($kind['volatile']) {
            // Run to run variance on a shared runner routinely reaches tens of
            // percent with identical code, so a regression under the volatile
            // threshold is reported without colouring the verdict.
            if ($regressionPercent <= $volatileThreshold) {
                $status = 'green';
                $emoji = '📊';
            } else {
                $status = 'red';
                $emoji = '🔴';
            }
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

    /**
     * Known units, with the direction of improvement and whether the metric is
     * steady enough between runs to decide the verdict.
     *
     * Throughput measured on a shared CI runner moves by tens of percent
     * between runs of identical code, so those metrics are reported but do not
     * gate. Memory and disk figures repeat exactly and do.
     *
     * @var array<string, array{smallerIsBetter: bool, volatile: bool}>
     */
    private const UNITS = [
        'ops/s' => ['smallerIsBetter' => false, 'volatile' => true],
        'queries/s' => ['smallerIsBetter' => false, 'volatile' => true],
        'MB/s' => ['smallerIsBetter' => false, 'volatile' => true],
        'MB' => ['smallerIsBetter' => true, 'volatile' => false],
    ];

    /**
     * Classify a unit. An unregistered one falls back to the rate separator for
     * direction and is treated as steady, so a metric nobody classified still
     * reaches the verdict instead of slipping past it unnoticed.
     *
     * @return array{smallerIsBetter: bool, volatile: bool}
     */
    private static function classify(string $unit): array
    {
        return self::UNITS[$unit] ?? [
            'smallerIsBetter' => !str_contains($unit, '/'),
            'volatile' => false,
        ];
    }

    private static function formatValue(float $value, string $unit): string
    {
        return $unit === ''
            ? number_format($value, 2)
            : number_format($value, 2) . ' ' . $unit;
    }

    private static function worsenStatus(string $current, string $new): string
    {
        $order = ['green' => 0, 'orange' => 1, 'red' => 2];

        return ($order[$new] ?? 0) > ($order[$current] ?? 0) ? $new : $current;
    }

    /**
     * Split `{scenario}/{metric}` into its two parts.
     *
     * A slash inside the trailing unit label (`save (MB/s)`) is not a scenario
     * separator, so only a slash occurring before the first parenthesis counts.
     *
     * @return array{0: string, 1: string} scenario ('' when absent) and label
     */
    private static function splitScenario(string $name): array
    {
        $slash = strpos($name, '/');
        $paren = strpos($name, '(');

        if ($slash === false || ($paren !== false && $slash > $paren)) {
            return ['', $name];
        }

        return [substr($name, 0, $slash), substr($name, $slash + 1)];
    }

    /**
     * Group rows by scenario, preserving first-seen order.
     *
     * @param array<int, array{name: string, baseline: string, current: string, delta: string, emoji: string, status: string}> $rows
     * @return array<string, array<int, array{label: string, baseline: string, current: string, delta: string, emoji: string}>>
     */
    private static function groupByScenario(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            [$scenario, $label] = self::splitScenario($row['name']);
            $grouped[$scenario][] = [
                'label' => $label,
                'baseline' => $row['baseline'],
                'current' => $row['current'],
                'delta' => $row['delta'],
                'emoji' => $row['emoji'],
            ];
        }

        return $grouped;
    }

    /**
     * @param array<int, array{label: string, baseline: string, current: string, delta: string, emoji: string}> $rows
     * @return list<string>
     */
    private static function renderTable(array $rows): array
    {
        $lines = [];
        $lines[] = '| Metric | Baseline | Current | Delta | Status |';
        $lines[] = '|--------|----------|---------|-------|--------|';

        foreach ($rows as $row) {
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s |',
                $row['label'],
                $row['baseline'],
                $row['current'],
                $row['delta'],
                $row['emoji'],
            );
        }

        return $lines;
    }

    /**
     * @param array<int, array{name: string, baseline: string, current: string, delta: string, emoji: string, status: string}> $compared
     * @param array<int, array{name: string, baseline: string, current: string, delta: string, emoji: string, status: string}> $unmatched
     */
    private static function buildMarkdown(
        array $compared,
        array $unmatched,
        string $worstStatus,
        float $threshold,
        float $volatileThreshold,
    ): string {
        $lines = [];
        $lines[] = '## Benchmark Comparison';
        $lines[] = '';

        if ($compared === []) {
            // A baseline exists but shares no metric name with this run, so
            // nothing was actually measured against anything. Reporting green
            // here would hide a regression of any size.
            $lines[] = '> ⚠️ **No metrics could be compared**';
            $lines[] = '';
            $lines[] = sprintf(
                'The baseline and this run share no metric names, so %d baseline and %d current '
                . 'metrics were left unpaired. This usually means the two runs used different '
                . 'scenarios, or the baseline predates a change in metric naming. '
                . 'The baseline realigns on the next push to the default branch.',
                count(array_filter($unmatched, static fn(array $r): bool => $r['current'] === '-')),
                count(array_filter($unmatched, static fn(array $r): bool => $r['baseline'] === '-')),
            );
        } else {
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

            $lines[] = sprintf(
                '> %s **%s** — %d metric%s compared',
                $summaryEmoji,
                $summary,
                count($compared),
                count($compared) === 1 ? '' : 's',
            );

            $informational = count(array_filter(
                $compared,
                static fn(array $r): bool => $r['emoji'] === '📊',
            ));

            if ($informational > 0) {
                $lines[] = '';
                $lines[] = sprintf(
                    '📊 marks %d throughput metric%s that moved within the run to run variance of a '
                    . 'shared runner. Only a drop beyond %.0f%% counts as a regression there. Memory '
                    . 'and disk figures repeat exactly between runs, so they gate at %.0f%%.',
                    $informational,
                    $informational === 1 ? '' : 's',
                    $volatileThreshold,
                    $threshold,
                );
            }

            foreach (self::groupByScenario($compared) as $scenario => $rows) {
                $lines[] = '';
                if ($scenario !== '') {
                    $lines[] = sprintf('### %s', $scenario);
                    $lines[] = '';
                }
                $lines = array_merge($lines, self::renderTable($rows));
            }
        }

        if ($unmatched !== []) {
            $lines[] = '';
            $lines[] = sprintf(
                '<details><summary>%d metric%s without a counterpart</summary>',
                count($unmatched),
                count($unmatched) === 1 ? '' : 's',
            );
            $lines[] = '';

            foreach (self::groupByScenario($unmatched) as $scenario => $rows) {
                if ($scenario !== '') {
                    $lines[] = sprintf('**%s**', $scenario);
                    $lines[] = '';
                }
                $lines = array_merge($lines, self::renderTable($rows));
                $lines[] = '';
            }

            $lines[] = '</details>';
        }

        $lines[] = '';

        return implode("\n", $lines);
    }
}
