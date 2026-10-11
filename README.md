# JDZ Search

Site search library with text isolation and highlighting.

## Installation

```bash
composer require jdz/search
```

## Requirements

- PHP 8.2 or higher
- jdz/database ^2.1 (queries are built with its `SelectQuery` and run through its `DatabaseInterface`)

## Components

Namespace `JDZ\Search\`.

- **Searcher** — Abstract base class for building component-specific searchers
- **Contract\SearcherInterface** — Contract for searcher implementations
- **Repository\SearchRepository** — Logs search terms and tracks hit counts
- **Isolator** — Extracts and highlights matching text from HTML content
- **Pagination** — Calculates page navigation for search results

## Usage

Create a concrete searcher by extending `JDZ\Search\Searcher`:

```php
use JDZ\Search\Searcher;
use JDZ\Database\Query\SelectQuery;

class BlogSearcher extends Searcher
{
    protected string $name = 'blog';
    protected array $fields = ['blog.title', 'blog.content'];

    public function makeQuery(SelectQuery $query): void
    {
        $query
            ->select('DISTINCT(blog.id), blog.title, blog.content')
            ->from('#__blog AS blog')
            ->where('blog.published IS NOT NULL');

        parent::makeQuery($query);
    }
}
```

Run it:

```php
$searcher = (new BlogSearcher())
    ->setDbo($dbo)          // a JDZ\Database\Contract\DatabaseInterface
    ->setTitle('Blog')
    ->setType('words')      // 'exact' | 'contains' | anything else = every word in the same field
    ->setTerm('slim twig');

$query = new SelectQuery();
$searcher->makeQuery($query);   // adds "'blog' AS component" and the LIKE conditions on $fields

$dbo->setQuery($query);
$rows = $dbo->loadObjectList();
$searcher->parseResults($rows); // $row->content becomes a highlighted excerpt
```

`parseResults()` also defaults `group`, `url`, `hasher` and `json` on every row, and flags `invalid` when no excerpt is left. `getFilterOption()` returns `{value: name, text: title}` for a component filter.

### Isolator

```php
$excerpt = (new JDZ\Search\Isolator())
    ->setNumWordsAround(14)
    ->setRegexFromSearchArray(['slim', 'twig'])
    ->setContent($html)
    ->highlight()
    ->getContent();   // '<p>&hellip; words around <strong>slim</strong> &hellip;</p>'
```

### Pagination

`(new JDZ\Search\Pagination())->setPage(2)->setLimit(30)->setTotal(240)->getNav()` returns an object with `list` (page links, `current` on the active one, `span` for gaps) and `start` / `previous` / `next` / `end` links (`disabled` at the bounds), or `false` when there is a single page. Labels come from `setI18n(['PREVIOUS' => '…', …])`.

### SearchRepository

`(new JDZ\Search\Repository\SearchRepository())->setDbo($dbo)->setComponent('blog')->setTerm('slim twig')->save()` logs the term in `#__search`, incrementing `hits` when it is already there. The table definition is in `data/sql/search.mysql.sql`.

## Changelog

- **1.0.4** (2026-10-11) — the LIKE wildcards of a term are escaped (`50%` matched "50 euros" and "5000"; `ESCAPE` clause added when one was escaped); a search without a field throws `SearchException` (it built `WHERE ()`); `Pagination` clamps a page out of range; the excerpt highlights every match in a window of words around the first one and keeps the words after it (a greedy pattern kept the last match only and always cut what followed); a row is `invalid` when no usable term remains (the flag was unreachable); a curly apostrophe in the content matches the straight one of the term (the replace was a no-op). `Isolator::hasTerms()`.

## License

MIT
