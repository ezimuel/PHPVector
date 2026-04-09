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
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10000],
        ];
        $current = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 9600],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('🟠', $result);
        self::assertStringContainsString('Minor regressions detected', $result);
    }

    public function testRedRegression(): void
    {
        $baseline = [
            ['name' => 'vector_search (QPS)', 'unit' => 'queries/s', 'value' => 10000],
        ];
        $current = [
            ['name' => 'vector_search (QPS)', 'unit' => 'queries/s', 'value' => 9000],
        ];

        $result = BenchmarkComparator::compare($baseline, $current);

        self::assertStringContainsString('🔴', $result);
        self::assertStringContainsString('Significant regressions detected', $result);
    }

    public function testMixedStatuses(): void
    {
        $baseline = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10000],
            ['name' => 'vector_search (QPS)', 'unit' => 'queries/s', 'value' => 10000],
            ['name' => 'text_search (QPS)', 'unit' => 'queries/s', 'value' => 10000],
        ];
        $current = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10500],
            ['name' => 'vector_search (QPS)', 'unit' => 'queries/s', 'value' => 9700],
            ['name' => 'text_search (QPS)', 'unit' => 'queries/s', 'value' => 8000],
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
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 10000],
        ];
        $current = [
            ['name' => 'insert (ops/s)', 'unit' => 'ops/s', 'value' => 9200],
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
}
