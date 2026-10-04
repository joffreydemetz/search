<?php

namespace JDZ\Search\Tests;

use JDZ\Database\Contract\DatabaseInterface;
use JDZ\Database\Query\SelectQuery;
use JDZ\Search\Searcher;
use PHPUnit\Framework\TestCase;

class SearcherTest extends TestCase
{
    private function searcher(): Searcher
    {
        $dbo = $this->createMock(DatabaseInterface::class);
        $dbo->method('quote')->willReturnCallback(fn(string $text) => "'" . $text . "'");

        return (new class extends Searcher {})
            ->setDbo($dbo)
            ->setName('pages')
            ->setFields(['title']);
    }

    public function testNullTermIsAnEmptySearch(): void
    {
        $query = new SelectQuery();

        $this->searcher()->setTerm(null)->makeQuery($query);

        $sql = $query->toString();
        $this->assertStringContainsString("'pages' AS component", $sql);
        $this->assertStringNotContainsString('LIKE', $sql);
    }

    public function testUnsetTermAndTypeAreAnEmptySearch(): void
    {
        $query = new SelectQuery();

        $this->searcher()->makeQuery($query);

        $this->assertStringNotContainsString('LIKE', $query->toString());
    }

    public function testTermWithoutTypeSearchesEveryWord(): void
    {
        $query = new SelectQuery();

        $this->searcher()->setTerm('foo bar')->makeQuery($query);

        $sql = $query->toString();
        $this->assertStringContainsString("title LIKE '%foo%' AND title LIKE '%bar%'", $sql);
    }
}
