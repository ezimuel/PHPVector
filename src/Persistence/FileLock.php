<?php

declare(strict_types=1);

namespace PHPVector\Persistence;

use PHPVector\Exception\LockTimeoutException;

/**
 * Advisory inter-process lock backed by flock() on a dedicated lock file.
 *
 * A VectorDatabase folder keeps a single `.lock` file next to its index files.
 * Writers (`VectorDatabase::save()`) take it exclusively, readers
 * (`VectorDatabase::open()`) take it in shared mode, so a reader never observes
 * the folder while index files are being replaced.
 *
 * Acquisition is always non-blocking (LOCK_NB) and retried in a loop until the
 * timeout expires: a stuck peer produces a LockTimeoutException instead of
 * hanging the caller forever.
 */
final class FileLock
{
    /** Default acquisition timeout, in seconds. */
    public const DEFAULT_TIMEOUT = 10.0;

    /** Pause between two acquisition attempts, in microseconds. */
    private const RETRY_INTERVAL_US = 20_000;

    /** @var resource|null Open handle while the lock is held, null otherwise. */
    private $handle = null;

    public function __construct(private readonly string $path) {}

    /** Release the lock if the owning object is garbage-collected. */
    public function __destruct()
    {
        $this->release();
    }

    /**
     * Take the lock in exclusive mode (writers).
     *
     * @throws LockTimeoutException when the lock is still held after $timeout seconds.
     * @throws \RuntimeException    when the lock file cannot be opened.
     */
    public function acquireExclusive(float $timeout = self::DEFAULT_TIMEOUT): void
    {
        $this->acquire(LOCK_EX, $timeout);
    }

    /**
     * Take the lock in shared mode (readers).
     *
     * @throws LockTimeoutException when an exclusive lock is still held after $timeout seconds.
     * @throws \RuntimeException    when the lock file cannot be opened.
     */
    public function acquireShared(float $timeout = self::DEFAULT_TIMEOUT): void
    {
        $this->acquire(LOCK_SH, $timeout);
    }

    /**
     * Release the lock. Safe to call when nothing is held, which makes it usable
     * from a `finally` block regardless of where the failure happened.
     */
    public function release(): void
    {
        if ($this->handle === null) {
            return;
        }

        @flock($this->handle, LOCK_UN);
        fclose($this->handle);
        $this->handle = null;
    }

    /** Whether this instance currently holds the lock. */
    public function isHeld(): bool
    {
        return $this->handle !== null;
    }

    /**
     * @param int   $operation LOCK_EX or LOCK_SH.
     * @param float $timeout   Seconds to keep retrying; 0 means a single attempt.
     */
    private function acquire(int $operation, float $timeout): void
    {
        if ($this->handle !== null) {
            throw new \LogicException("Lock already held: {$this->path}");
        }

        $dir = dirname($this->path);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create lock directory: {$dir}");
        }

        // 'c' creates the file when missing and never truncates it, so an
        // existing lock held by another process is not disturbed.
        $handle = @fopen($this->path, 'c');
        if ($handle === false) {
            throw new \RuntimeException("Failed to open lock file: {$this->path}");
        }

        $deadline = microtime(true) + max(0.0, $timeout);

        do {
            if (@flock($handle, $operation | LOCK_NB)) {
                $this->handle = $handle;
                return;
            }
            if (microtime(true) >= $deadline) {
                break;
            }
            usleep(self::RETRY_INTERVAL_US);
        } while (true);

        fclose($handle);
        throw LockTimeoutException::forPath($this->path, $timeout);
    }
}
