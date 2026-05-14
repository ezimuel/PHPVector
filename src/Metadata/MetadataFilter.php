<?php

declare(strict_types=1);

namespace PHPVector\Metadata;

use InvalidArgumentException;

final class MetadataFilter
{
    private const VALID_OPERATORS = ['=', '!=', '<', '<=', '>', '>=', 'in', 'not_in', 'contains', 'exists', 'not_exists'];

    /**
     * @param string $key      Metadata field name to filter on.
     * @param mixed  $value    Value to compare against.
     * @param string $operator Comparison operator.
     */
    public function __construct(
        public readonly string $key,
        public readonly mixed $value,
        public readonly string $operator = '=',
    ) {
        if (!in_array($operator, self::VALID_OPERATORS, true)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unknown operator "%s". Valid operators are: %s',
                    $operator,
                    implode(', ', self::VALID_OPERATORS)
                )
            );
        }

        if (in_array($operator, ['in', 'not_in'], true) && !is_array($value)) {
            throw new InvalidArgumentException(
                sprintf('Operator "%s" requires an array value.', $operator)
            );
        }

        if ($operator === 'contains' && is_array($value)) {
            throw new InvalidArgumentException(
                'Operator "contains" requires a single value, not an array.'
            );
        }
    }

    public static function eq(string $key, mixed $value): self
    {
        return new self($key, $value, '=');
    }

    public static function neq(string $key, mixed $value): self
    {
        return new self($key, $value, '!=');
    }

    public static function lt(string $key, mixed $value): self
    {
        return new self($key, $value, '<');
    }

    public static function lte(string $key, mixed $value): self
    {
        return new self($key, $value, '<=');
    }

    public static function gt(string $key, mixed $value): self
    {
        return new self($key, $value, '>');
    }

    public static function gte(string $key, mixed $value): self
    {
        return new self($key, $value, '>=');
    }

    public static function in(string $key, array $values): self
    {
        return new self($key, $values, 'in');
    }

    public static function notIn(string $key, array $values): self
    {
        return new self($key, $values, 'not_in');
    }

    public static function contains(string $key, mixed $value): self
    {
        return new self($key, $value, 'contains');
    }

    public static function exists(string $key): self
    {
        return new self($key, true, 'exists');
    }

    public static function notExists(string $key): self
    {
        return new self($key, true, 'not_exists');
    }
}
