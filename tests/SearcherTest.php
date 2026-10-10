<?php

namespace JDZ\Search\Tests;

use JDZ\Database\Contract\DatabaseInterface;
use JDZ\Database\Query\SelectQuery;
use JDZ\Search\Searcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SearcherTest extends TestCase
{
    private function searcher(array $fields = ['title']): Searcher
    {
        $dbo = $this->createStub(DatabaseInterface::class);
        $dbo->method('quote')->willReturnCallback(fn(string $text) => "'" . addslashes($text) . "'");

        return (new class extends Searcher {
            public function regex(): array
            {
                return $this->makeRegex();
            }
        })
            ->setDbo($dbo)
            ->setName('pages')
            ->setFields($fields);
    }

    public function testNullTermIsAnEmptySearch(): void
    {
        $query = new SelectQuery();

        $this->searcher()->setTerm(null)->makeQuery($query);

        $sql = $query->toString();
        $this->assertStringContainsString("'pages' AS component", $sql);
        $this->assertStringNotContainsString('LIKE', $sql);
    }

    public function testTermWithoutTypeSearchesEveryWord(): void
    {
        $query = new SelectQuery();

        $this->searcher()->setTerm('foo bar')->makeQuery($query);

        $sql = $query->toString();
        $this->assertStringContainsString("title LIKE '%foo%' AND title LIKE '%bar%'", $sql);
    }

    public static function strategyProvider(): array
    {
        return [
            'exact matches the whole phrase in any field' => [
                'exact',
                'foo bar',
                "(title LIKE '%foo bar%' OR body LIKE '%foo bar%')",
            ],
            'exact quotes the term through the database' => [
                'exact',
                "l'eau",
                "(title LIKE '%l\\'eau%' OR body LIKE '%l\\'eau%')",
            ],
            'contains matches any word in any field' => [
                'contains',
                'foo bar',
                "(title LIKE '%foo%' OR title LIKE '%bar%' OR body LIKE '%foo%' OR body LIKE '%bar%')",
            ],
            'words matches every word within one field' => [
                '',
                'foo bar',
                "((title LIKE '%foo%' AND title LIKE '%bar%') OR (body LIKE '%foo%' AND body LIKE '%bar%'))",
            ],
            'unknown type falls back to words' => [
                'fuzzy',
                'foo bar',
                "((title LIKE '%foo%' AND title LIKE '%bar%') OR (body LIKE '%foo%' AND body LIKE '%bar%'))",
            ],
        ];
    }

    #[DataProvider('strategyProvider')]
    public function testSearchStrategyBuildsTheWhereClause(string $type, string $term, string $where): void
    {
        $query = new SelectQuery();

        $this->searcher(['title', 'body'])->setType($type)->setTerm($term)->makeQuery($query);

        $this->assertSame("SELECT 'pages' AS component" . PHP_EOL . 'WHERE ' . $where, (string) $query);
        $this->assertSame([], $query->getBounded());
    }

    public function testSetFieldsMergesWithoutDuplicates(): void
    {
        $query = new SelectQuery();

        $this->searcher()
            ->setFields(['body', 'title'])
            ->setFields(['summary', 'body'])
            ->setFields()
            ->setType('exact')
            ->setTerm('foo')
            ->makeQuery($query);

        $this->assertSame(
            "SELECT 'pages' AS component" . PHP_EOL
            . "WHERE (title LIKE '%foo%' OR body LIKE '%foo%' OR summary LIKE '%foo%')",
            (string) $query
        );
    }

    public function testFilterOptionUsesNameAsValueAndTitleAsText(): void
    {
        $option = $this->searcher()->setTitle('Pages')->getFilterOption();

        $this->assertSame(['value' => 'pages', 'text' => 'Pages'], (array) $option);
    }

    public static function regexProvider(): array
    {
        return [
            'exact keeps the phrase' => ['exact', 'with care', ['with care']],
            'contains splits on spaces' => ['contains', 'with care', ['with', 'care']],
            'words splits on spaces' => ['', 'with care', ['with', 'care']],
            'no term, no pattern' => ['exact', '', []],
        ];
    }

    #[DataProvider('regexProvider')]
    public function testMakeRegex(string $type, string $term, array $expected): void
    {
        $this->assertSame($expected, $this->searcher()->setType($type)->setTerm($term)->regex());
    }

    public static function rowProvider(): array
    {
        return [
            'missing columns get defaults' => [
                ['content' => '<p>No match.</p>'],
                ['content' => '<p>No match.</p>', 'group' => '', 'url' => '', 'hasher' => '', 'json' => false],
            ],
            'set columns are kept, json 1 is true' => [
                ['content' => '<p>x</p>', 'group' => 'Blog', 'url' => '/blog/x', 'hasher' => 'abc', 'json' => '1'],
                ['content' => '<p>x</p>', 'group' => 'Blog', 'url' => '/blog/x', 'hasher' => 'abc', 'json' => true],
            ],
            'json 0 is false' => [
                ['content' => '<p>x</p>', 'json' => '0'],
                ['content' => '<p>x</p>', 'json' => false, 'group' => '', 'url' => '', 'hasher' => ''],
            ],
            'json other than 1 is false' => [
                ['content' => '<p>x</p>', 'json' => 2],
                ['content' => '<p>x</p>', 'json' => false, 'group' => '', 'url' => '', 'hasher' => ''],
            ],
            'null columns count as missing' => [
                ['content' => '<p>x</p>', 'group' => null, 'url' => null, 'hasher' => null, 'json' => null],
                ['content' => '<p>x</p>', 'group' => '', 'url' => '', 'hasher' => '', 'json' => false],
            ],
        ];
    }

    #[DataProvider('rowProvider')]
    public function testParseResultsNormalisesRows(array $row, array $expected): void
    {
        $rows = [(object) $row];

        $this->searcher()->setTerm('zz')->parseResults($rows);

        $this->assertSame($expected, (array) $rows[0]);
    }

    public function testParseResultsExcerptsEachRowAroundTheTerm(): void
    {
        $rows = [
            (object) ['content' => '<p>w1 w2 w3 w4 w5 w6 w7 w8 w9 w10 w11 w12 w13 w14 w15 w16 with care, always.</p>'],
            (object) ['content' => '<p>Alpha.</p><p>Beta.</p>'],
        ];

        $this->searcher()->setType('exact')->setTerm('with care')->parseResults($rows);

        $this->assertSame(
            '<p>&hellip; w3 w4 w5 w6 w7 w8 w9 w10 w11 w12 w13 w14 w15 w16 <strong>with care</strong> &hellip;</p>',
            $rows[0]->content
        );
        $this->assertSame('<p>Alpha. [...] Beta.</p>', $rows[1]->content);
    }

    public function testTheTermZeroIsSearched(): void
    {
        $query = new SelectQuery();
        $searcher = $this->searcher()->setType('exact')->setTerm('0');

        $searcher->makeQuery($query);

        $this->assertSame("SELECT 'pages' AS component" . PHP_EOL . "WHERE (title LIKE '%0%')", (string) $query);
        $this->assertSame(['0'], $searcher->regex());
    }

    public static function spacingProvider(): array
    {
        return [
            'contains, trailing space' => ['contains', 'foo ', "(title LIKE '%foo%')", ['foo']],
            'contains, double space' => ['contains', 'foo  bar', "(title LIKE '%foo%' OR title LIKE '%bar%')", ['foo', 'bar']],
            'words, leading space' => ['', ' foo bar', "((title LIKE '%foo%' AND title LIKE '%bar%'))", ['foo', 'bar']],
        ];
    }

    /**
     * An empty word used to become LIKE '%%', which in contains mode (OR) matches
     * every row.
     */
    #[DataProvider('spacingProvider')]
    public function testEmptyWordsAreSkipped(string $type, string $term, string $where, array $regex): void
    {
        $query = new SelectQuery();
        $searcher = $this->searcher()->setType($type)->setTerm($term);

        $searcher->makeQuery($query);

        $this->assertSame("SELECT 'pages' AS component" . PHP_EOL . 'WHERE ' . $where, (string) $query);
        $this->assertSame($regex, $searcher->regex());
    }

    public static function blankTermProvider(): array
    {
        return [
            'exact' => ['exact'],
            'contains' => ['contains'],
            'words' => [''],
        ];
    }

    #[DataProvider('blankTermProvider')]
    public function testAWhitespaceTermIsAnEmptySearch(string $type): void
    {
        $query = new SelectQuery();
        $searcher = $this->searcher()->setType($type)->setTerm('   ');

        $searcher->makeQuery($query);

        $this->assertSame("SELECT 'pages' AS component", (string) $query);
        $this->assertSame([], $searcher->regex());
    }
}
