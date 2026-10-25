<?php

declare(strict_types=1);

namespace PHPVector\Persistence;

/**
 * Per-document file store inside a VectorDatabase folder.
 *
 * Each document is persisted as `{docsDir}/{nodeId}.bin`.
 *
 * File format (per document):
 *   [1 byte : idType  (0 = int64, 1 = string)]
 *   [8 bytes: int64   (idType 0)]  OR  [4 bytes: len + len bytes string  (idType 1)]
 *   [4 bytes: textLen]  [textLen bytes: utf-8 text]   (textLen=0 → null text)
 *   [4 bytes: metaLen]  [metaLen bytes: JSON metadata] (metaLen=0 → empty array)
 *
 * Writes are synchronous by default.  Async writes dispatch each one to a
 * forked child process (pcntl_fork) and need both ext-pcntl and ext-posix;
 * without them they fall back to synchronous writes.  Call waitAll() before
 * reading back or writing index files.
 *
 * An async child ends by sending itself SIGKILL instead of calling exit(), so
 * it never runs the shutdown functions and destructors it inherited from the
 * parent (which could, for example, close a database connection the parent
 * still uses).  Finished children are reaped on every async write, so a long
 * import does not accumulate zombie processes.
 *
 * Every write goes through AtomicFile, so a reader loading `{nodeId}.bin`
 * lazily always sees a complete record: either the previous version or the new
 * one, never a truncated file.
 *
 * Document files are NOT covered by the folder lock held by
 * VectorDatabase::save(): they are written incrementally, outside of it.  Two
 * processes rewriting the same node (VectorDatabase::patchMetadata()) therefore
 * resolve last-writer-wins rather than being serialised.
 */
final class DocumentStore
{
    /**
     * PIDs of outstanding async child processes, keyed by nodeId.
     * Keying by nodeId lets waitForNode() drain exactly one write without
     * blocking every other in-flight write.
     *
     * @var array<int, int>  nodeId → PID
     */
    private array $pendingPids = [];

    public function __construct(private readonly string $docsDir) {}

    // ------------------------------------------------------------------
    // Write
    // ------------------------------------------------------------------

    /**
     * Persist a document to disk.
     *
     * When $async is true and async writes are supported (see
     * supportsAsync()) the write is dispatched to a child process and the
     * parent returns immediately.  Otherwise the write is synchronous.
     *
     * @param int             $nodeId
     * @param string|int      $docId    Must NOT be null (UUID already assigned by caller).
     * @param string|null     $text
     * @param array<string, mixed> $metadata
     * @param bool            $async
     *
     * @throws \RuntimeException if a synchronous write fails.
     */
    public function write(
        int $nodeId,
        string|int $docId,
        ?string $text,
        array $metadata,
        bool $async = false,
    ): void {
        if ($async && self::supportsAsync()) {
            $this->reapFinished();

            $pid = pcntl_fork();

            if ($pid === 0) {
                // Child: never return into the caller's code, whatever happens.
                try {
                    $this->writeSync($nodeId, $docId, $text, $metadata);
                } catch (\Throwable $e) {
                    // The parent notices the missing file when it reaps this child.
                    error_log(sprintf('PHPVector: async write of node %d failed: %s', $nodeId, $e->getMessage()));
                } finally {
                    posix_kill(getmypid(), SIGKILL);
                }
            }

            if ($pid > 0) {
                $this->pendingPids[$nodeId] = $pid;
                return;
            }

            // Fork failed: fall through to a synchronous write.
        }

        $this->writeSync($nodeId, $docId, $text, $metadata);
    }

    /**
     * Whether async writes can run in this process: forking needs ext-pcntl,
     * and ending the child without running shutdown code needs ext-posix.
     */
    public static function supportsAsync(): bool
    {
        return function_exists('pcntl_fork')
            && function_exists('pcntl_waitpid')
            && function_exists('posix_kill');
    }

    /** Number of async writes started but not yet reaped. */
    public function pendingCount(): int
    {
        return count($this->pendingPids);
    }

    /**
     * Block until the async write for a specific node has completed.
     *
     * Use this before deleting a node's file so a late child write cannot
     * recreate {nodeId}.bin after the unlink().
     *
     * @throws \RuntimeException if the child finished without writing the file.
     */
    public function waitForNode(int $nodeId): void
    {
        if (!isset($this->pendingPids[$nodeId])) {
            return;
        }

        pcntl_waitpid($this->pendingPids[$nodeId], $status);
        unset($this->pendingPids[$nodeId]);
        $this->assertWritten($nodeId);
    }

