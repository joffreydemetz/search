<?php

namespace JDZ\Search\Tests;

use JDZ\Database\Contract\DatabaseInterface;
use JDZ\Database\Contract\QueryInterface;
use JDZ\Database\Exception\DatabaseException;
use JDZ\Database\ParamType;
use JDZ\Search\Repository\SearchRepository;
use PHPUnit\Framework\TestCase;

class SearchRepositoryTest extends TestCase
{
    /** @var QueryInterface[] every query handed to setQuery() */
    private array $queries = [];

    /** @var QueryInterface[] the current query at each execute() */
    private array $executed = [];

    private function capturingDatabase(mixed $existing = null, ?\Exception $lookupFailure = null, ?\Exception $executeFailure = null): DatabaseInterface
    {
        $this->queries = [];
        $this->executed = [];

        $database = $this->createStub(DatabaseInterface::class);
        $database->method('setQuery')->willReturnCallback(function (QueryInterface|string $query) {
            $this->queries[] = $query;
            return $query;
        });
        if ($lookupFailure) {
            $database->method('loadResult')->willThrowException($lookupFailure);
        } else {
            $database->method('loadResult')->willReturn($existing);
        }
        $database->method('execute')->willReturnCallback(function () use ($executeFailure) {
            if ($executeFailure) {
                throw $executeFailure;
            }
            $this->executed[] = end($this->queries);
            return true;
        });

        return $database;
    }

    private function save(DatabaseInterface $database): bool
    {
        return (new SearchRepository())
            ->setDbo($database)
            ->setComponent('pages')
            ->setTerm('foo')
            ->save();
    }

    private static function bindings(QueryInterface $query): array
    {
        return array_map(get_object_vars(...), $query->getBounded());
    }

    private static function expectedBindings(): array
    {
        $bind = fn(string $param, string $value) => [
            'param' => $param,
            'value' => $value,
            'dataType' => ParamType::STR->value,
            'maxLength' => 0,
            'driverOptions' => null,
        ];

        return [
            ':component' => $bind(':component', 'pages'),
            ':term' => $bind(':term', 'foo'),
        ];
    }

    private function assertLookup(QueryInterface $query): void
    {
        $this->assertSame(
            'SELECT component' . PHP_EOL
            . 'FROM #__search' . PHP_EOL
            . 'WHERE component = :component AND term = :term',
            (string) $query
        );
        $this->assertSame(self::expectedBindings(), self::bindings($query));
    }

    private function assertInsert(QueryInterface $query): void
    {
        $this->assertSame(
            'INSERT INTO #__search' . PHP_EOL
            . '(component, term)' . PHP_EOL
            . 'VALUES (:component, :term)',
            (string) $query
        );
        $this->assertSame(self::expectedBindings(), self::bindings($query));
    }

    public function testKnownTermIncrementsItsHits(): void
    {
        $this->assertTrue($this->save($this->capturingDatabase('pages')));

        $this->assertCount(2, $this->queries);
        $this->assertLookup($this->queries[0]);
        $this->assertSame(
            'UPDATE #__search' . PHP_EOL
            . 'SET hits = hits+1' . PHP_EOL
            . 'WHERE component = :component AND term = :term',
            (string) $this->queries[1]
        );
        $this->assertSame(self::expectedBindings(), self::bindings($this->queries[1]));
        $this->assertSame([$this->queries[1]], $this->executed);
    }

    public function testNewTermIsInserted(): void
    {
        $this->assertTrue($this->save($this->capturingDatabase(null)));

        $this->assertCount(2, $this->queries);
        $this->assertLookup($this->queries[0]);
        $this->assertInsert($this->queries[1]);
        $this->assertSame([$this->queries[1]], $this->executed);
    }

    public function testFailedLookupFallsBackToInsert(): void
    {
        $this->assertTrue($this->save($this->capturingDatabase(lookupFailure: new DatabaseException('table missing'))));

        $this->assertCount(2, $this->queries);
        $this->assertLookup($this->queries[0]);
        $this->assertInsert($this->queries[1]);
        $this->assertSame([$this->queries[1]], $this->executed);
    }

    public function testFailedInsertIsSwallowedAndReportsFalse(): void
    {
        $this->assertFalse($this->save($this->capturingDatabase(null, executeFailure: new DatabaseException('duplicate'))));

        $this->assertCount(2, $this->queries);
        $this->assertInsert($this->queries[1]);
    }
}
