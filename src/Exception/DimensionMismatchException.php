<?php

declare(strict_types=1);

namespace PHPVector\Exception;

final class DimensionMismatchException extends VectorDatabaseException
{
    public static function forVectors(int $expected, int $actual): self
    {
        return new self(
            sprintf('Vector dimension mismatch: expected %d, got %d.', $expected, $actual),
        );
    }
}
