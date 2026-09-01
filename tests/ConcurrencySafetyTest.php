<?php

declare(strict_types=1);

namespace PHPVector\Tests;

use PHPUnit\Framework\TestCase;
use PHPVector\BM25\Config as BM25Config;
use PHPVector\BM25\SimpleTokenizer;
use PHPVector\Document;
use PHPVector\Exception\LockTimeoutException;
use PHPVector\Exception\VectorDatabaseException;
use PHPVector\HNSW\Config as HNSWConfig;
use PHPVector\Persistence\AtomicFile;
use PHPVector\Persistence\FileLock;
use PHPVector\VectorDatabase;

/**
 * Multi-process safety of the on-disk format: atomic file replacement and the
 * flock() based folder lock.
 */
final class ConcurrencySafetyTest extends TestCase
{
    private string $tmpDir;
    private string $lockPath;

    protected function setUp(): void
    {
        $this->tmpDir   = sys_get_temp_dir() . '/phpvconc_' . uniqid('', true);
        $this->lockPath = $this->tmpDir . '/.lock';
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
        foreach (array_diff((array) scandir($dir), ['.', '..']) as $entry) {
            $item = $dir . '/' . $entry;
            is_dir($item) ? $this->rrmdir($item) : unlink($item);
        }
        rmdir($dir);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeDb(float $lockTimeout = FileLock::DEFAULT_TIMEOUT): VectorDatabase
    {
        return new VectorDatabase(
            hnswConfig: new HNSWConfig(M: 8, efConstruction: 50, efSearch: 20),
            bm25Config: new BM25Config(),
            tokenizer: new SimpleTokenizer([]),
            path: $this->tmpDir,
            lockTimeout: $lockTimeout,
        );
    }

    private function seededDb(float $lockTimeout = FileLock::DEFAULT_TIMEOUT): VectorDatabase
    {
        $db = $this->makeDb($lockTimeout);
        $db->addDocument(new Document(id: 1, vector: [1.0, 0.0, 0.0], text: 'alpha one'));
        $db->addDocument(new Document(id: 2, vector: [0.0, 1.0, 0.0], text: 'beta two'));
        $db->addDocument(new Document(id: 3, vector: [0.0, 0.0, 1.0], text: 'gamma three'));

        return $db;
    }

    /**
     * Temporary files left behind by AtomicFile inside $dir.
     *
     * @return list<string>
     */
    private function temporaryFiles(string $dir): array
    {
        return glob($dir . '/.*.tmp') ?: [];
    }

    /**
     * Grab the folder lock from a second file handle.
     *
     * flock() locks belong to the *open file description*, not to the process,
     * so a second fopen() of the same file inside this process conflicts with
     * the handle opened by save()/open() exactly like a second process would.
     * This keeps the lock tests deterministic; the genuinely cross-process
     * behaviour is covered by testSaveWaitsForALockHeldByAnotherProcess().
     *
     * @return resource
     */
    private function grabLock(int $operation = LOCK_EX)
    {
        $handle = fopen($this->lockPath, 'c');
        self::assertIsResource($handle);
        self::assertTrue(flock($handle, $operation | LOCK_NB), 'Could not pre-acquire the folder lock.');

        return $handle;
    }

    /** @param resource $handle */
    private function dropLock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }

    /** Make the next write of $name fail: a directory can never be replaced by rename(). */
    private function blockIndexFile(string $name): void
    {
        mkdir($this->tmpDir . '/' . $name, 0755, true);
    }

    // ------------------------------------------------------------------
    // Regression: locking must not change the round-trip behaviour
    // ------------------------------------------------------------------

    public function testRoundTripIsUnchangedWithLocking(): void
    {
        $db = $this->seededDb();
        $db->save();

        $reopened = VectorDatabase::open(
            path: $this->tmpDir,
            hnswConfig: new HNSWConfig(M: 8, efConstruction: 50, efSearch: 20),
            bm25Config: new BM25Config(),
            tokenizer: new SimpleTokenizer([]),
        );

        self::assertSame(3, $reopened->count());

        $before = $db->vectorSearch([1.0, 0.1, 0.0], k: 1);
        $after  = $reopened->vectorSearch([1.0, 0.1, 0.0], k: 1);
        self::assertSame($before[0]->document->id, $after[0]->document->id);
        self::assertSame('alpha one', $after[0]->document->text);

        $text = $reopened->textSearch('gamma', k: 1);
        self::assertSame(3, $text[0]->document->id);
    }

    public function testSaveAndOpenCanBeRepeatedWhileTheLockFileExists(): void
    {
        $db = $this->seededDb();
        $db->save();
        $db->save();

        $db->addDocument(new Document(id: 4, vector: [0.5, 0.5, 0.0], text: 'delta four'));
        $db->save();

        self::assertSame(4, VectorDatabase::open($this->tmpDir, new HNSWConfig(M: 8, efConstruction: 50, efSearch: 20))->count());
    }

    // ------------------------------------------------------------------
    // Lock lifecycle
    // ------------------------------------------------------------------

