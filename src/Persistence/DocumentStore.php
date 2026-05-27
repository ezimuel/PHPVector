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
 * Writes can be dispatched to forked child processes (pcntl_fork) to avoid
 * blocking the caller.  Call waitAll() before reading back or writing index files.
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
     * When $async is true and pcntl_fork() is available the write is
     * dispatched to a child process; the parent returns immediately.
     * When unavailable the write is synchronous.
     *
     * @param int             $nodeId
     * @param string|int      $docId    Must NOT be null (UUID already assigned by caller).
     * @param string|null     $text
     * @param array<string, mixed> $metadata
     * @param bool            $async
     */
    public function write(
        int $nodeId,
        string|int $docId,
        ?string $text,
        array $metadata,
        bool $async = true,
    ): void {
        if ($async && function_exists('pcntl_fork')) {
            $pid = pcntl_fork();

            if ($pid === -1) {
                // Fork failed — fall through to synchronous write.
            } elseif ($pid === 0) {
                // Child: write and exit immediately.
                $this->writeSync($nodeId, $docId, $text, $metadata);
                exit(0);
            } else {
                // Parent: record PID keyed by nodeId and return.
                $this->pendingPids[$nodeId] = $pid;
                return;
            }
        }

        // Synchronous path (no fork or fork failed).
        $this->writeSync($nodeId, $docId, $text, $metadata);
    }

    /**
     * Block until the async write for a specific node has completed.
     *
     * Use this before deleting a node's file so a late child write cannot
     * recreate {nodeId}.bin after the unlink().
     */
    public function waitForNode(int $nodeId): void
    {
        if (!isset($this->pendingPids[$nodeId])) {
            return;
        }

        if (function_exists('pcntl_waitpid')) {
            pcntl_waitpid($this->pendingPids[$nodeId], $status);
        }

        unset($this->pendingPids[$nodeId]);
    }

    /**
     * Block until every outstanding async write has completed.
     * Must be called before index files are written (see VectorDatabase::save()).
     */
    public function waitAll(): void
    {
        foreach ($this->pendingPids as $pid) {
            if (function_exists('pcntl_waitpid')) {
                pcntl_waitpid($pid, $status);
            }
        }
        $this->pendingPids = [];
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

        if (file_put_contents($this->filePath($nodeId), $buf) === false) {
            throw new \RuntimeException("Failed to write document file: {$this->filePath($nodeId)}");
        }
    }
}
