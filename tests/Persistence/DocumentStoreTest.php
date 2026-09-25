<?php

declare(strict_types=1);

namespace PHPVector\Tests\Persistence;

use PHPUnit\Framework\TestCase;
use PHPVector\Persistence\DocumentStore;

final class DocumentStoreTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/phpvtest_store_' . uniqid('', true);
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        rmdir($this->tmpDir);
    }

    private function requireAsync(): void
    {
        if (!DocumentStore::supportsAsync()) {
            self::markTestSkipped('Async writes need ext-pcntl and ext-posix.');
        }
    }

    public function testWriteIsSynchronousByDefault(): void
    {
        $store = new DocumentStore($this->tmpDir);
        $store->write(0, 'a', 'hello', ['k' => 'v']);

        self::assertSame(0, $store->pendingCount());
        self::assertSame(['a', 'hello', ['k' => 'v']], $store->read(0));
    }

    public function testAsyncWriteIsReadableAfterWaitAll(): void
    {
        $this->requireAsync();

        $store = new DocumentStore($this->tmpDir);
        $store->write(0, 'a', 'hello', ['k' => 'v'], async: true);
        $store->waitAll();

        self::assertSame(0, $store->pendingCount());
        self::assertSame(['a', 'hello', ['k' => 'v']], $store->read(0));
    }

    public function testFinishedChildrenAreReapedOnLaterWrites(): void
    {
        $this->requireAsync();

        $store = new DocumentStore($this->tmpDir);
        $total = 30;
        for ($i = 0; $i < $total; $i++) {
            $store->write($i, $i, "doc {$i}", [], async: true);
            usleep(20_000);
        }

        // Every child exits within milliseconds, so most of them must have been
        // reaped by the following writes instead of piling up until waitAll().
        self::assertLessThan($total, $store->pendingCount());

        $store->waitAll();
        self::assertSame([29, 'doc 29', []], $store->read(29));
    }

    public function testFailedAsyncWriteIsReportedByWaitAll(): void
    {
        $this->requireAsync();

        // The child logs its failure; keep that out of the test output.
        $previousLog = ini_set('error_log', '/dev/null');

        try {
            $store = new DocumentStore($this->tmpDir . '/missing-dir');
            $store->write(0, 'a', 'hello', [], async: true);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Async write of document file failed');
            $store->waitAll();
        } finally {
            ini_set('error_log', $previousLog === false ? '' : $previousLog);
        }
    }

    /**
     * The child must not run the shutdown functions and destructors it
     * inherits from the parent: in a host application they can close shared
     * resources such as a database connection.  Runs in a separate process so
     * that PHPUnit's own shutdown handlers stay out of the picture.
     */
    public function testAsyncChildRunsNoShutdownFunctionsOrDestructors(): void
    {
        $this->requireAsync();
        if (!function_exists('proc_open')) {
            self::markTestSkipped('proc_open() is not available.');
        }

        $marker   = $this->tmpDir . '/marker.log';
        $script   = $this->tmpDir . '/child.php';
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';

        file_put_contents($script, <<<'CHILD'
            <?php
            [, $autoload, $docsDir, $marker] = $argv;
            require $autoload;

            final class Witness
            {
                public function __construct(private string $marker) {}

                public function __destruct()
                {
                    file_put_contents($this->marker, 'destruct:' . getmypid() . "\n", FILE_APPEND);
                }
            }

            $witness = new Witness($marker);
            register_shutdown_function(static function () use ($marker): void {
                file_put_contents($marker, 'shutdown:' . getmypid() . "\n", FILE_APPEND);
            });

            $store = new PHPVector\Persistence\DocumentStore($docsDir);
            $store->write(0, 'a', 'hello', [], async: true);
            $store->waitAll();

            echo getmypid();
            CHILD);

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process     = proc_open([PHP_BINARY, $script, $autoload, $this->tmpDir, $marker], $descriptors, $pipes);
        if (!is_resource($process)) {
            self::markTestSkipped('Could not spawn a child PHP process.');
        }

        $parentPid = (int) stream_get_contents($pipes[1]);
        $stderr    = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        self::assertSame(0, proc_close($process), $stderr);

        self::assertFileExists($this->tmpDir . '/0.bin');
        self::assertSame(
            ["shutdown:{$parentPid}", "destruct:{$parentPid}"],
            file($marker, FILE_IGNORE_NEW_LINES),
            'Only the parent process may run shutdown functions and destructors.',
        );
    }
}
