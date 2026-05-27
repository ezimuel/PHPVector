<?php

declare(strict_types=1);

namespace PHPVector\Tests\BM25;

use PHPUnit\Framework\TestCase;
use PHPVector\BM25\SimpleTokenizer;
use PHPVector\BM25\StopWords\EnglishStopWords;
use PHPVector\BM25\StopWords\FileStopWords;
use PHPVector\BM25\StopWords\ItalianStopWords;
use PHPVector\BM25\StopWords\StopWordsProviderInterface;

final class StopWordsTest extends TestCase
{
    // ------------------------------------------------------------------
    // EnglishStopWords
    // ------------------------------------------------------------------

    public function testEnglishStopWordsImplementsInterface(): void
    {
        $provider = new EnglishStopWords();
        self::assertInstanceOf(StopWordsProviderInterface::class, $provider);
    }

    public function testEnglishStopWordsReturnsNonEmptyArray(): void
    {
        $words = (new EnglishStopWords())->getStopWords();
        self::assertNotEmpty($words);
    }

    public function testEnglishStopWordsContainsCommonWords(): void
    {
        $words = (new EnglishStopWords())->getStopWords();

        // Articles
        self::assertContains('the', $words);
        self::assertContains('an', $words);

        // Prepositions
        self::assertContains('of', $words);
        self::assertContains('in', $words);
        self::assertContains('to', $words);

        // Pronouns
        self::assertContains('i', $words);
        self::assertContains('you', $words);

        // Auxiliary verbs
        self::assertContains('is', $words);
        self::assertContains('have', $words);
    }

    public function testEnglishStopWordsStaticAccessMatchesInstance(): void
    {
        $instance = (new EnglishStopWords())->getStopWords();
        $static   = EnglishStopWords::words();

        self::assertSame($instance, $static);
    }

    // ------------------------------------------------------------------
    // ItalianStopWords
    // ------------------------------------------------------------------

    public function testItalianStopWordsImplementsInterface(): void
    {
        $provider = new ItalianStopWords();
        self::assertInstanceOf(StopWordsProviderInterface::class, $provider);
    }

    public function testItalianStopWordsReturnsNonEmptyArray(): void
    {
        $words = (new ItalianStopWords())->getStopWords();
        self::assertNotEmpty($words);
    }

    public function testItalianStopWordsContainsCommonWords(): void
    {
        $words = (new ItalianStopWords())->getStopWords();

        // Articoli
        self::assertContains('il', $words);
        self::assertContains('un', $words);

        // Preposizioni
        self::assertContains('di', $words);
        self::assertContains('a', $words);

        // Pronomi
        self::assertContains('io', $words);
        self::assertContains('tu', $words);

        // Verbi ausiliari
        self::assertContains('sono', $words);
        self::assertContains('ho', $words);
    }

    public function testItalianStopWordsStaticAccessMatchesInstance(): void
    {
        $instance = (new ItalianStopWords())->getStopWords();
        $static   = ItalianStopWords::words();

        self::assertSame($instance, $static);
    }

    // ------------------------------------------------------------------
    // FileStopWords
    // ------------------------------------------------------------------

    public function testFileStopWordsImplementsInterface(): void
    {
        $file     = $this->createTempStopWordsFile(['test', 'word']);
        $provider = new FileStopWords($file);
        self::assertInstanceOf(StopWordsProviderInterface::class, $provider);
        unlink($file);
    }

    public function testFileStopWordsThrowsOnMissingFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Stop words file not found');
        new FileStopWords('/nonexistent/path/stopwords.txt');
    }

    public function testFileStopWordsThrowsOnUnreadableFile(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('chmod test not supported on Windows');
        }

        $file = $this->createTempStopWordsFile(['test']);
        chmod($file, 0000); // Remove all permissions

        $provider  = new FileStopWords($file);
        $exception = null;

        try {
            $provider->getStopWords();
        } catch (\RuntimeException $e) {
            $exception = $e;
        } finally {
            chmod($file, 0644); // Restore permissions for cleanup
            unlink($file);
        }

        self::assertNotNull($exception, 'Expected RuntimeException to be thrown');
        self::assertStringContainsString('Failed to read stop words file', $exception->getMessage());
    }

    public function testFileStopWordsLoadsWordsFromFile(): void
    {
        $file     = $this->createTempStopWordsFile(['hello', 'world', 'test']);
        $provider = new FileStopWords($file);
        $words    = $provider->getStopWords();

        self::assertContains('hello', $words);
        self::assertContains('world', $words);
        self::assertContains('test', $words);
        self::assertCount(3, $words);

        unlink($file);
    }

    public function testFileStopWordsIgnoresEmptyLinesAndComments(): void
    {
        $content = <<<'EOF'
# This is a comment
hello

world
# Another comment

test
EOF;
        $file     = $this->createTempFile($content);
        $provider = new FileStopWords($file);
        $words    = $provider->getStopWords();

        self::assertCount(3, $words);
        self::assertContains('hello', $words);
        self::assertContains('world', $words);
        self::assertContains('test', $words);

        unlink($file);
    }

    public function testFileStopWordsLowercasesWords(): void
    {
        $file     = $this->createTempStopWordsFile(['HELLO', 'World', 'TEST']);
        $provider = new FileStopWords($file);
        $words    = $provider->getStopWords();

        self::assertContains('hello', $words);
        self::assertContains('world', $words);
        self::assertContains('test', $words);
        self::assertNotContains('HELLO', $words);
        self::assertNotContains('World', $words);

        unlink($file);
    }

    public function testFileStopWordsCachesResults(): void
    {
        $file     = $this->createTempStopWordsFile(['word']);
        $provider = new FileStopWords($file);

        $first  = $provider->getStopWords();
        $second = $provider->getStopWords();

        self::assertSame($first, $second);

        unlink($file);
    }

    // ------------------------------------------------------------------
    // SimpleTokenizer with StopWordsProviderInterface
    // ------------------------------------------------------------------

    public function testSimpleTokenizerWithEnglishStopWords(): void
    {
        $tokenizer = new SimpleTokenizer(new EnglishStopWords());
        $tokens    = $tokenizer->tokenize('A curious cat quietly watches the birds in the garden');

        // Stop words should be removed
        self::assertNotContains('a', $tokens);
        self::assertNotContains('the', $tokens);
        self::assertNotContains('in', $tokens);

        // Content words should remain
        self::assertContains('curious', $tokens);
        self::assertContains('cat', $tokens);
        self::assertContains('quietly', $tokens);
        self::assertContains('watches', $tokens);
        self::assertContains('birds', $tokens);
        self::assertContains('garden', $tokens);
    }

    public function testSimpleTokenizerWithItalianStopWords(): void
    {
        $tokenizer = new SimpleTokenizer(new ItalianStopWords());
        $tokens    = $tokenizer->tokenize('Il piccolo gatto osserva un corvo insolitamente silenzioso nel giardino');

        // Stop words
        self::assertNotContains('il', $tokens);
        self::assertNotContains('un', $tokens);
        self::assertNotContains('nel', $tokens);

        // Content words
        self::assertContains('piccolo', $tokens);
        self::assertContains('gatto', $tokens);
        self::assertContains('osserva', $tokens);
        self::assertContains('corvo', $tokens);
        self::assertContains('insolitamente', $tokens);
        self::assertContains('silenzioso', $tokens);
        self::assertContains('giardino', $tokens);
    }

    public function testSimpleTokenizerWithFileStopWords(): void
    {
        $file      = $this->createTempStopWordsFile(['custom', 'stop', 'words']);
        $tokenizer = new SimpleTokenizer(new FileStopWords($file));
        $tokens    = $tokenizer->tokenize('These are custom stop words for testing');

        self::assertNotContains('custom', $tokens);
        self::assertNotContains('stop', $tokens);
        self::assertNotContains('words', $tokens);
        self::assertContains('these', $tokens);
        self::assertContains('are', $tokens);
        self::assertContains('for', $tokens);
        self::assertContains('testing', $tokens);

        unlink($file);
    }

    public function testSimpleTokenizerDefaultsToEnglishStopWords(): void
    {
        $tokenizer = new SimpleTokenizer();
        $tokens    = $tokenizer->tokenize('The sky is blue and the grass is green');

        // English stop words should be removed by default
        self::assertNotContains('the', $tokens);
        self::assertNotContains('is', $tokens);
        self::assertNotContains('and', $tokens);

        // Content words should remain
        self::assertContains('sky', $tokens);
        self::assertContains('blue', $tokens);
        self::assertContains('grass', $tokens);
        self::assertContains('green', $tokens);
    }

    public function testSimpleTokenizerAcceptsBothArrayAndProvider(): void
    {
        // Array syntax (backward compatible)
        $tokenizer1 = new SimpleTokenizer(['foo', 'bar']);
        $tokens1    = $tokenizer1->tokenize('foo bar baz');
        self::assertNotContains('foo', $tokens1);
        self::assertNotContains('bar', $tokens1);
        self::assertContains('baz', $tokens1);

        // Provider syntax
        $tokenizer2 = new SimpleTokenizer(new EnglishStopWords());
        $tokens2    = $tokenizer2->tokenize('the dirty fox');
        self::assertNotContains('the', $tokens2);
        self::assertContains('dirty', $tokens2);
        self::assertContains('fox', $tokens2);
    }

    public function testSimpleTokenizerHandlesUnicodeWithItalianStopWords(): void
    {
        $tokenizer = new SimpleTokenizer(new ItalianStopWords());
        $tokens    = $tokenizer->tokenize('Perché è così difficile?');

        // Italian stop words with accents should be removed
        self::assertNotContains('perché', $tokens);
        self::assertNotContains('è', $tokens);
        self::assertNotContains('così', $tokens);

        // Content word should remain
        self::assertContains('difficile', $tokens);
    }

    public function testSimpleTokenizerEmptyStopWordsProvider(): void
    {
        $file      = $this->createTempFile('');
        $tokenizer = new SimpleTokenizer(new FileStopWords($file));
        $tokens    = $tokenizer->tokenize('the quick brown fox');

        // With no stop words, all words should remain (except those < minTokenLength)
        self::assertContains('the', $tokens);
        self::assertContains('quick', $tokens);
        self::assertContains('brown', $tokens);
        self::assertContains('fox', $tokens);

        unlink($file);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** @param string[] $words */
    private function createTempStopWordsFile(array $words): string
    {
        return $this->createTempFile(implode("\n", $words));
    }

    private function createTempFile(string $content): string
    {
        $file = tempnam(sys_get_temp_dir(), 'stopwords_test_');
        file_put_contents($file, $content);
        return $file;
    }
}
