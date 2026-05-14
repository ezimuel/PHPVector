<?php

declare(strict_types=1);

namespace PHPVector\Tests;

use PHPUnit\Framework\TestCase;
use PHPVector\BM25\Config as BM25Config;
use PHPVector\BM25\SimpleTokenizer;
use PHPVector\Document;
use PHPVector\HNSW\Config as HNSWConfig;
use PHPVector\HybridMode;
use PHPVector\Metadata\MetadataFilter;
use PHPVector\Metadata\SortDirection;
use PHPVector\SearchResult;
use PHPVector\VectorDatabase;

final class VectorDatabaseTest extends TestCase
{
    private function makeDb(): VectorDatabase
    {
        return new VectorDatabase(
            hnswConfig: new HNSWConfig(M: 8, efConstruction: 50, efSearch: 20),
            bm25Config: new BM25Config(),
            tokenizer:  new SimpleTokenizer([]),
        );
    }

    // ------------------------------------------------------------------
    // Sanity / basic API
    // ------------------------------------------------------------------

    public function testCountIsAccurate(): void
    {
        $db = $this->makeDb();
        self::assertSame(0, $db->count());

        $db->addDocument(new Document(id: 1, vector: [0.1, 0.2], text: 'hello'));
        self::assertSame(1, $db->count());

        $db->addDocuments([
            new Document(id: 2, vector: [0.3, 0.4], text: 'world'),
            new Document(id: 3, vector: [0.5, 0.6], text: 'foo bar'),
        ]);
        self::assertSame(3, $db->count());
    }

