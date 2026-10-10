<?php

namespace JDZ\Search\Tests;

use JDZ\Search\Isolator;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testSetContentStripsInlineMarkupAndAttributes(): void
    {
        $html = '<p class="lead" style="color:red">c' . "\u{0153}" . 'ur&nbsp;&nbsp;<span>de</span> <a href="/x">lion</a></p>  ';

        $this->assertSame('<p>c&oelig;ur de lion</p>', (new Isolator())->setContent($html)->getContent());
    }

    public function testEveryBlockElementIsAParagraph(): void
    {
        $html = '<h1>H1</h1><h2>H2</h2><h3>H3</h3><h4>H4</h4><h5>H5</h5><h6>H6</h6>'
            . '<ul><li>one</li><li>two</li></ul><ol><li>three</li></ol>'
            . '<div>four</div><blockquote>five</blockquote><figure>six<figcaption>seven</figcaption></figure>';

        $this->assertSame(
            '<p>H1 [...] H2 [...] H3 [...] H4 [...] H5 [...] H6 [...] one [...] two [...] three [...] four [...] five [...] six [...] seven</p>',
            $this->excerpt(['missing'], $html)
        );
    }

    public function testOnlyMatchingParagraphsAreKept(): void
    {
        $output = $this->excerpt(['care'], '<p>First care here.</p><p>Nothing.</p><ul><li>Second care there.</li></ul>');

        $this->assertSame('<p>First <strong>care</strong> &hellip; Second <strong>care</strong> &hellip;</p>', $output);
    }

    public function testEachTermCanMatchItsOwnParagraph(): void
    {
        $output = $this->excerpt(['foo', 'bar'], '<p>has foo</p><p>has bar</p><p>none</p>');

        $this->assertSame('<p>has <strong>foo</strong> has <strong>bar</strong></p>', $output);
    }

    public function testAdjacentEllipsesAreMerged(): void
    {
        $output = $this->excerpt(['x'], '<p>a b c d e f g h i j k l x m</p><p>a b c d e f g h i j k l x</p>', 3);

        $this->assertSame('<p>&hellip; j k l <strong>x</strong> &hellip; j k l <strong>x</strong></p>', $output);
    }

    public function testMatchIsCaseInsensitiveAndKeepsTheContentCase(): void
    {
        $output = $this->excerpt(['care'], '<p>Handled with CARE today.</p>');

        $this->assertSame('<p>Handled with <strong>CARE</strong> &hellip;</p>', $output);
    }

    public static function edgeProvider(): array
    {
        return [
            'term opens the paragraph' => ['<p>care is all</p>', '<p><strong>care</strong> &hellip;</p>'],
            'term closes the paragraph' => ['<p>all is care</p>', '<p>all is <strong>care</strong></p>'],
            'plain text without markup' => ['plain text with care', '<p>plain text with <strong>care</strong></p>'],
        ];
    }

    #[DataProvider('edgeProvider')]
    public function testEllipsisOnlyMarksCutText(string $html, string $expected): void
    {
        $this->assertSame($expected, $this->excerpt(['care'], $html));
    }

    public static function windowProvider(): array
    {
        return [
            'five words' => [5, '<p>&hellip; w8 w9 w10 w11 w12 <strong>target</strong> &hellip;</p>'],
            'ten words by default' => [null, '<p>&hellip; w3 w4 w5 w6 w7 w8 w9 w10 w11 w12 <strong>target</strong> &hellip;</p>'],
        ];
    }

    #[DataProvider('windowProvider')]
    public function testExcerptKeepsNumWordsAroundBeforeTheMatch(?int $numWordsAround, string $expected): void
    {
        $isolator = new Isolator();
        if (null !== $numWordsAround) {
            $isolator->setNumWordsAround($numWordsAround);
        }

        $output = $isolator
            ->setRegexFromSearchArray(['target'])
            ->setContent('<p>w1 w2 w3 w4 w5 w6 w7 w8 w9 w10 w11 w12 target end</p>')
            ->highlight()
            ->getContent();

        $this->assertSame($expected, $output);
    }
}