    public function testSaveCreatesTheLockFileInTheIndexDirectory(): void
    {
        $this->seededDb()->save();

        self::assertFileExists($this->lockPath);
    }

    public function testLockIsReleasedAfterASuccessfulSave(): void
    {
        $this->seededDb()->save();

        $handle = fopen($this->lockPath, 'c');
        self::assertIsResource($handle);
        self::assertTrue(
            flock($handle, LOCK_EX | LOCK_NB),
            'save() must release the exclusive lock when it completes.',
        );
        $this->dropLock($handle);
    }

    public function testLockIsReleasedAfterAFailedSave(): void
    {
        $db = $this->seededDb();
        $this->blockIndexFile('hnsw.bin');

        try {
            $db->save();
            self::fail('save() was expected to fail while hnsw.bin cannot be replaced.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('hnsw.bin', $e->getMessage());
        }

        $handle = fopen($this->lockPath, 'c');
        self::assertIsResource($handle);
        self::assertTrue(
            flock($handle, LOCK_EX | LOCK_NB),
            'save() must release the exclusive lock even when it throws.',
        );
        $this->dropLock($handle);
    }

    public function testOpenReleasesTheSharedLock(): void
    {
        $this->seededDb()->save();
        VectorDatabase::open($this->tmpDir, new HNSWConfig(M: 8, efConstruction: 50, efSearch: 20));

        $handle = fopen($this->lockPath, 'c');
        self::assertIsResource($handle);
        self::assertTrue(
            flock($handle, LOCK_EX | LOCK_NB),
            'open() must release the shared lock when it returns.',
        );
        $this->dropLock($handle);
    }

    // ------------------------------------------------------------------
    // Lock contention
    // ------------------------------------------------------------------

    public function testSaveTimesOutWhenTheLockIsAlreadyHeld(): void
    {
        $db     = $this->seededDb(lockTimeout: 0.2);
        $holder = $this->grabLock(LOCK_EX);

        $start = microtime(true);
        try {
            $db->save();
            self::fail('save() was expected to time out while the folder lock is held.');
        } catch (LockTimeoutException $e) {
            $elapsed = microtime(true) - $start;
            self::assertGreaterThanOrEqual(0.15, $elapsed, 'save() gave up before the timeout expired.');
            self::assertLessThan(5.0, $elapsed, 'save() waited far longer than the configured timeout.');
            self::assertStringContainsString('.lock', $e->getMessage());
            self::assertInstanceOf(VectorDatabaseException::class, $e);
        } finally {
            $this->dropLock($holder);
        }

        // The very same instance must save cleanly once the lock is free.
        $db->save();
        self::assertFileExists($this->tmpDir . '/hnsw.bin');
    }

    public function testOpenTimesOutWhenAWriterHoldsTheLock(): void
    {
        $this->seededDb()->save();

        $holder = $this->grabLock(LOCK_EX);

        try {
            $this->expectException(LockTimeoutException::class);
            VectorDatabase::open(
                path: $this->tmpDir,
                hnswConfig: new HNSWConfig(M: 8, efConstruction: 50, efSearch: 20),
                lockTimeout: 0.2,
            );
        } finally {
            $this->dropLock($holder);
        }
    }

    public function testOpenSucceedsWhileAnotherReaderHoldsTheSharedLock(): void
    {
        $this->seededDb()->save();

        $reader = $this->grabLock(LOCK_SH);

        try {
            $db = VectorDatabase::open(
                path: $this->tmpDir,
                hnswConfig: new HNSWConfig(M: 8, efConstruction: 50, efSearch: 20),
                lockTimeout: 0.2,
            );
            self::assertSame(3, $db->count());
        } finally {
            $this->dropLock($reader);
        }
    }

    public function testSaveIsBlockedWhileAReaderHoldsTheSharedLock(): void
    {
        $db     = $this->seededDb(lockTimeout: 0.2);
        $reader = $this->grabLock(LOCK_SH);

        try {
            $this->expectException(LockTimeoutException::class);
            $db->save();
        } finally {
            $this->dropLock($reader);
        }
    }

    /**
     * Genuine inter-process contention: a child PHP process holds the lock and
     * only releases it when its stdin is closed, so the parent's save() has to
     * wait for it through the retry loop.
     */
    public function testSaveWaitsForALockHeldByAnotherProcess(): void
    {
        if (!function_exists('proc_open')) {
            self::markTestSkipped('proc_open() is not available.');
        }

        $script = $this->tmpDir . '/hold_lock.php';
        file_put_contents($script, <<<'CHILD'
            <?php
            $fh = fopen($argv[1], 'c');
            if ($fh === false || !flock($fh, LOCK_EX)) {
                exit(1);
            }
            echo "locked\n";
            fgets(STDIN);          // block until the parent closes stdin
            flock($fh, LOCK_UN);
            exit(0);
            CHILD);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process     = proc_open([PHP_BINARY, $script, $this->lockPath], $descriptors, $pipes);
        if (!is_resource($process)) {
            self::markTestSkipped('Could not spawn a child PHP process.');
        }

        try {
            self::assertSame("locked\n", fgets($pipes[1]), 'The child process did not take the lock.');

            // With the child holding it, a short timeout must fail fast.
            $db = $this->seededDb(lockTimeout: 0.2);
            try {
                $db->save();
                self::fail('save() was expected to time out while another process holds the lock.');
            } catch (LockTimeoutException) {
                self::assertFileDoesNotExist($this->tmpDir . '/hnsw.bin');
            }

            // Releasing the child lets the retry loop succeed.
            fclose($pipes[0]);
            $pipes[0] = null;
            self::assertSame(0, $this->waitForExit($process));

            $db->save();
            self::assertFileExists($this->tmpDir . '/hnsw.bin');
            self::assertSame(3, VectorDatabase::open($this->tmpDir, new HNSWConfig(M: 8, efConstruction: 50, efSearch: 20))->count());
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_close($process);
        }
    }

    /**
     * @param resource $process
     */
    private function waitForExit($process): int
    {
        $deadline = microtime(true) + 5.0;
        do {
            $status = proc_get_status($process);
            if ($status['running'] === false) {
                return $status['exitcode'];
            }
            usleep(10_000);
        } while (microtime(true) < $deadline);

        self::fail('The child process did not exit within 5 seconds.');
    }

    // ------------------------------------------------------------------
    // Atomic writes
    // ------------------------------------------------------------------

    public function testAtomicWriteReplacesTheFileInPlace(): void
    {
        $target = $this->tmpDir . '/payload.bin';
        AtomicFile::write($target, 'first');
        AtomicFile::write($target, 'second');

        self::assertSame('second', file_get_contents($target));
        self::assertSame([], $this->temporaryFiles($this->tmpDir));
    }

    public function testAtomicWriteKeepsThePreviousContentWhenTheWriterFails(): void
    {
        $target = $this->tmpDir . '/payload.bin';
        AtomicFile::write($target, 'GOOD');

        try {
            AtomicFile::writeStream($target, static function ($handle): void {
                fwrite($handle, 'PARTIAL');
                throw new \RuntimeException('write interrupted');
            });
            self::fail('The failing writer should have propagated its exception.');
        } catch (\RuntimeException $e) {
            self::assertSame('write interrupted', $e->getMessage());
        }

        self::assertSame('GOOD', file_get_contents($target), 'A failed write must not replace the good file.');
        self::assertSame([], $this->temporaryFiles($this->tmpDir), 'The temporary file must be cleaned up.');
    }

    public function testFailedSaveLeavesNoPartialIndexFileAndNoTemporaries(): void
    {
        $db = $this->seededDb();
        $db->save();

        $goodHnsw = (string) file_get_contents($this->tmpDir . '/hnsw.bin');
        $goodBm25 = (string) file_get_contents($this->tmpDir . '/bm25.bin');

        // Adding a document changes the state that the next save() would flush.
        $db->addDocument(new Document(id: 4, vector: [0.5, 0.5, 0.0], text: 'delta four'));

        // Make the hnsw.bin replacement fail after meta.json was already written.
        unlink($this->tmpDir . '/hnsw.bin');
        $this->blockIndexFile('hnsw.bin');

        $this->expectException(\RuntimeException::class);

        try {
            $db->save();
        } finally {
            self::assertSame([], $this->temporaryFiles($this->tmpDir), 'No temporary file may survive a failed save().');
            self::assertSame($goodBm25, file_get_contents($this->tmpDir . '/bm25.bin'), 'bm25.bin must be untouched.');
            self::assertNotSame('', $goodHnsw);
        }
    }

    public function testDocumentFilesAreWrittenWithoutTemporaryLeftovers(): void
    {
        $db = $this->seededDb();
        $db->save();

        $docsDir = $this->tmpDir . '/docs';
        self::assertSame([], $this->temporaryFiles($docsDir));
        self::assertCount(3, glob($docsDir . '/*.bin') ?: []);
    }

    // ------------------------------------------------------------------
    // Configuration
    // ------------------------------------------------------------------

    public function testNegativeLockTimeoutIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new VectorDatabase(path: $this->tmpDir, lockTimeout: -1.0);
    }

    public function testDefaultLockTimeoutIsTenSeconds(): void
    {
        self::assertSame(10.0, FileLock::DEFAULT_TIMEOUT);
    }

    public function testFileLockRefusesADoubleAcquisition(): void
    {
        $lock = new FileLock($this->lockPath);
        $lock->acquireExclusive(0.1);

        try {
            self::assertTrue($lock->isHeld());
            $this->expectException(\LogicException::class);
            $lock->acquireShared(0.1);
        } finally {
            $lock->release();
        }
    }

    public function testFileLockReleaseIsIdempotent(): void
    {
        $lock = new FileLock($this->lockPath);
        $lock->acquireExclusive(0.1);
        $lock->release();
        $lock->release();

        self::assertFalse($lock->isHeld());
    }
}
