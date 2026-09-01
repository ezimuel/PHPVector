<?php

declare(strict_types=1);

namespace PHPVector\Tests\Benchmark;

use PHPUnit\Framework\TestCase;
use PHPVector\Benchmark\BenchmarkComparator;

final class BenchmarkComparatorTest extends TestCase
{
    public function testAllGreen(): void
    {
        $baseline = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10000],
            ['name' => 'vector_search (QPS)', 'unit' => 'queries/s', 'value' => 5000],
        ];
        $current = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10500],
            ['name' => 'vector_search (QPS)', 'unit' => 'queries/s', 'value' => 5100],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('All benchmarks passed', $result);
        self::assertStringNotContainsString('🟠', $result);
        self::assertStringNotContainsString('🔴', $result);
        self::assertStringContainsString('🟢', $result);
    }

    public function testOrangeRegression(): void
    {
        $baseline = [
            ['name' => 'insert (memory delta)', 'unit' => 'MB', 'value' => 100],
        ];
        $current = [
            ['name' => 'insert (memory delta)', 'unit' => 'MB', 'value' => 104],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('🟠', $result);
        self::assertStringContainsString('Minor regressions detected', $result);
    }

    public function testRedRegression(): void
    {
        $baseline = [
            ['name' => 'save (disk size)', 'unit' => 'MB', 'value' => 100],
        ];
        $current = [
            ['name' => 'save (disk size)', 'unit' => 'MB', 'value' => 110],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('🔴', $result);
        self::assertStringContainsString('Significant regressions detected', $result);
    }

    public function testMixedStatuses(): void
    {
        $baseline = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10000],
            ['name' => 'a (memory delta)', 'unit' => 'MB', 'value' => 100],
            ['name' => 'b (memory delta)', 'unit' => 'MB', 'value' => 100],
        ];
        $current = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10500],
            ['name' => 'a (memory delta)', 'unit' => 'MB', 'value' => 103],
            ['name' => 'b (memory delta)', 'unit' => 'MB', 'value' => 120],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('🟢', $result);
        self::assertStringContainsString('🟠', $result);
        self::assertStringContainsString('🔴', $result);
        self::assertStringContainsString('Significant regressions detected', $result);
    }

    public function testEmptyBaseline(): void
    {
        $current = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10000],
        ];

        $result = BenchmarkComparator::compare([], $current);

        self::assertStringContainsString('No baseline available', $result);
    }

    public function testSmallerIsBetterMetrics(): void
    {
        $baseline = [
            ['name' => 'insert (memory delta)', 'unit' => 'MB', 'value' => 100.0],
        ];
        $current = [
            ['name' => 'insert (memory delta)', 'unit' => 'MB', 'value' => 90.0],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('🟢', $result);
    }

    public function testSmallerIsBetterRegression(): void
    {
        $baseline = [
            ['name' => 'insert (memory delta)', 'unit' => 'MB', 'value' => 100.0],
        ];
        $current = [
            ['name' => 'insert (memory delta)', 'unit' => 'MB', 'value' => 115.0],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('🔴', $result);
    }

    public function testBaselineZeroValue(): void
    {
        $baseline = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 0.0],
        ];
        $current = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10000],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringNotContainsString('INF', $result);
        self::assertStringNotContainsString('NAN', $result);
    }

    public function testBaselineIntegerZeroFromJson(): void
    {
        // JSON decodes 0 as int(0), not float(0.0)
        $baseline = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 0],
        ];
        $current = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10000],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringNotContainsString('INF', $result);
        self::assertStringNotContainsString('NAN', $result);
        self::assertStringContainsString('N/A', $result);
    }

    public function testNewMetricInCurrent(): void
    {
        $baseline = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10000],
        ];
        $current = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10000],
            ['name' => 'delete (ops/s)', 'unit' => 'ops/s', 'value' => 5000],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('delete (ops/s)', $result);
        self::assertStringContainsString('new', $result);
    }

    public function testCustomThreshold(): void
    {
        $baseline = [
            ['name' => 'insert (memory delta)', 'unit' => 'MB', 'value' => 10000],
        ];
        $current = [
            ['name' => 'insert (memory delta)', 'unit' => 'MB', 'value' => 10800],
        ];

        $resultDefault = BenchmarkComparator::compare($baseline, $current, 5.0);
        $resultRelaxed = BenchmarkComparator::compare($baseline, $current, 10.0);

        self::assertStringContainsString('🔴', $resultDefault);
        self::assertStringContainsString('🟠', $resultRelaxed);
    }

    public function testMarkdownTableStructure(): void
    {
        $baseline = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10000],
        ];
        $current = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10500],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('| Metric | Baseline | Current | Delta | Status |', $result);
        self::assertStringContainsString('## Benchmark Comparison', $result);
    }

    public function testRemovedMetric(): void
    {
        $baseline = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10000],
            ['name' => 'old_metric (QPS)', 'unit' => 'queries/s', 'value' => 5000],
        ];
        $current = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10000],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('old_metric (QPS)', $result);
        self::assertStringContainsString('removed', $result);
    }

    public function testNoOverlapIsNotReportedAsPassing(): void
    {
        // The exact shape that made the CI comment lie: a baseline recorded
        // without a scenario prefix against a run that carries one.
        $baseline = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10000],
        ];
        $current = [
            ['name' => 'xs/insert (ops/s)', 'unit' => 'ops/s', 'value' => 10],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('No metrics could be compared', $result);
        self::assertStringNotContainsString('All benchmarks passed', $result);
        self::assertStringNotContainsString('🟢', $result);
    }

    public function testComparedCountIsReported(): void
    {
        $baseline = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10000],
            ['name' => 'gone (ops/s)', 'unit' => 'ops/s', 'value' => 1],
        ];
        $current = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10500],
            ['name' => 'fresh (ops/s)', 'unit' => 'ops/s', 'value' => 2],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('1 metric compared', $result);
    }

    public function testUnitsAreRendered(): void
    {
        $baseline = [
            ['name' => 'insert (memory delta)', 'unit' => 'MB', 'value' => 48],
        ];
        $current = [
            ['name' => 'insert (memory delta)', 'unit' => 'MB', 'value' => 48],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('48.00 MB', $result);
    }

    public function testRowsAreGroupedByScenario(): void
    {
        $baseline = [
            ['name' => 'xs/insert (ops/s)', 'unit' => 'ops/s', 'value' => 100],
            ['name' => 'small/insert (ops/s)', 'unit' => 'ops/s', 'value' => 200],
        ];
        $current = [
            ['name' => 'xs/insert (ops/s)', 'unit' => 'ops/s', 'value' => 100],
            ['name' => 'small/insert (ops/s)', 'unit' => 'ops/s', 'value' => 200],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('### xs', $result);
        self::assertStringContainsString('### small', $result);
        // The prefix belongs to the heading, not to every row.
        self::assertStringNotContainsString('| xs/insert (ops/s) |', $result);
        self::assertStringContainsString('| insert (ops/s) |', $result);
    }

    public function testSlashInsideUnitLabelIsNotAScenario(): void
    {
        $baseline = [
            ['name' => 'save (MB/s)', 'unit' => 'MB/s', 'value' => 10],
        ];
        $current = [
            ['name' => 'save (MB/s)', 'unit' => 'MB/s', 'value' => 10],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringNotContainsString('### save', $result);
        self::assertStringContainsString('| save (MB/s) |', $result);
    }

    public function testUnmatchedMetricsAreCollapsed(): void
    {
        $baseline = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10000],
            ['name' => 'gone (ops/s)', 'unit' => 'ops/s', 'value' => 1],
        ];
        $current = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10000],
            ['name' => 'fresh (ops/s)', 'unit' => 'ops/s', 'value' => 2],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('<details>', $result);
        self::assertStringContainsString('2 metrics without a counterpart', $result);
        self::assertStringContainsString('*new*', $result);
        self::assertStringContainsString('*removed*', $result);
    }

    public function testThroughputWithinVarianceDoesNotGate(): void
    {
        // Identical library code measured on two shared runners moved by more
        // than forty percent on every timing metric, so a drop of this size
        // carries no signal and must not colour the verdict.
        $baseline = [
            ['name' => 'vector_search (QPS)', 'unit' => 'queries/s', 'value' => 400.26],
        ];
        $current = [
            ['name' => 'vector_search (QPS)', 'unit' => 'queries/s', 'value' => 220.55],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('📊', $result);
        self::assertStringContainsString('All benchmarks passed', $result);
        self::assertStringNotContainsString('🔴', $result);
        self::assertStringContainsString('-44.9%', $result);
    }

    public function testThroughputCollapseStillGates(): void
    {
        $baseline = [
            ['name' => 'vector_search (QPS)', 'unit' => 'queries/s', 'value' => 1000],
        ];
        $current = [
            ['name' => 'vector_search (QPS)', 'unit' => 'queries/s', 'value' => 100],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('🔴', $result);
        self::assertStringContainsString('Significant regressions detected', $result);
    }

    public function testMemoryGatesWhereThroughputDoesNot(): void
    {
        $baseline = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 1000],
            ['name' => 'insert (memory delta)', 'unit' => 'MB', 'value' => 100],
        ];
        $current = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 700],
            ['name' => 'insert (memory delta)', 'unit' => 'MB', 'value' => 110],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('📊', $result);
        self::assertStringContainsString('🔴', $result);
        self::assertStringContainsString('Significant regressions detected', $result);
    }

    public function testVolatileThresholdIsConfigurable(): void
    {
        $baseline = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 1000],
        ];
        $current = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 700],
        ];

        self::assertStringContainsString('📊', BenchmarkComparator::compare($baseline, $current, 5.0, 50.0));
        self::assertStringContainsString('🔴', BenchmarkComparator::compare($baseline, $current, 5.0, 10.0));
    }

    public function testUnknownUnitStillGates(): void
    {
        // A unit nobody registered must reach the verdict rather than slip past
        // it as informational.
        $baseline = [
            ['name' => 'something (widgets)', 'unit' => 'widgets', 'value' => 100],
        ];
        $current = [
            ['name' => 'something (widgets)', 'unit' => 'widgets', 'value' => 130],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('🔴', $result);
        self::assertStringNotContainsString('📊', $result);
    }
}
