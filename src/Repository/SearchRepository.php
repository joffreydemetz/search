<?php

namespace JDZ\Search\Repository;

use JDZ\Database\Contract\DatabaseInterface;
use JDZ\Database\Query\InsertQuery;
use JDZ\Database\Query\SelectQuery;
use JDZ\Database\Query\UpdateQuery;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
class SearchRepository
{
  protected DatabaseInterface $dbo;
  protected string $component;
  protected string $term;

  public function setDbo(DatabaseInterface $dbo): static
  {
    $this->dbo = $dbo;
    return $this;
  }

  public function setComponent(string $component): static
  {
    $this->component = $component;
    return $this;
  }

  public function setTerm(string $term): static
  {
    $this->term = $term;
    return $this;
  }

  public function save(): bool
  {
    try {
      // a failed lookup falls back to the INSERT, a failed UPDATE does not
      $exists = false;
      try {
        $this->dbo->setQuery(
          (new SelectQuery())
            ->select('component')
            ->from('#__search')
            ->where('component = :component')
            ->bindValue(':component', $this->component)
            ->where('term = :term')
            ->bindValue(':term', $this->term)
        );

        $exists = (bool) $this->dbo->loadResult();
      } catch (\Exception $e) {}

      if ($exists) {
        $this->dbo->setQuery(
          (new UpdateQuery())
            ->update('#__search')
            ->set('hits = hits+1')
            ->where('component = :component')
            ->bindValue(':component', $this->component)
            ->where('term = :term')
            ->bindValue(':term', $this->term)
        );

        $this->dbo->execute();
        return true;
      }

      $this->dbo->setQuery(
        (new InsertQuery())
          ->insert('#__search')
          ->columns('component, term')
          ->values(':component, :term')
          ->bindValue(':component', $this->component)
          ->bindValue(':term', $this->term)
      );
      $this->dbo->execute();

      return true;
    } catch (\Exception $e) {}

    return false;
  }
}
