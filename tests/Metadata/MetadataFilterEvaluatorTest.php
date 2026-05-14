<?php

declare(strict_types=1);

namespace PHPVector\Tests\Metadata;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PHPVector\Document;
use PHPVector\Metadata\MetadataFilterEvaluator;
use PHPVector\Metadata\MetadataFilter;

final class MetadataFilterEvaluatorTest extends TestCase
{
    private MetadataFilterEvaluator $evaluator;

    protected function setUp(): void
    {
        $this->evaluator = new MetadataFilterEvaluator();
    }

    // ===========================================
    // Equality (=) operator tests
    // ===========================================

    public function testEqualityMatchesExactStringValue(): void
    {
        $document = new Document(metadata: ['status' => 'active']);
        $filters = [MetadataFilter::eq('status', 'active')];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testEqualityFailsOnDifferentStringValue(): void
    {
        $document = new Document(metadata: ['status' => 'inactive']);
        $filters = [MetadataFilter::eq('status', 'active')];

        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testEqualityUsesStrictComparison(): void
    {
        $document = new Document(metadata: ['count' => '5']);
        $filters = [MetadataFilter::eq('count', 5)];

        // String '5' !== int 5
        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testEqualityMatchesIntegerValue(): void
    {
        $document = new Document(metadata: ['count' => 42]);
        $filters = [MetadataFilter::eq('count', 42)];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testEqualityMatchesBooleanValue(): void
    {
        $document = new Document(metadata: ['enabled' => true]);
        $filters = [MetadataFilter::eq('enabled', true)];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testEqualityMatchesNullValue(): void
    {
        $document = new Document(metadata: ['value' => null]);
        $filters = [MetadataFilter::eq('value', null)];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    // ===========================================
    // Inequality (!=) operator tests
    // ===========================================

    public function testInequalityMatchesWhenDifferent(): void
    {
        $document = new Document(metadata: ['status' => 'inactive']);
        $filters = [MetadataFilter::neq('status', 'active')];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testInequalityFailsWhenEqual(): void
    {
        $document = new Document(metadata: ['status' => 'active']);
        $filters = [MetadataFilter::neq('status', 'active')];

        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testInequalityUsesStrictComparison(): void
    {
        $document = new Document(metadata: ['count' => '5']);
        $filters = [MetadataFilter::neq('count', 5)];

        // String '5' !== int 5, so they are different
        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    // ===========================================
    // Less than (<) operator tests
    // ===========================================

    public function testLessThanMatchesWithNumericValues(): void
    {
        $document = new Document(metadata: ['price' => 50]);
        $filters = [MetadataFilter::lt('price', 100)];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testLessThanFailsWhenEqual(): void
    {
        $document = new Document(metadata: ['price' => 100]);
        $filters = [MetadataFilter::lt('price', 100)];

        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testLessThanFailsWhenGreater(): void
    {
        $document = new Document(metadata: ['price' => 150]);
        $filters = [MetadataFilter::lt('price', 100)];

        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testLessThanWorksWithStrings(): void
    {
        $document = new Document(metadata: ['name' => 'apple']);
        $filters = [MetadataFilter::lt('name', 'banana')];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    // ===========================================
    // Less than or equal (<=) operator tests
    // ===========================================

    public function testLessThanOrEqualMatchesWhenLess(): void
    {
        $document = new Document(metadata: ['price' => 50]);
        $filters = [MetadataFilter::lte('price', 100)];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testLessThanOrEqualMatchesWhenEqual(): void
    {
        $document = new Document(metadata: ['price' => 100]);
        $filters = [MetadataFilter::lte('price', 100)];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testLessThanOrEqualFailsWhenGreater(): void
    {
        $document = new Document(metadata: ['price' => 150]);
        $filters = [MetadataFilter::lte('price', 100)];

        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    // ===========================================
    // Greater than (>) operator tests
    // ===========================================

    public function testGreaterThanMatchesWithNumericValues(): void
    {
        $document = new Document(metadata: ['price' => 150]);
        $filters = [MetadataFilter::gt('price', 100)];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testGreaterThanFailsWhenEqual(): void
    {
        $document = new Document(metadata: ['price' => 100]);
        $filters = [MetadataFilter::gt('price', 100)];

        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testGreaterThanFailsWhenLess(): void
    {
        $document = new Document(metadata: ['price' => 50]);
        $filters = [MetadataFilter::gt('price', 100)];

        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testGreaterThanWorksWithStrings(): void
    {
        $document = new Document(metadata: ['name' => 'banana']);
        $filters = [MetadataFilter::gt('name', 'apple')];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    // ===========================================
    // Greater than or equal (>=) operator tests
    // ===========================================

    public function testGreaterThanOrEqualMatchesWhenGreater(): void
    {
        $document = new Document(metadata: ['price' => 150]);
        $filters = [MetadataFilter::gte('price', 100)];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testGreaterThanOrEqualMatchesWhenEqual(): void
    {
        $document = new Document(metadata: ['price' => 100]);
        $filters = [MetadataFilter::gte('price', 100)];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testGreaterThanOrEqualFailsWhenLess(): void
    {
        $document = new Document(metadata: ['price' => 50]);
        $filters = [MetadataFilter::gte('price', 100)];

        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    // ===========================================
    // In operator tests
    // ===========================================

    public function testInMatchesWhenValueInArray(): void
    {
        $document = new Document(metadata: ['status' => 'active']);
        $filters = [MetadataFilter::in('status', ['active', 'pending'])];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testInFailsWhenValueNotInArray(): void
    {
        $document = new Document(metadata: ['status' => 'inactive']);
        $filters = [MetadataFilter::in('status', ['active', 'pending'])];

        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testInUsesStrictComparison(): void
    {
        $document = new Document(metadata: ['id' => '1']);
        $filters = [MetadataFilter::in('id', [1, 2, 3])];

        // String '1' is not in [1, 2, 3] with strict comparison
        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testInMatchesWithMixedTypes(): void
    {
        $document = new Document(metadata: ['value' => null]);
        $filters = [MetadataFilter::in('value', [null, false, 0])];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    // ===========================================
    // Not in operator tests
    // ===========================================

    public function testNotInMatchesWhenValueNotInArray(): void
    {
        $document = new Document(metadata: ['status' => 'inactive']);
        $filters = [MetadataFilter::notIn('status', ['active', 'pending'])];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testNotInFailsWhenValueInArray(): void
    {
        $document = new Document(metadata: ['status' => 'active']);
        $filters = [MetadataFilter::notIn('status', ['active', 'pending'])];

        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testNotInUsesStrictComparison(): void
    {
        $document = new Document(metadata: ['id' => '1']);
        $filters = [MetadataFilter::notIn('id', [1, 2, 3])];

        // String '1' is not in [1, 2, 3] with strict comparison, so not_in matches
        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    // ===========================================
    // Contains operator tests
    // ===========================================

    public function testContainsMatchesWhenArrayContainsValue(): void
    {
        $document = new Document(metadata: ['tags' => ['php', 'vector', 'search']]);
        $filters = [MetadataFilter::contains('tags', 'vector')];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testContainsFailsWhenArrayDoesNotContainValue(): void
    {
        $document = new Document(metadata: ['tags' => ['php', 'vector', 'search']]);
        $filters = [MetadataFilter::contains('tags', 'database')];

        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testContainsUsesStrictComparison(): void
    {
        $document = new Document(metadata: ['ids' => [1, 2, 3]]);
        $filters = [MetadataFilter::contains('ids', '1')];

        // String '1' is not in [1, 2, 3] with strict comparison
        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testContainsFailsWhenMetadataIsNotArray(): void
    {
        $document = new Document(metadata: ['name' => 'test']);
        $filters = [MetadataFilter::contains('name', 'test')];

        // 'name' is not an array, so contains returns false
        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testContainsMatchesWithIntegerValue(): void
    {
        $document = new Document(metadata: ['numbers' => [1, 2, 3, 4, 5]]);
        $filters = [MetadataFilter::contains('numbers', 3)];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    // ===========================================
    // Missing metadata key tests
    // ===========================================

    public function testMissingMetadataKeyReturnsFalse(): void
    {
        $document = new Document(metadata: ['status' => 'active']);
        $filters = [MetadataFilter::eq('nonexistent', 'value')];

        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testMissingKeyReturnsFalseForAllOperators(): void
    {
        $document = new Document(metadata: []);

        $operators = [
            MetadataFilter::eq('key', 'value'),
            MetadataFilter::neq('key', 'value'),
            MetadataFilter::lt('key', 10),
            MetadataFilter::lte('key', 10),
            MetadataFilter::gt('key', 10),
            MetadataFilter::gte('key', 10),
            MetadataFilter::in('key', ['a', 'b']),
            MetadataFilter::notIn('key', ['a', 'b']),
            MetadataFilter::contains('key', 'value'),
        ];

        foreach ($operators as $filter) {
            $this->assertFalse(
                $this->evaluator->matches($document, [$filter]),
                "Missing key should return false for operator: {$filter->operator}"
            );
        }
    }

    // ===========================================
    // AND logic (top-level filters) tests
    // ===========================================

    public function testMultipleFiltersAreAndedTogether(): void
    {
        $document = new Document(metadata: [
            'status' => 'active',
            'price' => 150,
        ]);
        $filters = [
            MetadataFilter::eq('status', 'active'),
            MetadataFilter::gt('price', 100),
        ];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testAndLogicFailsIfAnyFilterFails(): void
    {
        $document = new Document(metadata: [
            'status' => 'active',
            'price' => 50,  // Less than 100
        ]);
        $filters = [
            MetadataFilter::eq('status', 'active'),  // Matches
            MetadataFilter::gt('price', 100),        // Fails
        ];

        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testEmptyFiltersMatchAnyDocument(): void
    {
        $document = new Document(metadata: ['anything' => 'whatever']);
        $filters = [];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    // ===========================================
    // OR groups (nested arrays) tests
    // ===========================================

    public function testOrGroupMatchesWhenFirstFilterMatches(): void
    {
        $document = new Document(metadata: ['status' => 'active']);
        $filters = [
            [
                MetadataFilter::eq('status', 'active'),
                MetadataFilter::eq('status', 'pending'),
            ],
        ];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testOrGroupMatchesWhenSecondFilterMatches(): void
    {
        $document = new Document(metadata: ['status' => 'pending']);
        $filters = [
            [
                MetadataFilter::eq('status', 'active'),
                MetadataFilter::eq('status', 'pending'),
            ],
        ];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testOrGroupFailsWhenNoFilterMatches(): void
    {
        $document = new Document(metadata: ['status' => 'inactive']);
        $filters = [
            [
                MetadataFilter::eq('status', 'active'),
                MetadataFilter::eq('status', 'pending'),
            ],
        ];

        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testEmptyOrGroupReturnsFalse(): void
    {
        $document = new Document(metadata: ['status' => 'active']);
        $filters = [[]];  // Empty OR group

        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    // ===========================================
    // Combined AND/OR logic tests
    // ===========================================

    public function testCombinedAndOrLogic(): void
    {
        // Filter: (status = 'active' OR status = 'pending') AND price > 100
        $document = new Document(metadata: [
            'status' => 'pending',
            'price' => 150,
        ]);
        $filters = [
            [
                MetadataFilter::eq('status', 'active'),
                MetadataFilter::eq('status', 'pending'),
            ],
            MetadataFilter::gt('price', 100),
        ];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testCombinedAndOrLogicFailsWhenOrGroupFails(): void
    {
        // Filter: (status = 'active' OR status = 'pending') AND price > 100
        $document = new Document(metadata: [
            'status' => 'inactive',  // Fails OR group
            'price' => 150,
        ]);
        $filters = [
            [
                MetadataFilter::eq('status', 'active'),
                MetadataFilter::eq('status', 'pending'),
            ],
            MetadataFilter::gt('price', 100),
        ];

        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testCombinedAndOrLogicFailsWhenAndFilterFails(): void
    {
        // Filter: (status = 'active' OR status = 'pending') AND price > 100
        $document = new Document(metadata: [
            'status' => 'active',
            'price' => 50,  // Fails price > 100
        ]);
        $filters = [
            [
                MetadataFilter::eq('status', 'active'),
                MetadataFilter::eq('status', 'pending'),
            ],
            MetadataFilter::gt('price', 100),
        ];

        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testMultipleOrGroupsWithAndFilters(): void
    {
        // Filter: (status = 'active' OR status = 'pending') AND (category = 'tech' OR category = 'science') AND price >= 50
        $document = new Document(metadata: [
            'status' => 'active',
            'category' => 'science',
            'price' => 75,
        ]);
        $filters = [
            [
                MetadataFilter::eq('status', 'active'),
                MetadataFilter::eq('status', 'pending'),
            ],
            [
                MetadataFilter::eq('category', 'tech'),
                MetadataFilter::eq('category', 'science'),
            ],
            MetadataFilter::gte('price', 50),
        ];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    // ===========================================
    // Complex real-world scenarios
    // ===========================================

    public function testRealWorldEcommerceScenario(): void
    {
        $document = new Document(metadata: [
            'category' => 'electronics',
            'brand' => 'acme',
            'price' => 299.99,
            'in_stock' => true,
            'tags' => ['sale', 'featured', 'new'],
        ]);

        // Filter: category in ['electronics', 'computers'] AND price < 500 AND in_stock = true AND tags contains 'sale'
        $filters = [
            MetadataFilter::in('category', ['electronics', 'computers']),
            MetadataFilter::lt('price', 500),
            MetadataFilter::eq('in_stock', true),
            MetadataFilter::contains('tags', 'sale'),
        ];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testRealWorldContentFilteringScenario(): void
    {
        $document = new Document(metadata: [
            'author' => 'john_doe',
            'status' => 'published',
            'created_at' => '2025-01-15',
            'view_count' => 1500,
            'categories' => ['tutorial', 'beginner'],
        ]);

        // Filter: (status = 'published' OR status = 'featured') AND view_count >= 1000 AND categories contains 'tutorial'
        $filters = [
            [
                MetadataFilter::eq('status', 'published'),
                MetadataFilter::eq('status', 'featured'),
            ],
            MetadataFilter::gte('view_count', 1000),
            MetadataFilter::contains('categories', 'tutorial'),
        ];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testDocumentWithNoMetadata(): void
    {
        $document = new Document();
        $filters = [MetadataFilter::eq('any', 'value')];

        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testDocumentWithNoMetadataAndEmptyFilters(): void
    {
        $document = new Document();
        $filters = [];

        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    // ===========================================
    // Exists operator tests
    // ===========================================

    public function testExistsReturnsTrueWhenKeyPresent(): void
    {
        $document = new Document(metadata: ['category' => 'books']);
        $filters = [MetadataFilter::exists('category')];
        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testExistsReturnsTrueWhenKeyPresentWithNullValue(): void
    {
        $document = new Document(metadata: ['category' => null]);
        $filters = [MetadataFilter::exists('category')];
        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testExistsReturnsFalseWhenKeyMissing(): void
    {
        $document = new Document(metadata: ['name' => 'test']);
        $filters = [MetadataFilter::exists('category')];
        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testExistsReturnsFalseOnEmptyMetadata(): void
    {
        $document = new Document(metadata: []);
        $filters = [MetadataFilter::exists('category')];
        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    // ===========================================
    // Not Exists operator tests
    // ===========================================

    public function testNotExistsReturnsTrueWhenKeyMissing(): void
    {
        $document = new Document(metadata: ['name' => 'test']);
        $filters = [MetadataFilter::notExists('category')];
        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testNotExistsReturnsTrueOnEmptyMetadata(): void
    {
        $document = new Document(metadata: []);
        $filters = [MetadataFilter::notExists('category')];
        $this->assertTrue($this->evaluator->matches($document, $filters));
    }

    public function testNotExistsReturnsFalseWhenKeyPresent(): void
    {
        $document = new Document(metadata: ['category' => 'books']);
        $filters = [MetadataFilter::notExists('category')];
        $this->assertFalse($this->evaluator->matches($document, $filters));
    }

    public function testNotExistsReturnsFalseWhenKeyPresentWithNullValue(): void
    {
        $document = new Document(metadata: ['category' => null]);
        $filters = [MetadataFilter::notExists('category')];
        $this->assertFalse($this->evaluator->matches($document, $filters));
    }
}