    public function testDuplicateIdThrows(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 'abc', vector: [1.0, 0.0]));

        $this->expectException(\RuntimeException::class);
        $db->addDocument(new Document(id: 'abc', vector: [0.0, 1.0]));
    }

    // ------------------------------------------------------------------
    // Vector search
    // ------------------------------------------------------------------

    public function testVectorSearchReturnsCorrectDocument(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'red'));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], text: 'blue'));

        $results = $db->vectorSearch([0.99, 0.01], 1);

        self::assertCount(1, $results);
        self::assertSame(1, $results[0]->document->id);
    }

    public function testVectorSearchResultsAreSearchResult(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 'x', vector: [1.0, 0.0]));

        $results = $db->vectorSearch([1.0, 0.0], 1);

        self::assertInstanceOf(SearchResult::class, $results[0]);
        self::assertGreaterThan(0.0, $results[0]->score);
    }

    // ------------------------------------------------------------------
    // Text search
    // ------------------------------------------------------------------

    public function testTextSearchFindsRelevantDoc(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'php vector database hnsw'));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], text: 'baking bread sourdough'));

        $results = $db->textSearch('hnsw vector php', 5);

        self::assertNotEmpty($results);
        self::assertSame(1, $results[0]->document->id);
    }

    public function testTextSearchEmptyQueryReturnsNothing(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0], text: 'hello world'));

        self::assertSame([], $db->textSearch('', 5));
    }

    // ------------------------------------------------------------------
    // Hybrid RRF search
    // ------------------------------------------------------------------

    public function testHybridRRFReturnsSomeResults(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'apple orange'));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], text: 'banana mango'));
        $db->addDocument(new Document(id: 3, vector: [0.5, 0.5], text: 'apple mango hybrid'));

        $results = $db->hybridSearch(
            vector: [1.0, 0.0],
            text: 'apple',
            k: 3,
            mode: HybridMode::RRF,
        );

        self::assertNotEmpty($results);
    }

    public function testHybridRRFScoresAreDescending(): void
    {
        $db = $this->makeDb();

        for ($i = 0; $i < 10; $i++) {
            $db->addDocument(new Document(
                id: $i,
                vector: [(float) $i / 10.0, 1.0 - (float) $i / 10.0],
                text: "document number $i with keyword",
            ));
        }

        $results = $db->hybridSearch(
            vector: [0.5, 0.5],
            text: 'keyword',
            k: 5,
            mode: HybridMode::RRF,
        );

        for ($i = 1; $i < count($results); $i++) {
            self::assertGreaterThanOrEqual($results[$i]->score, $results[$i - 1]->score);
        }
    }

    // ------------------------------------------------------------------
    // Hybrid Weighted search
    // ------------------------------------------------------------------

    public function testHybridWeightedReturnsSomeResults(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'php vector search'));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], text: 'sql relational database'));

        $results = $db->hybridSearch(
            vector: [1.0, 0.0],
            text: 'php',
            k: 2,
            mode: HybridMode::Weighted,
            vectorWeight: 0.6,
            textWeight: 0.4,
        );

        self::assertNotEmpty($results);
    }

    public function testHybridWeightedScoresAreDescending(): void
    {
        $db = $this->makeDb();

        for ($i = 0; $i < 8; $i++) {
            $db->addDocument(new Document(
                id: $i,
                vector: [(float) $i * 0.1, 0.0],
                text: "item $i with common tag",
            ));
        }

        $results = $db->hybridSearch(
            vector: [0.4, 0.0],
            text: 'common tag',
            k: 5,
            mode: HybridMode::Weighted,
        );

        for ($i = 1; $i < count($results); $i++) {
            self::assertGreaterThanOrEqual($results[$i]->score, $results[$i - 1]->score);
        }
    }

    // ------------------------------------------------------------------
    // Edge cases
    // ------------------------------------------------------------------

    public function testSearchOnEmptyDbReturnsEmpty(): void
    {
        $db = $this->makeDb();

        self::assertSame([], $db->vectorSearch([1.0, 0.0], 5));
        self::assertSame([], $db->textSearch('hello', 5));
        self::assertSame([], $db->hybridSearch([1.0, 0.0], 'hello', 5));
    }

    public function testKExceedingIndexSizeIsHandled(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'one'));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], text: 'two'));

        $results = $db->vectorSearch([0.5, 0.5], 100);
        self::assertCount(2, $results);
    }

    public function testMetadataIsPreservedInResults(): void
    {
        $db  = $this->makeDb();
        $doc = new Document(
            id: 'meta-doc',
            vector: [1.0, 0.0],
            text: 'metadata test',
            metadata: ['color' => 'red', 'year' => 2024],
        );
        $db->addDocument($doc);

        $results = $db->vectorSearch([1.0, 0.0], 1);

        self::assertSame(['color' => 'red', 'year' => 2024], $results[0]->document->metadata);
    }

    // ------------------------------------------------------------------
    // Delete document
    // ------------------------------------------------------------------

    public function testDeleteDocumentReducesCount(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'first'));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], text: 'second'));

        self::assertSame(2, $db->count());

        $result = $db->deleteDocument(1);
        self::assertTrue($result);
        self::assertSame(1, $db->count());
    }

    public function testDeleteDocumentExcludesFromVectorSearch(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'first'));
        $db->addDocument(new Document(id: 2, vector: [0.99, 0.01], text: 'second'));
        $db->addDocument(new Document(id: 3, vector: [0.0, 1.0], text: 'third'));

        // Before delete, doc 1 should be closest to [1.0, 0.0]
        $results = $db->vectorSearch([1.0, 0.0], 1);
        self::assertSame(1, $results[0]->document->id);

        // Delete doc 1
        $db->deleteDocument(1);

        // Now doc 2 should be closest
        $results = $db->vectorSearch([1.0, 0.0], 1);
        self::assertSame(2, $results[0]->document->id);
    }

    public function testDeleteDocumentExcludesFromTextSearch(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'unique keyword here'));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], text: 'something else'));

        // Before delete
        $results = $db->textSearch('unique keyword', 5);
        self::assertCount(1, $results);
        self::assertSame(1, $results[0]->document->id);

        // Delete doc 1
        $db->deleteDocument(1);

        // After delete: no results for that keyword
        $results = $db->textSearch('unique keyword', 5);
        self::assertCount(0, $results);
    }

    public function testDeleteNonexistentDocumentReturnsFalse(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'hello'));

        self::assertFalse($db->deleteDocument(999));
        self::assertFalse($db->deleteDocument('nonexistent'));
    }

    public function testDeleteAllowsReinsertWithSameId(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 'doc', vector: [1.0, 0.0], text: 'original'));

        $db->deleteDocument('doc');

        // Should not throw - ID is now available
        $db->addDocument(new Document(id: 'doc', vector: [0.0, 1.0], text: 'replacement'));

        $results = $db->textSearch('replacement', 1);
        self::assertCount(1, $results);
        self::assertSame('doc', $results[0]->document->id);
    }

    // ------------------------------------------------------------------
    // Update document
    // ------------------------------------------------------------------

    public function testUpdateDocumentChangesContent(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'original content'));

        $result = $db->updateDocument(new Document(
            id: 1,
            vector: [0.0, 1.0],
            text: 'updated content',
            metadata: ['version' => 2],
        ));

        self::assertTrue($result);
        self::assertSame(1, $db->count());

        // Text search should find updated content
        $results = $db->textSearch('updated content', 1);
        self::assertCount(1, $results);
        self::assertSame(1, $results[0]->document->id);
        self::assertSame(['version' => 2], $results[0]->document->metadata);

        // Vector search should use new vector
        $results = $db->vectorSearch([0.0, 1.0], 1);
        self::assertSame(1, $results[0]->document->id);
    }

    public function testUpdateNonexistentDocumentReturnsFalse(): void
    {
        $db = $this->makeDb();

        $result = $db->updateDocument(new Document(id: 999, vector: [1.0, 0.0], text: 'new'));
        self::assertFalse($result);
    }

    public function testUpdateDocumentWithoutIdThrows(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'hello'));

        $this->expectException(\RuntimeException::class);
        $db->updateDocument(new Document(vector: [0.0, 1.0], text: 'no id'));
    }

    // ------------------------------------------------------------------
    // Vector search with metadata filters
    // ------------------------------------------------------------------

    public function testVectorSearchWithFiltersReturnsMatchingDocs(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['category' => 'tech']));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], metadata: ['category' => 'finance']));
        $db->addDocument(new Document(id: 3, vector: [0.8, 0.2], metadata: ['category' => 'tech']));

        $results = $db->vectorSearch(
            vector: [1.0, 0.0],
            k: 10,
            filters: [MetadataFilter::eq('category', 'tech')],
        );

        self::assertCount(2, $results);
        self::assertSame(1, $results[0]->document->id);
        self::assertSame(3, $results[1]->document->id);
    }

    public function testVectorSearchWithFiltersRespectsKLimit(): void
    {
        $db = $this->makeDb();

        for ($i = 0; $i < 10; $i++) {
            $db->addDocument(new Document(
                id: $i,
                vector: [1.0 - ($i * 0.05), $i * 0.05],
                metadata: ['type' => 'match'],
            ));
        }

        $results = $db->vectorSearch(
            vector: [1.0, 0.0],
            k: 3,
            filters: [MetadataFilter::eq('type', 'match')],
        );

        self::assertCount(3, $results);
    }

    public function testVectorSearchWithFiltersMayReturnFewerThanK(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['status' => 'active']));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], metadata: ['status' => 'inactive']));
        $db->addDocument(new Document(id: 3, vector: [0.8, 0.2], metadata: ['status' => 'inactive']));

        $results = $db->vectorSearch(
            vector: [1.0, 0.0],
            k: 10,
            filters: [MetadataFilter::eq('status', 'active')],
        );

        self::assertCount(1, $results);
    }

    public function testVectorSearchEmptyFiltersWorksLikeNoFilters(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['key' => 'value']));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], metadata: ['key' => 'other']));

        $resultsWithEmptyFilters = $db->vectorSearch([1.0, 0.0], 10, null, []);
        $resultsWithoutFilters = $db->vectorSearch([1.0, 0.0], 10);

        self::assertCount(count($resultsWithoutFilters), $resultsWithEmptyFilters);
        self::assertSame($resultsWithoutFilters[0]->document->id, $resultsWithEmptyFilters[0]->document->id);
    }

    public function testVectorSearchWithFiltersAndCustomOverFetch(): void
    {
        $db = $this->makeDb();

        // Create many non-matching documents and few matching ones
        for ($i = 0; $i < 50; $i++) {
            $db->addDocument(new Document(
                id: $i,
                vector: [1.0 - ($i * 0.01), $i * 0.01],
                metadata: ['group' => $i < 5 ? 'target' : 'other'],
            ));
        }

        $results = $db->vectorSearch(
            vector: [1.0, 0.0],
            k: 3,
            ef: null,
            filters: [MetadataFilter::eq('group', 'target')],
            overFetch: 10,
        );

        self::assertLessThanOrEqual(3, count($results));
        foreach ($results as $result) {
            self::assertSame('target', $result->document->metadata['group']);
        }
    }

    public function testVectorSearchWithMultipleFilters(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['type' => 'article', 'year' => 2024]));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], metadata: ['type' => 'article', 'year' => 2023]));
        $db->addDocument(new Document(id: 3, vector: [0.8, 0.2], metadata: ['type' => 'video', 'year' => 2024]));

        $results = $db->vectorSearch(
            vector: [1.0, 0.0],
            k: 10,
            filters: [
                MetadataFilter::eq('type', 'article'),
                MetadataFilter::eq('year', 2024),
            ],
        );

        self::assertCount(1, $results);
        self::assertSame(1, $results[0]->document->id);
    }

    public function testVectorSearchWithOrFilters(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['status' => 'published']));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], metadata: ['status' => 'draft']));
        $db->addDocument(new Document(id: 3, vector: [0.8, 0.2], metadata: ['status' => 'archived']));

        // OR group: status = 'published' OR status = 'draft'
        $results = $db->vectorSearch(
            vector: [1.0, 0.0],
            k: 10,
            filters: [
                [MetadataFilter::eq('status', 'published'), MetadataFilter::eq('status', 'draft')],
            ],
        );

        self::assertCount(2, $results);
    }

    public function testVectorSearchFiltersPreserveScoreOrdering(): void
    {
        $db = $this->makeDb();

        // Documents with descending similarity to [1.0, 0.0]
        $db->addDocument(new Document(id: 1, vector: [0.99, 0.01], metadata: ['visible' => true]));
        $db->addDocument(new Document(id: 2, vector: [0.95, 0.05], metadata: ['visible' => true]));
        $db->addDocument(new Document(id: 3, vector: [0.90, 0.10], metadata: ['visible' => true]));

        $results = $db->vectorSearch(
            vector: [1.0, 0.0],
            k: 3,
            filters: [MetadataFilter::eq('visible', true)],
        );

        self::assertCount(3, $results);
        // Results should be ordered by score (highest first)
        self::assertSame(1, $results[0]->document->id);
        self::assertSame(2, $results[1]->document->id);
        self::assertSame(3, $results[2]->document->id);
    }

    public function testVectorSearchFiltersRanksStartAtOne(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['active' => true]));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], metadata: ['active' => true]));

        $results = $db->vectorSearch(
            vector: [1.0, 0.0],
            k: 2,
            filters: [MetadataFilter::eq('active', true)],
        );

        self::assertSame(1, $results[0]->rank);
        self::assertSame(2, $results[1]->rank);
    }

    public function testVectorSearchExistingCallsWithoutFiltersWork(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['x' => 1]));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], metadata: ['x' => 2]));

        // Original API: vectorSearch(vector, k, ef)
        $results = $db->vectorSearch([1.0, 0.0], 2, 50);

        self::assertCount(2, $results);
        self::assertSame(1, $results[0]->document->id);
    }

    public function testVectorSearchUsesOverFetchMultiplierFromConstructor(): void
    {
        $db = new VectorDatabase(
            hnswConfig: new HNSWConfig(M: 8, efConstruction: 50, efSearch: 20),
            overFetchMultiplier: 2,
        );

        // Add docs where only 2 match, but they're not the top results without filtering
        for ($i = 0; $i < 20; $i++) {
            $db->addDocument(new Document(
                id: $i,
                vector: [1.0 - ($i * 0.04), $i * 0.04],
                metadata: ['target' => $i >= 18],
            ));
        }

        // With overFetchMultiplier=2 and k=2, we fetch 4 candidates
        // This may not find the matching docs if they're ranked > 4
        $results = $db->vectorSearch(
            vector: [1.0, 0.0],
            k: 2,
            filters: [MetadataFilter::eq('target', true)],
        );

        // Should return 0-2 results depending on where the matching docs rank
        self::assertLessThanOrEqual(2, count($results));
    }

    // ------------------------------------------------------------------
    // Text search with metadata filters
    // ------------------------------------------------------------------

    public function testTextSearchWithFiltersReturnsMatchingDocs(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'apple fruit', metadata: ['category' => 'produce']));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], text: 'apple iphone', metadata: ['category' => 'tech']));
        $db->addDocument(new Document(id: 3, vector: [0.8, 0.2], text: 'apple juice', metadata: ['category' => 'produce']));

        $results = $db->textSearch(
            query: 'apple',
            k: 10,
            filters: [MetadataFilter::eq('category', 'produce')],
        );

        self::assertCount(2, $results);
        foreach ($results as $result) {
            self::assertSame('produce', $result->document->metadata['category']);
        }
    }

    public function testTextSearchWithFiltersRespectsKLimit(): void
    {
        $db = $this->makeDb();

        for ($i = 0; $i < 10; $i++) {
            $db->addDocument(new Document(
                id: $i,
                vector: [1.0, 0.0],
                text: "document $i with common keywords",
                metadata: ['type' => 'match'],
            ));
        }

        $results = $db->textSearch(
            query: 'common keywords',
            k: 3,
            filters: [MetadataFilter::eq('type', 'match')],
        );

        self::assertCount(3, $results);
    }

    public function testTextSearchWithFiltersMayReturnFewerThanK(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'search term here', metadata: ['status' => 'active']));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], text: 'search term there', metadata: ['status' => 'inactive']));
        $db->addDocument(new Document(id: 3, vector: [0.8, 0.2], text: 'search term everywhere', metadata: ['status' => 'inactive']));

        $results = $db->textSearch(
            query: 'search term',
            k: 10,
            filters: [MetadataFilter::eq('status', 'active')],
        );

        self::assertCount(1, $results);
    }

    public function testTextSearchEmptyFiltersWorksLikeNoFilters(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'test document one', metadata: ['key' => 'value']));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], text: 'test document two', metadata: ['key' => 'other']));

        $resultsWithEmptyFilters = $db->textSearch('test document', 10, []);
        $resultsWithoutFilters = $db->textSearch('test document', 10);

        self::assertCount(count($resultsWithoutFilters), $resultsWithEmptyFilters);
        self::assertSame($resultsWithoutFilters[0]->document->id, $resultsWithEmptyFilters[0]->document->id);
    }

    public function testTextSearchWithFiltersAndCustomOverFetch(): void
    {
        $db = $this->makeDb();

        // Create many non-matching documents and few matching ones
        for ($i = 0; $i < 50; $i++) {
            $db->addDocument(new Document(
                id: $i,
                vector: [1.0, 0.0],
                text: "document $i with search keywords",
                metadata: ['group' => $i < 5 ? 'target' : 'other'],
            ));
        }

        $results = $db->textSearch(
            query: 'search keywords',
            k: 3,
            filters: [MetadataFilter::eq('group', 'target')],
            overFetch: 10,
        );

        self::assertLessThanOrEqual(3, count($results));
        foreach ($results as $result) {
            self::assertSame('target', $result->document->metadata['group']);
        }
    }

    public function testTextSearchWithMultipleFilters(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'news article about tech', metadata: ['type' => 'article', 'year' => 2024]));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], text: 'news article about sports', metadata: ['type' => 'article', 'year' => 2023]));
        $db->addDocument(new Document(id: 3, vector: [0.8, 0.2], text: 'news video about tech', metadata: ['type' => 'video', 'year' => 2024]));

        $results = $db->textSearch(
            query: 'news',
            k: 10,
            filters: [
                MetadataFilter::eq('type', 'article'),
                MetadataFilter::eq('year', 2024),
            ],
        );

        self::assertCount(1, $results);
        self::assertSame(1, $results[0]->document->id);
    }

    public function testTextSearchWithOrFilters(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'content published', metadata: ['status' => 'published']));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], text: 'content draft', metadata: ['status' => 'draft']));
        $db->addDocument(new Document(id: 3, vector: [0.8, 0.2], text: 'content archived', metadata: ['status' => 'archived']));

        // OR group: status = 'published' OR status = 'draft'
        $results = $db->textSearch(
            query: 'content',
            k: 10,
            filters: [
                [MetadataFilter::eq('status', 'published'), MetadataFilter::eq('status', 'draft')],
            ],
        );

        self::assertCount(2, $results);
    }

    public function testTextSearchFiltersRanksStartAtOne(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'ranking test document one', metadata: ['active' => true]));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], text: 'ranking test document two', metadata: ['active' => true]));

        $results = $db->textSearch(
            query: 'ranking test',
            k: 2,
            filters: [MetadataFilter::eq('active', true)],
        );

        self::assertSame(1, $results[0]->rank);
        self::assertSame(2, $results[1]->rank);
    }

    public function testTextSearchExistingCallsWithoutFiltersWork(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'existing api test', metadata: ['x' => 1]));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], text: 'another existing document', metadata: ['x' => 2]));

        // Original API: textSearch(query, k)
        $results = $db->textSearch('existing', 2);

        self::assertCount(2, $results);
    }

    public function testTextSearchUsesOverFetchMultiplierFromConstructor(): void
    {
        $db = new VectorDatabase(
            hnswConfig: new HNSWConfig(M: 8, efConstruction: 50, efSearch: 20),
            overFetchMultiplier: 2,
        );

        // Add docs where only 2 match, but they're not the top BM25 results without filtering
        for ($i = 0; $i < 20; $i++) {
            $db->addDocument(new Document(
                id: $i,
                vector: [1.0, 0.0],
                text: str_repeat("keyword ", 20 - $i), // Docs with fewer keywords will rank lower
                metadata: ['target' => $i >= 18],
            ));
        }

        // With overFetchMultiplier=2 and k=2, we fetch 4 candidates
        $results = $db->textSearch(
            query: 'keyword',
            k: 2,
            filters: [MetadataFilter::eq('target', true)],
        );

        // Should return 0-2 results depending on where the matching docs rank
        self::assertLessThanOrEqual(2, count($results));
    }

    public function testTextSearchWithFiltersEmptyQueryReturnsEmpty(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'some text', metadata: ['active' => true]));

        $results = $db->textSearch(
            query: '',
            k: 10,
            filters: [MetadataFilter::eq('active', true)],
        );

        self::assertSame([], $results);
    }

    // ------------------------------------------------------------------
    // Hybrid search with metadata filters
    // ------------------------------------------------------------------

    public function testHybridSearchWithFiltersReturnsMatchingDocs(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'apple fruit', metadata: ['category' => 'produce']));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], text: 'apple iphone', metadata: ['category' => 'tech']));
        $db->addDocument(new Document(id: 3, vector: [0.8, 0.2], text: 'apple juice', metadata: ['category' => 'produce']));

        $results = $db->hybridSearch(
            vector: [1.0, 0.0],
            text: 'apple',
            k: 10,
            filters: [MetadataFilter::eq('category', 'produce')],
        );

        self::assertCount(2, $results);
        foreach ($results as $result) {
            self::assertSame('produce', $result->document->metadata['category']);
        }
    }

    public function testHybridSearchWithFiltersRespectsKLimit(): void
    {
        $db = $this->makeDb();

        for ($i = 0; $i < 10; $i++) {
            $db->addDocument(new Document(
                id: $i,
                vector: [1.0 - ($i * 0.05), $i * 0.05],
                text: "document $i with common keywords",
                metadata: ['type' => 'match'],
            ));
        }

        $results = $db->hybridSearch(
            vector: [1.0, 0.0],
            text: 'common keywords',
            k: 3,
            filters: [MetadataFilter::eq('type', 'match')],
        );

        self::assertCount(3, $results);
    }

    public function testHybridSearchWithFiltersMayReturnFewerThanK(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'search term here', metadata: ['status' => 'active']));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], text: 'search term there', metadata: ['status' => 'inactive']));
        $db->addDocument(new Document(id: 3, vector: [0.8, 0.2], text: 'search term everywhere', metadata: ['status' => 'inactive']));

        $results = $db->hybridSearch(
            vector: [1.0, 0.0],
            text: 'search term',
            k: 10,
            filters: [MetadataFilter::eq('status', 'active')],
        );

        self::assertCount(1, $results);
    }

    public function testHybridSearchEmptyFiltersWorksLikeNoFilters(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'test document one', metadata: ['key' => 'value']));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], text: 'test document two', metadata: ['key' => 'other']));

        $resultsWithEmptyFilters = $db->hybridSearch(
            vector: [1.0, 0.0],
            text: 'test document',
            k: 10,
            filters: [],
        );
        $resultsWithoutFilters = $db->hybridSearch(
            vector: [1.0, 0.0],
            text: 'test document',
            k: 10,
        );

        self::assertCount(count($resultsWithoutFilters), $resultsWithEmptyFilters);
        self::assertSame($resultsWithoutFilters[0]->document->id, $resultsWithEmptyFilters[0]->document->id);
    }

    public function testHybridSearchWithFiltersAndCustomOverFetch(): void
    {
        $db = $this->makeDb();

        // Create many non-matching documents and few matching ones
        for ($i = 0; $i < 50; $i++) {
            $db->addDocument(new Document(
                id: $i,
                vector: [1.0 - ($i * 0.01), $i * 0.01],
                text: "document $i with search keywords",
                metadata: ['group' => $i < 5 ? 'target' : 'other'],
            ));
        }

        $results = $db->hybridSearch(
            vector: [1.0, 0.0],
            text: 'search keywords',
            k: 3,
            filters: [MetadataFilter::eq('group', 'target')],
            overFetch: 10,
        );

        self::assertLessThanOrEqual(3, count($results));
        foreach ($results as $result) {
            self::assertSame('target', $result->document->metadata['group']);
        }
    }

    public function testHybridSearchWithMultipleFilters(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'news article about tech', metadata: ['type' => 'article', 'year' => 2024]));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], text: 'news article about sports', metadata: ['type' => 'article', 'year' => 2023]));
        $db->addDocument(new Document(id: 3, vector: [0.8, 0.2], text: 'news video about tech', metadata: ['type' => 'video', 'year' => 2024]));

        $results = $db->hybridSearch(
            vector: [1.0, 0.0],
            text: 'news tech',
            k: 10,
            filters: [
                MetadataFilter::eq('type', 'article'),
                MetadataFilter::eq('year', 2024),
            ],
        );

        self::assertCount(1, $results);
        self::assertSame(1, $results[0]->document->id);
    }

    public function testHybridSearchWithOrFilters(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'content published', metadata: ['status' => 'published']));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], text: 'content draft', metadata: ['status' => 'draft']));
        $db->addDocument(new Document(id: 3, vector: [0.8, 0.2], text: 'content archived', metadata: ['status' => 'archived']));

        // OR group: status = 'published' OR status = 'draft'
        $results = $db->hybridSearch(
            vector: [1.0, 0.0],
            text: 'content',
            k: 10,
            filters: [
                [MetadataFilter::eq('status', 'published'), MetadataFilter::eq('status', 'draft')],
            ],
        );

        self::assertCount(2, $results);
    }

    public function testHybridSearchFiltersRanksStartAtOne(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'ranking test document one', metadata: ['active' => true]));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], text: 'ranking test document two', metadata: ['active' => true]));

        $results = $db->hybridSearch(
            vector: [1.0, 0.0],
            text: 'ranking test',
            k: 2,
            filters: [MetadataFilter::eq('active', true)],
        );

        self::assertSame(1, $results[0]->rank);
        self::assertSame(2, $results[1]->rank);
    }

    public function testHybridSearchExistingCallsWithoutFiltersWork(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'existing api test', metadata: ['x' => 1]));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], text: 'another existing document', metadata: ['x' => 2]));

        // Original API: hybridSearch(vector, text, k, fetchK, mode, vectorWeight, textWeight, rrfK)
        $results = $db->hybridSearch(
            vector: [1.0, 0.0],
            text: 'existing',
            k: 2,
            fetchK: 50,
            mode: HybridMode::RRF,
        );

        self::assertCount(2, $results);
    }

    public function testHybridSearchRRFWithFilters(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'apple orange', metadata: ['visible' => true]));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], text: 'banana mango', metadata: ['visible' => false]));
        $db->addDocument(new Document(id: 3, vector: [0.5, 0.5], text: 'apple mango hybrid', metadata: ['visible' => true]));

        $results = $db->hybridSearch(
            vector: [1.0, 0.0],
            text: 'apple',
            k: 3,
            mode: HybridMode::RRF,
            filters: [MetadataFilter::eq('visible', true)],
        );

        self::assertNotEmpty($results);
        foreach ($results as $result) {
            self::assertTrue($result->document->metadata['visible']);
        }
    }

    public function testHybridSearchWeightedWithFilters(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'php vector search', metadata: ['lang' => 'en']));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], text: 'sql relational database', metadata: ['lang' => 'de']));

        $results = $db->hybridSearch(
            vector: [1.0, 0.0],
            text: 'php',
            k: 2,
            mode: HybridMode::Weighted,
            vectorWeight: 0.6,
            textWeight: 0.4,
            filters: [MetadataFilter::eq('lang', 'en')],
        );

        self::assertNotEmpty($results);
        foreach ($results as $result) {
            self::assertSame('en', $result->document->metadata['lang']);
        }
    }

    // ------------------------------------------------------------------
    // Metadata patching
    // ------------------------------------------------------------------

    public function testPatchMetadataReturnsFalseForNonExistentDocument(): void
    {
        $db = $this->makeDb();
        $result = $db->patchMetadata('nonexistent', ['key' => 'value']);
        self::assertFalse($result);
    }

    public function testPatchMetadataReturnsTrueWhenDocumentExists(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['lang' => 'en']));

        $result = $db->patchMetadata(1, ['lang' => 'de']);
        self::assertTrue($result);
    }

    public function testPatchMetadataUpdatesExistingKey(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'test', metadata: ['lang' => 'en', 'status' => 'draft']));

        $db->patchMetadata(1, ['lang' => 'de']);

        $results = $db->vectorSearch([1.0, 0.0], 1);
        self::assertSame('de', $results[0]->document->metadata['lang']);
        self::assertSame('draft', $results[0]->document->metadata['status']); // other keys preserved
    }

    public function testPatchMetadataAddsNewKey(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['lang' => 'en']));

        $db->patchMetadata(1, ['author' => 'Alice']);

        $results = $db->vectorSearch([1.0, 0.0], 1);
        self::assertSame('en', $results[0]->document->metadata['lang']); // existing key preserved
        self::assertSame('Alice', $results[0]->document->metadata['author']); // new key added
    }

    public function testPatchMetadataRemovesKeyWhenValueIsNull(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['lang' => 'en', 'status' => 'draft']));

        $db->patchMetadata(1, ['status' => null]);

        $results = $db->vectorSearch([1.0, 0.0], 1);
        self::assertSame('en', $results[0]->document->metadata['lang']); // lang preserved
        self::assertArrayNotHasKey('status', $results[0]->document->metadata); // status removed
    }

    public function testPatchMetadataHandlesMultipleOperations(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: [
            'lang' => 'en',
            'status' => 'draft',
            'priority' => 'low',
        ]));

        $db->patchMetadata(1, [
            'lang' => 'de',        // update existing
            'author' => 'Bob',     // add new
            'status' => null,      // remove existing
        ]);

        $results = $db->vectorSearch([1.0, 0.0], 1);
        $metadata = $results[0]->document->metadata;

        self::assertSame('de', $metadata['lang']);
        self::assertSame('Bob', $metadata['author']);
        self::assertSame('low', $metadata['priority']);
        self::assertArrayNotHasKey('status', $metadata);
    }

    public function testPatchMetadataVisibleInVectorSearch(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['status' => 'draft']));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], metadata: ['status' => 'published']));

        $db->patchMetadata(1, ['status' => 'published']);

        $results = $db->vectorSearch([1.0, 0.0], 2);
        self::assertSame('published', $results[0]->document->metadata['status']);
    }

    public function testPatchMetadataVisibleInTextSearch(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'php vector', metadata: ['views' => 10]));

        $db->patchMetadata(1, ['views' => 100]);

        $results = $db->textSearch('php vector', 1);
        self::assertSame(100, $results[0]->document->metadata['views']);
    }

    public function testPatchMetadataVisibleInHybridSearch(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'php database', metadata: ['category' => 'backend']));

        $db->patchMetadata(1, ['category' => 'fullstack']);

        $results = $db->hybridSearch([1.0, 0.0], 'php database', 1);
        self::assertSame('fullstack', $results[0]->document->metadata['category']);
    }

    public function testPatchMetadataDoesNotAffectVectorOrText(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'original text', metadata: ['key' => 'old']));

        $db->patchMetadata(1, ['key' => 'new']);

        $results = $db->vectorSearch([1.0, 0.0], 1);
        self::assertSame([1.0, 0.0], $results[0]->document->vector);
        self::assertSame('original text', $results[0]->document->text);
    }

    public function testPatchMetadataWorksWithStringId(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 'doc-abc', vector: [1.0, 0.0], metadata: ['status' => 'draft']));

        $result = $db->patchMetadata('doc-abc', ['status' => 'published']);

        self::assertTrue($result);
        $results = $db->vectorSearch([1.0, 0.0], 1);
        self::assertSame('published', $results[0]->document->metadata['status']);
    }

    public function testPatchMetadataWorksWithIntId(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 123, vector: [1.0, 0.0], metadata: ['count' => 1]));

        $result = $db->patchMetadata(123, ['count' => 5]);

        self::assertTrue($result);
        $results = $db->vectorSearch([1.0, 0.0], 1);
        self::assertSame(5, $results[0]->document->metadata['count']);
    }

    public function testPatchMetadataWithEmptyPatch(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['key' => 'value']));

        $result = $db->patchMetadata(1, []);

        self::assertTrue($result);
        $results = $db->vectorSearch([1.0, 0.0], 1);
        self::assertSame('value', $results[0]->document->metadata['key']); // unchanged
    }

    public function testPatchMetadataPreservesDocumentIdAndVector(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 'test-123', vector: [0.5, 0.5], text: 'content', metadata: ['a' => 1]));

        $db->patchMetadata('test-123', ['b' => 2]);

        $results = $db->vectorSearch([0.5, 0.5], 1);
        self::assertSame('test-123', $results[0]->document->id);
        self::assertSame([0.5, 0.5], $results[0]->document->vector);
        self::assertSame('content', $results[0]->document->text);
        self::assertSame(1, $results[0]->document->metadata['a']);
        self::assertSame(2, $results[0]->document->metadata['b']);
    }

    public function testPatchMetadataWithPersistedDatabase(): void
    {
        $tmpDir = sys_get_temp_dir() . '/phpvector_test_' . uniqid();

        try {
            $db = new VectorDatabase(path: $tmpDir);
            $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'test', metadata: ['status' => 'draft']));
            $db->save();

            $db->patchMetadata(1, ['status' => 'published']);

            // Verify immediately visible
            $results = $db->vectorSearch([1.0, 0.0], 1);
            self::assertSame('published', $results[0]->document->metadata['status']);

            // Reload from disk to verify persistence
            $db2 = VectorDatabase::open($tmpDir);
            $results2 = $db2->vectorSearch([1.0, 0.0], 1);
            self::assertSame('published', $results2[0]->document->metadata['status']);
        } finally {
            // Cleanup
            if (is_dir($tmpDir)) {
                array_map('unlink', glob("$tmpDir/docs/*.bin") ?: []);
                @rmdir("$tmpDir/docs");
                @unlink("$tmpDir/meta.json");
                @unlink("$tmpDir/hnsw.bin");
                @unlink("$tmpDir/bm25.bin");
                @rmdir($tmpDir);
            }
        }
    }

    public function testPatchMetadataHnswIndexUnchanged(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['status' => 'draft']));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], metadata: ['status' => 'published']));
        $db->addDocument(new Document(id: 3, vector: [0.5, 0.5], metadata: ['status' => 'draft']));

        // Capture scores before patch
        $resultsBefore = $db->vectorSearch([1.0, 0.0], 3);
        $scoresBefore = array_map(fn ($r) => [$r->document->id, $r->score], $resultsBefore);

        // Patch metadata
        $db->patchMetadata(1, ['status' => 'archived', 'priority' => 'high']);

        // Capture scores after patch
        $resultsAfter = $db->vectorSearch([1.0, 0.0], 3);
        $scoresAfter = array_map(fn ($r) => [$r->document->id, $r->score], $resultsAfter);

        // HNSW index should produce identical scores and order
        self::assertSame($scoresBefore, $scoresAfter);
    }

    public function testPatchMetadataBm25IndexUnchanged(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'php vector database search', metadata: ['lang' => 'en']));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], text: 'php framework laravel', metadata: ['lang' => 'en']));
        $db->addDocument(new Document(id: 3, vector: [0.5, 0.5], text: 'vector embedding model', metadata: ['lang' => 'de']));

        // Capture scores before patch
        $resultsBefore = $db->textSearch('php vector', 3);
        $scoresBefore = array_map(fn ($r) => [$r->document->id, $r->score], $resultsBefore);

        // Patch metadata
        $db->patchMetadata(1, ['lang' => 'de', 'category' => 'database']);

        // Capture scores after patch
        $resultsAfter = $db->textSearch('php vector', 3);
        $scoresAfter = array_map(fn ($r) => [$r->document->id, $r->score], $resultsAfter);

        // BM25 index should produce identical scores and order
        self::assertSame($scoresBefore, $scoresAfter);
    }

    // ------------------------------------------------------------------
    // metadataSearch tests
    // ------------------------------------------------------------------

    public function testMetadataSearchReturnsAllMatchingDocuments(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['type' => 'article']));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], metadata: ['type' => 'blog']));
        $db->addDocument(new Document(id: 3, vector: [0.5, 0.5], metadata: ['type' => 'article']));

        $results = $db->metadataSearch([MetadataFilter::eq('type', 'article')]);

        self::assertCount(2, $results);
        self::assertSame(1, $results[0]->document->id);
        self::assertSame(3, $results[1]->document->id);
    }

    public function testMetadataSearchReturnsSearchResultWithScoreOne(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['status' => 'active']));

        $results = $db->metadataSearch([MetadataFilter::eq('status', 'active')]);

        self::assertCount(1, $results);
        self::assertInstanceOf(SearchResult::class, $results[0]);
        self::assertSame(1.0, $results[0]->score);
        self::assertSame(1, $results[0]->rank);
    }

    public function testMetadataSearchRespectsLimit(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['category' => 'tech']));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], metadata: ['category' => 'tech']));
        $db->addDocument(new Document(id: 3, vector: [0.5, 0.5], metadata: ['category' => 'tech']));

        $results = $db->metadataSearch([MetadataFilter::eq('category', 'tech')], limit: 2);

        self::assertCount(2, $results);
        self::assertSame(1, $results[0]->document->id);
        self::assertSame(2, $results[1]->document->id);
    }

    public function testMetadataSearchWithNullLimitReturnsAll(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['tag' => 'important']));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], metadata: ['tag' => 'important']));
        $db->addDocument(new Document(id: 3, vector: [0.5, 0.5], metadata: ['tag' => 'important']));

        $results = $db->metadataSearch([MetadataFilter::eq('tag', 'important')], limit: null);

        self::assertCount(3, $results);
    }

    public function testMetadataSearchSortsByMetadataKeyAscending(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['priority' => 3]));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], metadata: ['priority' => 1]));
        $db->addDocument(new Document(id: 3, vector: [0.5, 0.5], metadata: ['priority' => 2]));

        $results = $db->metadataSearch([], sortBy: 'priority', sortDirection: SortDirection::Asc);

        self::assertCount(3, $results);
        self::assertSame(2, $results[0]->document->id);
        self::assertSame(3, $results[1]->document->id);
        self::assertSame(1, $results[2]->document->id);
    }

    public function testMetadataSearchSortsByMetadataKeyDescending(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['score' => 10]));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], metadata: ['score' => 30]));
        $db->addDocument(new Document(id: 3, vector: [0.5, 0.5], metadata: ['score' => 20]));

        $results = $db->metadataSearch([], sortBy: 'score', sortDirection: SortDirection::Desc);

        self::assertCount(3, $results);
        self::assertSame(2, $results[0]->document->id);
        self::assertSame(3, $results[1]->document->id);
        self::assertSame(1, $results[2]->document->id);
    }

    public function testMetadataSearchSortsStringsAlphabetically(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['name' => 'Charlie']));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], metadata: ['name' => 'Alice']));
        $db->addDocument(new Document(id: 3, vector: [0.5, 0.5], metadata: ['name' => 'Bob']));

        $results = $db->metadataSearch([], sortBy: 'name', sortDirection: SortDirection::Asc);

        self::assertSame('Alice', $results[0]->document->metadata['name']);
        self::assertSame('Bob', $results[1]->document->metadata['name']);
        self::assertSame('Charlie', $results[2]->document->metadata['name']);
    }

    public function testMetadataSearchPlacesMissingSortKeyAtEnd(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['priority' => 2]));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], metadata: ['other' => 'value']));
        $db->addDocument(new Document(id: 3, vector: [0.5, 0.5], metadata: ['priority' => 1]));

        $results = $db->metadataSearch([], sortBy: 'priority', sortDirection: SortDirection::Asc);

        self::assertSame(3, $results[0]->document->id); // priority: 1
        self::assertSame(1, $results[1]->document->id); // priority: 2
        self::assertSame(2, $results[2]->document->id); // missing priority
    }

    public function testMetadataSearchAcceptsSortDirectionEnum(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['priority' => 3]));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], metadata: ['priority' => 1]));
        $db->addDocument(new Document(id: 3, vector: [0.8, 0.2], metadata: ['priority' => 2]));

        $results = $db->metadataSearch([], sortBy: 'priority', sortDirection: SortDirection::Desc);

        self::assertCount(3, $results);
        self::assertSame(3, $results[0]->document->metadata['priority']);
        self::assertSame(2, $results[1]->document->metadata['priority']);
        self::assertSame(1, $results[2]->document->metadata['priority']);
    }

    public function testMetadataSearchWithoutSortByReturnsInsertionOrder(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['value' => 100]));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], metadata: ['value' => 50]));
        $db->addDocument(new Document(id: 3, vector: [0.5, 0.5], metadata: ['value' => 200]));

        $results = $db->metadataSearch([]);

        self::assertSame(1, $results[0]->document->id);
        self::assertSame(2, $results[1]->document->id);
        self::assertSame(3, $results[2]->document->id);
    }

    public function testMetadataSearchEmptyFiltersReturnsAllDocuments(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['type' => 'a']));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], metadata: ['type' => 'b']));
        $db->addDocument(new Document(id: 3, vector: [0.5, 0.5], metadata: ['type' => 'c']));

        $results = $db->metadataSearch([]);

        self::assertCount(3, $results);
    }

    public function testMetadataSearchRanksStartAtOne(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['status' => 'active']));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], metadata: ['status' => 'active']));

        $results = $db->metadataSearch([MetadataFilter::eq('status', 'active')]);

        self::assertSame(1, $results[0]->rank);
        self::assertSame(2, $results[1]->rank);
    }

    public function testMetadataSearchWithMultipleFilters(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['type' => 'article', 'status' => 'published']));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], metadata: ['type' => 'article', 'status' => 'draft']));
        $db->addDocument(new Document(id: 3, vector: [0.5, 0.5], metadata: ['type' => 'blog', 'status' => 'published']));

        $results = $db->metadataSearch([
            MetadataFilter::eq('type', 'article'),
            MetadataFilter::eq('status', 'published'),
        ]);

        self::assertCount(1, $results);
        self::assertSame(1, $results[0]->document->id);
    }

    public function testMetadataSearchWithOrFilters(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['category' => 'tech']));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], metadata: ['category' => 'science']));
        $db->addDocument(new Document(id: 3, vector: [0.5, 0.5], metadata: ['category' => 'art']));

        $results = $db->metadataSearch([
            [MetadataFilter::eq('category', 'tech'), MetadataFilter::eq('category', 'science')],
        ]);

        self::assertCount(2, $results);
        self::assertSame(1, $results[0]->document->id);
        self::assertSame(2, $results[1]->document->id);
    }

    public function testMetadataSearchCombinedWithSortAndLimit(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['type' => 'item', 'price' => 30]));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], metadata: ['type' => 'item', 'price' => 10]));
        $db->addDocument(new Document(id: 3, vector: [0.5, 0.5], metadata: ['type' => 'item', 'price' => 20]));
        $db->addDocument(new Document(id: 4, vector: [0.3, 0.7], metadata: ['type' => 'other', 'price' => 5]));

        $results = $db->metadataSearch(
            [MetadataFilter::eq('type', 'item')],
            limit: 2,
            sortBy: 'price',
            sortDirection: SortDirection::Asc
        );

        self::assertCount(2, $results);
        self::assertSame(2, $results[0]->document->id); // price: 10
        self::assertSame(3, $results[1]->document->id); // price: 20
    }

    public function testMetadataSearchWorksWithPersistedDatabase(): void
    {
        $tmpDir = sys_get_temp_dir() . '/phpvector_test_' . uniqid();

        try {
            $db = new VectorDatabase(path: $tmpDir);
            $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'test', metadata: ['status' => 'active']));
            $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], text: 'test2', metadata: ['status' => 'inactive']));
            $db->save();

            // Test before reload
            $results = $db->metadataSearch([MetadataFilter::eq('status', 'active')]);
            self::assertCount(1, $results);
            self::assertSame(1, $results[0]->document->id);

            // Reload and test
            $db2 = VectorDatabase::open($tmpDir);
            $results2 = $db2->metadataSearch([MetadataFilter::eq('status', 'active')]);
            self::assertCount(1, $results2);
            self::assertSame(1, $results2[0]->document->id);
        } finally {
            if (is_dir($tmpDir)) {
                array_map('unlink', glob("$tmpDir/docs/*.bin") ?: []);
                @rmdir("$tmpDir/docs");
                @unlink("$tmpDir/meta.json");
                @unlink("$tmpDir/hnsw.bin");
                @unlink("$tmpDir/bm25.bin");
                @rmdir($tmpDir);
            }
        }
    }

    public function testMetadataSearchReturnsEmptyOnNoMatches(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['status' => 'active']));

        $results = $db->metadataSearch([MetadataFilter::eq('status', 'inactive')]);

        self::assertCount(0, $results);
    }

    public function testMetadataSearchExcludesDeletedDocuments(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], metadata: ['status' => 'active']));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], metadata: ['status' => 'active']));
        $db->addDocument(new Document(id: 3, vector: [0.8, 0.2], metadata: ['status' => 'active']));

        $db->deleteDocument(2);

        $results = $db->metadataSearch(
            filters: [MetadataFilter::eq('status', 'active')],
        );

        self::assertCount(2, $results);
        $ids = array_map(fn (SearchResult $r) => $r->document->id, $results);
        self::assertContains(1, $ids);
        self::assertContains(3, $ids);
        self::assertNotContains(2, $ids);
    }
}
