<?php

namespace JDZ\Search\Tests;

use JDZ\Search\Isolator;
use PHPUnit\Framework\TestCase;

class IsolatorTest extends TestCase
{
    private function excerpt(array $terms, string $html, int $numWordsAround = 10): string
    {
        return (new Isolator())
            ->setNumWordsAround($numWordsAround)
            ->setRegexFromSearchArray($terms)
            ->setContent($html)
            ->highlight()
            ->getContent();
    }

    public function testRegexCharactersAreMatchedLiterally(): void
    {
        $output = $this->excerpt(['c++'], '<p>We teach c++ daily.</p>');

        $this->assertStringContainsString('<strong>c++</strong>', $output);
    }

    public function testDelimiterInTermIsEscaped(): void
    {
        $output = $this->excerpt(['a/b'], '<p>We run a/b tests.</p>');

        $this->assertStringContainsString('<strong>a/b</strong>', $output);
    }

    public function testUnbalancedParenthesisInTerm(): void
    {
        $output = $this->excerpt(['(with'], '<p>Handled (with care).</p>');

        $this->assertStringContainsString('<strong>(with</strong>', $output);
    }

    public function testEmptyTermsAreIgnored(): void
    {
        $output = $this->excerpt(['', 'care'], '<p>Handled with care.</p>');

        $this->assertStringContainsString('<strong>care</strong>', $output);
        $this->assertStringNotContainsString('<strong></strong>', $output);
    }

    public function testFirstWordOfParagraphIsKept(): void
    {
        $output = $this->excerpt(['c++'], '<p>We teach c++ daily.</p>');

        $this->assertStringStartsWith('<p>We teach <strong>c++</strong>', $output);
    }

    public function testExcerptStartsAtSentenceBoundary(): void
    {
        $html = '<p>one two three four five six seven eight nine ten. alpha beta gamma delta epsilon zeta eta theta target</p>';

        $output = $this->excerpt(['target'], $html);

        $this->assertStringContainsString('&hellip; alpha beta gamma delta epsilon zeta eta theta <strong>target</strong>', $output);
        $this->assertStringNotContainsString('ten.', $output);
    }

    public function testNoMatchReturnsParagraphs(): void
    {
        $output = $this->excerpt(['missing'], '<p>First.</p><p>Second.</p>');

        $this->assertSame('<p>First. [...] Second.</p>', $output);
    }
}
