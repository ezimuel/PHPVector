<?php

declare(strict_types=1);

namespace PHPVector\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPVector\Metadata\MetadataFilter;

final class MetadataFilterTest extends TestCase
{
    public function testConstructorThrowsOnUnknownOperator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown operator "invalid"');

        new MetadataFilter('key', 'value', 'invalid');
    }

    public function testConstructorAcceptsAllValidOperators(): void
    {
        $operators = ['=', '!=', '<', '<=', '>', '>=', 'in', 'not_in', 'contains', 'exists', 'not_exists'];

        foreach ($operators as $operator) {
            $value = in_array($operator, ['in', 'not_in'], true) ? ['a'] : 'value';
            $filter = new MetadataFilter('key', $value, $operator);
            self::assertSame($operator, $filter->operator);
        }
    }

    public function testConstructorThrowsWhenInOperatorGetsNonArrayValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operator "in" requires an array value');

        new MetadataFilter('key', 'not-an-array', 'in');
    }

    public function testConstructorThrowsWhenNotInOperatorGetsNonArrayValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operator "not_in" requires an array value');

        new MetadataFilter('key', 'not-an-array', 'not_in');
    }

    public function testConstructorAcceptsArrayForInOperator(): void
    {
        $filter = new MetadataFilter('key', ['a', 'b'], 'in');
        self::assertSame(['a', 'b'], $filter->value);
    }

    public function testConstructorAcceptsArrayForNotInOperator(): void
    {
        $filter = new MetadataFilter('key', ['a', 'b'], 'not_in');
        self::assertSame(['a', 'b'], $filter->value);
    }

    public function testConstructorThrowsWhenContainsOperatorGetsArrayValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operator "contains" requires a single value, not an array');

        new MetadataFilter('key', ['an', 'array'], 'contains');
    }

    public function testConstructorAcceptsScalarForContainsOperator(): void
    {
        $filter = new MetadataFilter('key', 'needle', 'contains');
        self::assertSame('needle', $filter->value);
    }

    public function testPropertiesAreReadonly(): void
    {
        $filter = new MetadataFilter('category', 'electronics', '=');

        $reflection = new \ReflectionClass($filter);

        self::assertTrue($reflection->getProperty('key')->isReadOnly());
        self::assertTrue($reflection->getProperty('value')->isReadOnly());
        self::assertTrue($reflection->getProperty('operator')->isReadOnly());
    }

    public function testEqFactoryMethod(): void
    {
        $filter = MetadataFilter::eq('category', 'books');

        self::assertSame('category', $filter->key);
        self::assertSame('books', $filter->value);
        self::assertSame('=', $filter->operator);
    }

    public function testNeqFactoryMethod(): void
    {
        $filter = MetadataFilter::neq('status', 'deleted');

        self::assertSame('status', $filter->key);
        self::assertSame('deleted', $filter->value);
        self::assertSame('!=', $filter->operator);
    }

    public function testLtFactoryMethod(): void
    {
        $filter = MetadataFilter::lt('price', 100);

        self::assertSame('price', $filter->key);
        self::assertSame(100, $filter->value);
        self::assertSame('<', $filter->operator);
    }

    public function testLteFactoryMethod(): void
    {
        $filter = MetadataFilter::lte('price', 100);

        self::assertSame('price', $filter->key);
        self::assertSame(100, $filter->value);
        self::assertSame('<=', $filter->operator);
    }

    public function testGtFactoryMethod(): void
    {
        $filter = MetadataFilter::gt('rating', 4.5);

        self::assertSame('rating', $filter->key);
        self::assertSame(4.5, $filter->value);
        self::assertSame('>', $filter->operator);
    }

    public function testGteFactoryMethod(): void
    {
        $filter = MetadataFilter::gte('rating', 4.5);

        self::assertSame('rating', $filter->key);
        self::assertSame(4.5, $filter->value);
        self::assertSame('>=', $filter->operator);
    }

    public function testInFactoryMethod(): void
    {
        $filter = MetadataFilter::in('color', ['red', 'blue', 'green']);

        self::assertSame('color', $filter->key);
        self::assertSame(['red', 'blue', 'green'], $filter->value);
        self::assertSame('in', $filter->operator);
    }

    public function testNotInFactoryMethod(): void
    {
        $filter = MetadataFilter::notIn('status', ['archived', 'deleted']);

        self::assertSame('status', $filter->key);
        self::assertSame(['archived', 'deleted'], $filter->value);
        self::assertSame('not_in', $filter->operator);
    }

    public function testContainsFactoryMethod(): void
    {
        $filter = MetadataFilter::contains('description', 'python');

        self::assertSame('description', $filter->key);
        self::assertSame('python', $filter->value);
        self::assertSame('contains', $filter->operator);
    }

    public function testDefaultOperatorIsEquals(): void
    {
        $filter = new MetadataFilter('key', 'value');
        self::assertSame('=', $filter->operator);
    }

    public function testExistsFactoryMethod(): void
    {
        $filter = MetadataFilter::exists('category');

        self::assertSame('category', $filter->key);
        self::assertTrue($filter->value);
        self::assertSame('exists', $filter->operator);
    }

    public function testNotExistsFactoryMethod(): void
    {
        $filter = MetadataFilter::notExists('category');

        self::assertSame('category', $filter->key);
        self::assertTrue($filter->value);
        self::assertSame('not_exists', $filter->operator);
    }
}
