# JDZ Search

Site search library with text isolation and highlighting.

## Installation

```bash
composer require jdz/search
```

## Components

- **Searcher** — Abstract base class for building component-specific searchers
- **SearcherInterface** — Contract for searcher implementations
- **SearchRepository** — Logs search terms and tracks hit counts
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

## License

MIT
