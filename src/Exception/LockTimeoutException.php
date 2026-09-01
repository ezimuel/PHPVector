<?php

declare(strict_types=1);

namespace PHPVector\Exception;

final class LockTimeoutException extends VectorDatabaseException
{
    public static function forPath(string $path, float $timeout): self
    {
        return new self(
            sprintf(
                'Timed out after %.3f seconds while waiting for the lock on "%s". '
                . 'Another process is probably reading or writing the index.',
                $timeout,
                $path,
            ),
        );
    }
}
