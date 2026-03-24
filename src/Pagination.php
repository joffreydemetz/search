<?php

namespace JDZ\Search;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class Pagination
{
  const MAX_PAGES_DISPLAYED = 6;

  protected array $i18n = [
    'ALL' => 'All',
    'VIEW_ALL' => 'View all',
    'START' => 'Start',
    'END' => 'End',
    'PREVIOUS' => 'Previous',
    'NEXT' => 'Next',
  ];

  protected int $page = 1;
  protected int $limit = 30;
  protected int $total = 0;

  public function setPage(int $page): static
  {
    $this->page = $page;
    return $this;
  }

  public function setLimit(int $limit): static
  {
    $this->limit = $limit;
    return $this;
  }

  public function setTotal(int $total): static
  {
    $this->total = $total;
    return $this;
  }

  public function setI18n(array $i18n): static
  {
    $this->i18n = array_merge($this->i18n, $i18n);
    return $this;
  }

  public function getNav(): \stdClass|false
  {
    $nbPages = $this->limit ? max(0, (int)ceil($this->total / $this->limit)) : 0;

    $firstPageDisplayed = $this->page - (self::MAX_PAGES_DISPLAYED / 2);

    if ($firstPageDisplayed < 1) {
      $firstPageDisplayed = 1;
    }

    if (($firstPageDisplayed + self::MAX_PAGES_DISPLAYED) > $nbPages) {
      $lastPageDisplayed = $nbPages;

      if ($nbPages < self::MAX_PAGES_DISPLAYED) {
        $firstPageDisplayed = 1;
      } else {
        $firstPageDisplayed = $nbPages - self::MAX_PAGES_DISPLAYED + 1;
      }
    } else {
      $lastPageDisplayed = ($firstPageDisplayed + self::MAX_PAGES_DISPLAYED - 1);
    }

    if ($nbPages === 1) {
      return false;
    }

    $links = new \stdClass;
    $links->list = [];

    if ($nbPages > self::MAX_PAGES_DISPLAYED && $firstPageDisplayed > 1) {
      $links->list[$firstPageDisplayed - 1] = new \stdClass;
      $links->list[$firstPageDisplayed - 1]->span = true;
    }

    for ($page = $firstPageDisplayed; $page <= $lastPageDisplayed; $page++) {
      $links->list[$page] = new \stdClass;
      $links->list[$page]->page = $page;
      $links->list[$page]->text = (string)$page;

      if ($links->list[$page]->page === $this->page) {
        $links->list[$page]->current = true;
      }
    }

    if (empty($links->list)) {
      return false;
    }

    if ($nbPages > self::MAX_PAGES_DISPLAYED && $lastPageDisplayed < $nbPages) {
      $links->list[$lastPageDisplayed + 1] = new \stdClass;
      $links->list[$lastPageDisplayed + 1]->span = true;
    }

    $links->start = new \stdClass;
    $links->start->page = 1;
    $links->start->text = $this->i18n['START'];

    $links->previous = new \stdClass;
    $links->previous->text = $this->i18n['PREVIOUS'];
    $links->previous->page = $this->page - 1;

    $links->next = new \stdClass;
    $links->next->text = $this->i18n['NEXT'];
    $links->next->page = $this->page + 1;

    $links->end = new \stdClass;
    $links->end->page = $nbPages;
    $links->end->text = $this->i18n['END'];

    if ($this->page === 1) {
      $links->previous->disabled = true;
    }

    if ($this->page === $nbPages) {
      $links->next->disabled = true;
    }

    return $links;
  }
}
