<?php

declare(strict_types=1);

namespace PHPVector\Metadata;

use PHPVector\Document;

/**
 * Evaluates whether a document's metadata matches a set of filters.
 *
 * Filter logic:
 * - Top-level filters are ANDed together
 * - Nested arrays (array of MetadataFilter) are ORed within the group
 * - Example: [[$f1, $f2], $f3] = (f1 OR f2) AND f3
 */
final class MetadataFilterEvaluator
{
    /**
     * Check if a document's metadata matches all filters.
     *
     * @param Document $document The document to check
     * @param array<MetadataFilter|array<MetadataFilter>> $filters Filters to apply
     * @return bool True if document matches all filters
     */
    public function matches(Document $document, array $filters): bool
    {
        foreach ($filters as $filter) {
            if (is_array($filter)) {
                // OR group: at least one must match
                if (!$this->matchesOrGroup($document, $filter)) {
                    return false;
                }
            } elseif (!$this->matchesSingleFilter($document, $filter)) {
                // Single filter
                return false;
            }
        }

        return true;
    }

    /**
     * Check if document matches at least one filter in an OR group.
     *
     * @param Document $document The document to check
     * @param array<MetadataFilter> $filters OR group of filters
     * @return bool True if at least one filter matches
     */
    private function matchesOrGroup(Document $document, array $filters): bool
    {
        foreach ($filters as $filter) {
            if ($this->matchesSingleFilter($document, $filter)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if document matches a single filter.
     *
     * @param Document $document The document to check
     * @param MetadataFilter $filter The filter to apply
     * @return bool True if a document matches the filter
     */
    private function matchesSingleFilter(Document $document, MetadataFilter $filter): bool
    {
        $metadata = $document->metadata;

        if ($filter->operator === 'exists') {
            return array_key_exists($filter->key, $metadata);
        }
        if ($filter->operator === 'not_exists') {
            return !array_key_exists($filter->key, $metadata);
        }

        // Missing metadata key returns false
        if (!array_key_exists($filter->key, $metadata)) {
            return false;
        }

        $metadataValue = $metadata[$filter->key];
        $filterValue = $filter->value;

        return match ($filter->operator) {
            '=' => $metadataValue === $filterValue,
            '!=' => $metadataValue !== $filterValue,
            '<' => $metadataValue < $filterValue,
            '<=' => $metadataValue <= $filterValue,
            '>' => $metadataValue > $filterValue,
            '>=' => $metadataValue >= $filterValue,
            'in' => is_array($filterValue) && in_array($metadataValue, $filterValue, true),
            'not_in' => is_array($filterValue) && !in_array($metadataValue, $filterValue, true),
            'contains' => $this->evaluateContains($metadataValue, $filterValue),
            default => false,
        };
    }

    private function evaluateContains(mixed $metadataValue, mixed $filterValue): bool
    {
        if (!is_array($metadataValue)) {
            return false;
        }

        return in_array($filterValue, $metadataValue, true);
    }
}
