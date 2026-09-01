<?php

declare(strict_types=1);

namespace PHPVector\Persistence;

/**
 * Crash-safe file replacement.
 *
 * Data is written to a temporary file inside the destination directory and
 * then moved into place with rename(), which is atomic on POSIX filesystems
 * as long as source and destination sit on the same mount point.  A concurrent
 * reader therefore sees either the previous version of the file or the new one,
 * never a half-written mix.
 *
 * When the write fails the temporary file is removed and the destination is
 * left exactly as it was.
 */
final class AtomicFile
{
    /**
     * Atomically replace $path with $data.
     *
     * @throws \RuntimeException on I/O failure.
     */
    public static function write(string $path, string $data): void
    {
        self::writeStream($path, static function ($handle) use ($path, $data): void {
            if (@fwrite($handle, $data) === false) {
                throw new \RuntimeException("Failed to write file: {$path}");
            }
        });
    }

    /**
     * Atomically replace $path with whatever $writer streams into the handle it
     * receives.  The handle is opened in 'wb' mode and is closed by this method,
     * both on success and on failure.
     *
     * @param callable(resource): void $writer
     * @throws \RuntimeException on I/O failure.
     */
    public static function writeStream(string $path, callable $writer): void
    {
        $tmpPath = self::temporaryPath($path);

        $handle = @fopen($tmpPath, 'wb');
        if ($handle === false) {
            throw new \RuntimeException("Failed to open temporary file for writing: {$tmpPath}");
        }

        try {
            $writer($handle);
            if (@fflush($handle) === false) {
                throw new \RuntimeException("Failed to flush temporary file: {$tmpPath}");
            }
        } catch (\Throwable $e) {
            fclose($handle);
            @unlink($tmpPath);
            throw $e;
        }

        fclose($handle);

        if (!@rename($tmpPath, $path)) {
            @unlink($tmpPath);
            throw new \RuntimeException("Failed to move temporary file {$tmpPath} into place: {$path}");
        }
    }

    /**
     * Build a temporary sibling of $path: same directory (so rename() stays on
     * the same filesystem), dot-prefixed so directory globs ignore it, and
     * suffixed with random bytes so concurrent writers never collide.
     */
    private static function temporaryPath(string $path): string
    {
        return dirname($path) . '/.' . basename($path) . '.' . bin2hex(random_bytes(6)) . '.tmp';
    }
}
