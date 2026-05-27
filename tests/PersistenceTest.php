<?php

declare(strict_types=1);

namespace PHPVector\Tests;

use PHPUnit\Framework\TestCase;
use PHPVector\BM25\Config as BM25Config;
use PHPVector\BM25\SimpleTokenizer;
use PHPVector\Distance;
use PHPVector\Document;
use PHPVector\HNSW\Config as HNSWConfig;
use PHPVector\HybridMode;
use PHPVector\VectorDatabase;

final class PersistenceTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpvtest_' . uniqid('', true);
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tmpDir);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach ((array) glob($dir . '/*') as $item) {
            is_dir($item) ? $this->rrmdir((string) $item) : unlink((string) $item);
        }
        rmdir($dir);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeDb(): VectorDatabase
    {
        return new VectorDatabase(
            hnswConfig: new HNSWConfig(M: 8, efConstruction: 50, efSearch: 20),
            bm25Config: new BM25Config(),
            tokenizer: new SimpleTokenizer([]),
            path: $this->tmpDir,
        );
    }

    private function openDb(): VectorDatabase
    {
        return VectorDatabase::open(
            path: $this->tmpDir,
            hnswConfig: new HNSWConfig(M: 8, efConstruction: 50, efSearch: 20),
            bm25Config: new BM25Config(),
            tokenizer: new SimpleTokenizer([]),
        );
    }

    // ------------------------------------------------------------------
    // Round-trip: vector / text / hybrid search parity
    // ------------------------------------------------------------------

    public function testRoundTripVectorSearch(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0, 0.0, 0.0], text: 'alpha one'));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0, 0.0, 0.0], text: 'beta two'));
        $db->addDocument(new Document(id: 3, vector: [0.0, 0.0, 1.0, 0.0], text: 'gamma three'));
        $db->addDocument(new Document(id: 4, vector: [0.0, 0.0, 0.0, 1.0], text: 'delta four'));

        $query  = [1.0, 0.1, 0.0, 0.0];
        $before = $db->vectorSearch($query, k: 1);

        $db->save();
        $loaded = $this->openDb();
        $after  = $loaded->vectorSearch($query, k: 1);

        self::assertSame($before[0]->document->id, $after[0]->document->id);
    }

    public function testRoundTripTextSearch(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 'a', vector: [0.1, 0.9], text: 'machine learning vector search'));
        $db->addDocument(new Document(id: 'b', vector: [0.5, 0.5], text: 'database storage systems'));
        $db->addDocument(new Document(id: 'c', vector: [0.9, 0.1], text: 'neural network deep learning'));

        $query  = 'machine learning';
        $before = $db->textSearch($query, k: 1);

        $db->save();
        $loaded = $this->openDb();
        $after  = $loaded->textSearch($query, k: 1);

        self::assertSame($before[0]->document->id, $after[0]->document->id);
    }

    public function testRoundTripHybridSearchRRF(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 10, vector: [1.0, 0.0], text: 'php vector database search'));
        $db->addDocument(new Document(id: 20, vector: [0.0, 1.0], text: 'sql relational storage'));

        $query  = [1.0, 0.0];
        $text   = 'vector';
        $before = $db->hybridSearch($query, $text, k: 1, mode: HybridMode::RRF);

        $db->save();
        $loaded = $this->openDb();
        $after  = $loaded->hybridSearch($query, $text, k: 1, mode: HybridMode::RRF);

        self::assertSame($before[0]->document->id, $after[0]->document->id);
    }

    public function testRoundTripHybridSearchWeighted(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 10, vector: [1.0, 0.0], text: 'php vector database search'));
        $db->addDocument(new Document(id: 20, vector: [0.0, 1.0], text: 'sql relational storage'));

        $query  = [1.0, 0.0];
        $text   = 'vector';
        $before = $db->hybridSearch($query, $text, k: 1, mode: HybridMode::Weighted);

        $db->save();
        $loaded = $this->openDb();
        $after  = $loaded->hybridSearch($query, $text, k: 1, mode: HybridMode::Weighted);

        self::assertSame($before[0]->document->id, $after[0]->document->id);
    }

    // ------------------------------------------------------------------
    // Document fidelity
    // ------------------------------------------------------------------

    public function testCountMatchesAfterRoundTrip(): void
    {
        $db = $this->makeDb();

        for ($i = 0; $i < 10; $i++) {
            $db->addDocument(new Document(
                id: $i,
                vector: [sin($i), cos($i)],
                text: "document number {$i}",
            ));
        }

        $db->save();
        $loaded = $this->openDb();

        self::assertSame(10, $loaded->count());
    }

    public function testMetadataAndTextPreservedExactly(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(
            id: 'doc-meta',
            vector: [0.5, 0.5],
            text: 'some full-text content',
            metadata: ['key' => 'value', 'nested' => ['a' => 1, 'b' => true], 'num' => 3.14],
        ));
        $db->addDocument(new Document(
            id: 'no-text',
            vector: [0.1, 0.9],
            text: null,
        ));
        $db->addDocument(new Document(
            id: 'empty-meta',
            vector: [0.9, 0.1],
            text: 'only text',
        ));

        $db->save();
        $loaded  = $this->openDb();

        $results = $loaded->vectorSearch([0.5, 0.5], k: 3);
        $byId    = [];
        foreach ($results as $r) {
            $byId[$r->document->id] = $r->document;
        }

        self::assertArrayHasKey('doc-meta', $byId);
        $restored = $byId['doc-meta'];
        self::assertSame('some full-text content', $restored->text);
        self::assertSame('value', $restored->metadata['key']);
        self::assertSame(['a' => 1, 'b' => true], $restored->metadata['nested']);
        self::assertEqualsWithDelta(3.14, $restored->metadata['num'], 1e-9);

        self::assertArrayHasKey('no-text', $byId);
        self::assertNull($byId['no-text']->text);
        self::assertSame([], $byId['no-text']->metadata);

        self::assertArrayHasKey('empty-meta', $byId);
        self::assertSame([], $byId['empty-meta']->metadata);
    }

    public function testStringAndIntDocumentIdsPreserved(): void
    {
        $db = $this->makeDb();

        $db->addDocument(new Document(id: 42, vector: [1.0, 0.0]));
        $db->addDocument(new Document(id: 'str', vector: [0.0, 1.0]));

        $db->save();
        $loaded = $this->openDb();

        $intResult = $loaded->vectorSearch([1.0, 0.0], k: 1);
        self::assertSame(42, $intResult[0]->document->id);
        self::assertIsInt($intResult[0]->document->id);

        $strResult = $loaded->vectorSearch([0.0, 1.0], k: 1);
        self::assertSame('str', $strResult[0]->document->id);
        self::assertIsString($strResult[0]->document->id);
    }

    // ------------------------------------------------------------------
    // Folder structure
    // ------------------------------------------------------------------

    public function testFolderStructureIsCreated(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0]));
        $db->save();

        self::assertFileExists($this->tmpDir . '/meta.json');
        self::assertFileExists($this->tmpDir . '/hnsw.bin');
        self::assertFileExists($this->tmpDir . '/bm25.bin');
        self::assertDirectoryExists($this->tmpDir . '/docs');
        self::assertFileExists($this->tmpDir . '/docs/0.bin');
    }

    public function testDocFilesAreWrittenPerNode(): void
    {
        $db = $this->makeDb();

        for ($i = 0; $i < 5; $i++) {
            $db->addDocument(new Document(id: $i, vector: [(float) $i, 0.0]));
        }
        $db->save();

        $files = glob($this->tmpDir . '/docs/*.bin');
        self::assertCount(5, $files);
    }

    public function testEmptyDatabaseRoundTrip(): void
    {
        $db = $this->makeDb();
        $db->save();
        $loaded = $this->openDb();

        self::assertSame(0, $loaded->count());
        self::assertSame([], $loaded->vectorSearch([1.0, 0.0], k: 5));
        self::assertSame([], $loaded->textSearch('anything', k: 5));
    }

    // ------------------------------------------------------------------
    // Lazy loading
    // ------------------------------------------------------------------

    public function testLazyLoadingEnrichesStubs(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(
            id: 'lazy-doc',
            vector: [1.0, 0.0],
            text: 'lazy loading test',
            metadata: ['loaded' => true],
        ));
        $db->save();

        $loaded  = $this->openDb();
        $results = $loaded->vectorSearch([1.0, 0.0], k: 1);

        self::assertCount(1, $results);
        self::assertSame('lazy-doc', $results[0]->document->id);
        self::assertSame('lazy loading test', $results[0]->document->text);
        self::assertSame(['loaded' => true], $results[0]->document->metadata);
    }

    // ------------------------------------------------------------------
    // Auto UUID
    // ------------------------------------------------------------------

    public function testAutoUuidAssignedWhenIdIsNull(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(vector: [1.0, 0.0]));
        $db->save();

        $loaded  = $this->openDb();
        $results = $loaded->vectorSearch([1.0, 0.0], k: 1);

        self::assertCount(1, $results);
        self::assertIsString($results[0]->document->id);
        // UUID v4 format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            (string) $results[0]->document->id,
        );
    }

    public function testTwoNullIdDocumentsGetDistinctUuids(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(vector: [1.0, 0.0]));
        $db->addDocument(new Document(vector: [0.0, 1.0]));
        $db->save();

        $loaded   = $this->openDb();
        $results1 = $loaded->vectorSearch([1.0, 0.0], k: 1);
        $results2 = $loaded->vectorSearch([0.0, 1.0], k: 1);

        self::assertNotSame($results1[0]->document->id, $results2[0]->document->id);
    }

    // ------------------------------------------------------------------
    // Error cases
    // ------------------------------------------------------------------

    public function testSaveThrowsWhenNoPathConfigured(): void
    {
        $db = new VectorDatabase();
        $this->expectException(\RuntimeException::class);
        $db->save();
    }

    public function testOpenThrowsOnMissingFolder(): void
    {
        $this->expectException(\RuntimeException::class);
        VectorDatabase::open($this->tmpDir . '/does_not_exist');
    }

    public function testOpenThrowsOnDistanceMismatch(): void
    {
        $db = new VectorDatabase(
            hnswConfig: new HNSWConfig(distance: Distance::Cosine),
            path: $this->tmpDir,
        );
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0]));
        $db->save();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/[Dd]istance/');

        VectorDatabase::open(
            path: $this->tmpDir,
            hnswConfig: new HNSWConfig(distance: Distance::Euclidean),
        );
    }

    // ------------------------------------------------------------------
    // Incremental save
    // ------------------------------------------------------------------

    public function testIncrementalSave(): void
    {
        // First save: 2 documents.
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'first'));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], text: 'second'));
        $db->save();

        // Add more documents to a fresh instance opened from disk.
        $loaded = $this->openDb();
        $loaded->addDocument(new Document(id: 3, vector: [0.5, 0.5], text: 'third'));
        $loaded->save();

        // Re-open and verify all three documents are accessible.
        $final   = $this->openDb();
        self::assertSame(3, $final->count());

        $results = $final->vectorSearch([1.0, 0.0], k: 3);
        $ids     = array_map(fn($r) => $r->document->id, $results);
        self::assertContains(1, $ids);
    }

    // ------------------------------------------------------------------
    // Delete persistence
    // ------------------------------------------------------------------

    public function testDeletedDocumentsArePersistedAndExcluded(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'first'));
        $db->addDocument(new Document(id: 2, vector: [0.9, 0.1], text: 'second'));
        $db->addDocument(new Document(id: 3, vector: [0.0, 1.0], text: 'third'));

        // Delete document 1
        $db->deleteDocument(1);
        $db->save();

        // Reload and verify
        $loaded = $this->openDb();
        self::assertSame(2, $loaded->count());

        // Document 1 should not appear in results
        $results = $loaded->vectorSearch([1.0, 0.0], k: 3);
        $ids     = array_map(fn($r) => $r->document->id, $results);
        self::assertNotContains(1, $ids);
        self::assertContains(2, $ids);
    }

    public function testDeletedDocumentFileIsRemoved(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'to delete'));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0], text: 'to keep'));
        $db->save();

        // Verify doc files exist after initial save.
        self::assertFileExists($this->tmpDir . '/docs/0.bin');
        self::assertFileExists($this->tmpDir . '/docs/1.bin');

        // Delete document 1 (nodeId 0).
        // Physical removal is deferred to save() for crash-safety; a tombstone
        // is written immediately so the deletion survives a crash.
        $db->deleteDocument(1);

        self::assertFileExists($this->tmpDir . '/docs/0.tombstone', 'Tombstone must be created by deleteDocument().');
        self::assertFileExists($this->tmpDir . '/docs/0.bin', 'Doc file must NOT be removed before save().');
        self::assertFileExists($this->tmpDir . '/docs/1.bin');

        // After save() both the doc file and the tombstone must be gone.
        $db->save();

        self::assertFileDoesNotExist($this->tmpDir . '/docs/0.bin');
        self::assertFileDoesNotExist($this->tmpDir . '/docs/0.tombstone');
        self::assertFileExists($this->tmpDir . '/docs/1.bin');
    }

    public function testUpdateDocumentPersistsCorrectly(): void
    {
        $db = $this->makeDb();
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0], text: 'original'));
        $db->save();

        // Update the document
        $db->updateDocument(new Document(
            id: 1,
            vector: [0.0, 1.0],
            text: 'updated content',
            metadata: ['version' => 2],
        ));
        $db->save();

        // Reload and verify
        $loaded  = $this->openDb();
        $results = $loaded->vectorSearch([0.0, 1.0], k: 1);

        self::assertSame(1, $results[0]->document->id);
        self::assertSame('updated content', $results[0]->document->text);
        self::assertSame(['version' => 2], $results[0]->document->metadata);
    }
}
