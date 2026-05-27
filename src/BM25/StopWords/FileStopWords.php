<?php

declare(strict_types=1);

namespace PHPVector\BM25\StopWords;

/**
 * Load stop words from a file.
 *
 * This class demonstrates the value of the StopWordsProviderInterface:
 * stop words can come from any source, not just hardcoded arrays.
 *
 * Expected file format:
 * - One word per line
 * - Empty lines and lines starting with # are ignored
 * - Words are automatically lowercased
 *
 * Example file:
 * ```
 * # English stop words
 * the
 * a
 * an
 * is
 * are
 * ```
 */
final class FileStopWords implements StopWordsProviderInterface
{
    /** @var string[]|null Cached stop words (loaded once) */
    private ?array $words = null;

    /**
     * @param string $filePath Path to the stop words file.
     * @throws \InvalidArgumentException if the file does not exist.
     */
    public function __construct(
        private readonly string $filePath,
    ) {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException(
                sprintf('Stop words file not found: %s', $filePath),
            );
        }
    }

    public function getStopWords(): array
    {
        if ($this->words !== null) {
            return $this->words;
        }

        $this->words = $this->loadFromFile();
        return $this->words;
    }

    /**
     * @return string[]
     */
    private function loadFromFile(): array
    {
        $content = @file_get_contents($this->filePath);
        if ($content === false) {
            throw new \RuntimeException(
                sprintf('Failed to read stop words file: %s', $this->filePath),
            );
        }

        $lines = explode("\n", $content);
        $words = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip empty lines and comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $words[] = mb_strtolower($line, 'UTF-8');
        }

        return $words;
    }
}
