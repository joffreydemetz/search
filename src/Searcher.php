<?php

namespace JDZ\Search;

use JDZ\Database\Contract\DatabaseInterface;
use JDZ\Database\Query\SelectQuery;
use JDZ\Search\Contract\SearcherInterface;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
abstract class Searcher implements SearcherInterface
{
  protected DatabaseInterface $dbo;
  protected string $name = '';
  protected string $title = '';
  protected array $fields = [];
  protected string $term = '';
  protected string $type = '';

  private Isolator $isolator;

  public function __construct()
  {
    $this->isolator = new Isolator();
  }

  public function setDbo(DatabaseInterface $dbo): static
  {
    $this->dbo = $dbo;
    return $this;
  }

  public function setName(string $name): static
  {
    $this->name = $name;
    return $this;
  }

  public function setTitle(string $title): static
  {
    $this->title = $title;
    return $this;
  }

  public function setFields(array $fields = []): static
  {
    $this->fields = array_merge($this->fields, $fields);
    $this->fields = array_unique($this->fields);
    return $this;
  }

  public function setType(string $type): static
  {
    $this->type = $type;
    return $this;
  }

  public function setTerm(?string $term): static
  {
    $this->term = $term ?? '';
    return $this;
  }

  public function getName(): string
  {
    return $this->name;
  }

  public function getTitle(): string
  {
    return $this->title;
  }

  public function getFilterOption(): \stdClass
  {
    return (object)[
      'value' => $this->name,
      'text' => $this->title,
    ];
  }

  public function makeQuery(SelectQuery $query): void
  {
    $query
      ->select($this->dbo->quote($this->name) . ' AS component');

    if ('' !== trim($this->term)) {
      $or = [];
      if ('exact' === $this->type) {
        $this->exactSearch($or, $this->term);
      } elseif ('contains' === $this->type) {
        $this->containsSearch($or, $this->term);
      } else {
        $this->wordsSearch($or, $this->term);
      }

      $query->where('(' . implode(' OR ', $or) . ')');
    }
  }

  public function parseResults(array &$rows): void
  {
    foreach ($rows as $row) {
      if (!isset($row->group)) {
        $row->group = '';
      }

      if (!isset($row->url)) {
        $row->url = '';
      }

      if (!isset($row->hasher)) {
        $row->hasher = '';
      }

      if (!isset($row->json)) {
        $row->json = false;
      } else {
        $row->json = intval($row->json) === 1;
      }

      $row->content = $this->isolator
        ->setNumWordsAround(14)
        ->setRegexFromSearchArray($this->makeRegex())
        ->setContent($row->content)
        ->highlight()
        ->getContent();

      if (!$row->content) {
        $row->content = '';
        $row->invalid = true;
      }
    }
  }

  protected function exactSearch(array &$or, string $searched): void
  {
    foreach ($this->fields as $field) {
      $or[] = $field . ' LIKE ' . $this->dbo->quote('%' . $searched . '%');
    }
  }

  protected function containsSearch(array &$or, string $searched): void
  {
    $words = $this->words($searched);

    foreach ($this->fields as $field) {
      foreach ($words as $word) {
        $or[] = $field . ' LIKE ' . $this->dbo->quote('%' . $word . '%');
      }
    }
  }

  protected function wordsSearch(array &$or, string $searched): void
  {
    $words = $this->words($searched);

    foreach ($this->fields as $field) {
      $and = [];
      foreach ($words as $word) {
        $and[] = $field . ' LIKE ' . $this->dbo->quote('%' . $word . '%');
      }
      $or[] = '(' . implode(' AND ', $and) . ')';
    }
  }

  protected function makeRegex(): array
  {
    $arrayForRegex = [];

    if ('' !== trim($this->term)) {
      if ('exact' === $this->type) {
        $arrayForRegex[] = $this->term;
      } else {
        $arrayForRegex = $this->words($this->term);
      }
    }

    return $arrayForRegex;
  }

  /**
   * The words of a term, without the empty ones that double, leading or
   * trailing spaces leave (LIKE '%%' matches every row).
   */
  protected function words(string $searched): array
  {
    return array_values(array_filter(explode(' ', $searched), fn($word) => '' !== $word));
  }
}
