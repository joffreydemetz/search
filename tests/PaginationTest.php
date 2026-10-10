<?php

namespace JDZ\Search\Tests;

use JDZ\Search\Pagination;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PaginationTest extends TestCase
{
    private static function page(int $page): array
    {
        return ['page' => $page, 'text' => (string) $page];
    }

    private static function current(int $page): array
    {
        return ['page' => $page, 'text' => (string) $page, 'current' => true];
    }

    private static function gap(): array
    {
        return ['span' => true];
    }

    private static function step(string $text, int $page, bool $disabled = false): array
    {
        return ['text' => $text, 'page' => $page] + ($disabled ? ['disabled' => true] : []);
    }

    /** Recursively turns the stdClass tree into arrays, keys and order kept. */
    private static function toArray(mixed $value): mixed
    {
        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        return is_array($value) ? array_map(self::toArray(...), $value) : $value;
    }

    public static function navProvider(): array
    {
        return [
            'two pages, on the first' => [1, 30, 31,
                [1 => self::current(1), 2 => self::page(2)],
                self::step('Previous', 0, true), self::step('Next', 2), 2,
            ],
            'two pages, on the last' => [2, 30, 31,
                [1 => self::page(1), 2 => self::current(2)],
                self::step('Previous', 1), self::step('Next', 3, true), 2,
            ],
            'six pages fit without ellipsis' => [6, 10, 60,
                [1 => self::page(1), 2 => self::page(2), 3 => self::page(3), 4 => self::page(4), 5 => self::page(5), 6 => self::current(6)],
                self::step('Previous', 5), self::step('Next', 7, true), 6,
            ],
            'seven pages, ellipsis after' => [1, 10, 70,
                [1 => self::current(1), 2 => self::page(2), 3 => self::page(3), 4 => self::page(4), 5 => self::page(5), 6 => self::page(6), 7 => self::gap()],
                self::step('Previous', 0, true), self::step('Next', 2), 7,
            ],
            'window clamped at the start' => [4, 10, 100,
                [1 => self::page(1), 2 => self::page(2), 3 => self::page(3), 4 => self::current(4), 5 => self::page(5), 6 => self::page(6), 7 => self::gap()],
                self::step('Previous', 3), self::step('Next', 5), 10,
            ],
            'window in the middle, ellipsis both sides' => [5, 10, 100,
                [1 => self::gap(), 2 => self::page(2), 3 => self::page(3), 4 => self::page(4), 5 => self::current(5), 6 => self::page(6), 7 => self::page(7), 8 => self::gap()],
                self::step('Previous', 4), self::step('Next', 6), 10,
            ],
            'window slides with the page' => [7, 10, 100,
                [3 => self::gap(), 4 => self::page(4), 5 => self::page(5), 6 => self::page(6), 7 => self::current(7), 8 => self::page(8), 9 => self::page(9), 10 => self::gap()],
                self::step('Previous', 6), self::step('Next', 8), 10,
            ],
            'window clamped at the end' => [8, 10, 100,
                [4 => self::gap(), 5 => self::page(5), 6 => self::page(6), 7 => self::page(7), 8 => self::current(8), 9 => self::page(9), 10 => self::page(10)],
                self::step('Previous', 7), self::step('Next', 9), 10,
            ],
            'last page of many' => [10, 10, 95,
                [4 => self::gap(), 5 => self::page(5), 6 => self::page(6), 7 => self::page(7), 8 => self::page(8), 9 => self::page(9), 10 => self::current(10)],
                self::step('Previous', 9), self::step('Next', 11, true), 10,
            ],
        ];
    }

    #[DataProvider('navProvider')]
    public function testNavigation(int $page, int $limit, int $total, array $list, array $previous, array $next, int $lastPage): void
    {
        $nav = (new Pagination())->setPage($page)->setLimit($limit)->setTotal($total)->getNav();

        $this->assertSame([
            'list' => $list,
            'start' => ['page' => 1, 'text' => 'Start'],
            'previous' => $previous,
            'next' => $next,
            'end' => ['page' => $lastPage, 'text' => 'End'],
        ], self::toArray($nav));
    }

    public static function noNavigationProvider(): array
    {
        return [
            'exactly one page' => [1, 30, 30],
            'fewer results than the limit' => [1, 30, 1],
            'no results' => [1, 30, 0],
            'zero limit' => [1, 0, 50],
            'negative limit' => [1, -10, 50],
        ];
    }

    #[DataProvider('noNavigationProvider')]
    public function testNoNavigation(int $page, int $limit, int $total): void
    {
        $this->assertFalse((new Pagination())->setPage($page)->setLimit($limit)->setTotal($total)->getNav());
    }

    public function testI18nOverridesKeepTheOtherLabels(): void
    {
        $nav = (new Pagination())
            ->setI18n(['PREVIOUS' => 'Précédent', 'NEXT' => 'Suivant'])
            ->setPage(1)
            ->setLimit(10)
            ->setTotal(20)
            ->getNav();

        $this->assertSame(
            ['Start', 'Précédent', 'Suivant', 'End'],
            [$nav->start->text, $nav->previous->text, $nav->next->text, $nav->end->text]
        );
    }
}