    /**
     * Block until every outstanding async write has completed.
     * Must be called before index files are written (see VectorDatabase::save()).
     *
     * @throws \RuntimeException if a child finished without writing its file.
     */
    public function waitAll(): void
    {
        $pending = $this->pendingPids;
        $this->pendingPids = [];

        foreach ($pending as $pid) {
            pcntl_waitpid($pid, $status);
        }
        foreach (array_keys($pending) as $nodeId) {
            $this->assertWritten($nodeId);
        }
    }

    /**
     * Reap children that already exited, without blocking.
     *
     * @throws \RuntimeException if a child finished without writing its file.
     */
    private function reapFinished(): void
    {
        foreach ($this->pendingPids as $nodeId => $pid) {
            if (pcntl_waitpid($pid, $status, WNOHANG) !== 0) {
                unset($this->pendingPids[$nodeId]);
                $this->assertWritten($nodeId);
            }
        }
    }

    /**
     * Async children cannot report errors through their exit status (they end
     * with SIGKILL), so a missing file is the failure signal.
     *
     * @throws \RuntimeException
     */
    private function assertWritten(int $nodeId): void
    {
        clearstatcache(true, $this->filePath($nodeId));
        if (!is_file($this->filePath($nodeId))) {
            throw new \RuntimeException(sprintf(
                'Async write of document file failed: %s',
                $this->filePath($nodeId),
            ));
        }
    }

    // ------------------------------------------------------------------
    // Read
    // ------------------------------------------------------------------

    /**
     * Load a document file and return its contents.
     *
     * @return array{string|int, string|null, array<string, mixed>}  [docId, text, metadata]
     * @throws \RuntimeException if the file cannot be read or is corrupt.
     */
    public function read(int $nodeId): array
    {
        $path = $this->filePath($nodeId);
        $data = @file_get_contents($path);
        if ($data === false) {
            throw new \RuntimeException("Failed to read document file: {$path}");
        }

        $off = 0;

        // ── Document ID ──────────────────────────────────────────────────
        $idType = ord($data[$off]);
        $off   += 1;

        if ($idType === 0) {
            // int64
            [$id] = array_values(unpack('q', $data, $off));
            $off += 8;
        } else {
            // string
            [$idLen] = array_values(unpack('N', $data, $off));
            $off    += 4;
            $id      = substr($data, $off, $idLen);
            $off    += $idLen;
        }

        // ── Text ─────────────────────────────────────────────────────────
        [$textLen] = array_values(unpack('N', $data, $off));
        $off       += 4;
        if ($textLen > 0) {
            $text = substr($data, $off, $textLen);
            $off += $textLen;
        } else {
            $text = null;
        }

        // ── Metadata ─────────────────────────────────────────────────────
        [$metaLen] = array_values(unpack('N', $data, $off));
        $off       += 4;
        if ($metaLen > 0) {
            $metadata = json_decode(substr($data, $off, $metaLen), true, 512, JSON_THROW_ON_ERROR);
        } else {
            $metadata = [];
        }

        return [$id, $text, $metadata];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function filePath(int $nodeId): string
    {
        return $this->docsDir . '/' . $nodeId . '.bin';
    }

    /** @param array<string, mixed> $metadata */
    private function writeSync(int $nodeId, string|int $docId, ?string $text, array $metadata): void
    {
        $buf = '';

        // ── Document ID ──────────────────────────────────────────────────
        if (is_int($docId)) {
            $buf .= pack('Cq', 0, $docId);   // type=0 + int64
        } else {
            $idBytes = (string) $docId;
            $buf    .= pack('CN', 1, strlen($idBytes)) . $idBytes;
        }

        // ── Text ─────────────────────────────────────────────────────────
        if ($text === null || $text === '') {
            $buf .= pack('N', 0);
        } else {
            $buf .= pack('N', strlen($text)) . $text;
        }

        // ── Metadata ─────────────────────────────────────────────────────
        if (empty($metadata)) {
            $buf .= pack('N', 0);
        } else {
            $metaJson = json_encode($metadata, JSON_THROW_ON_ERROR);
            $buf     .= pack('N', strlen($metaJson)) . $metaJson;
        }

        // Atomic replace: a reader loading {nodeId}.bin lazily sees either the
        // previous version or the new one, never a partially written record.
        AtomicFile::write($this->filePath($nodeId), $buf);
    }
}
