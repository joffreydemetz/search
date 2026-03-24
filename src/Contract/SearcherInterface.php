<?php

namespace JDZ\Search\Contract;

use JDZ\Database\Contract\DatabaseInterface;
use JDZ\Database\Query\SelectQuery;

/**
 * @author Joffrey Demetz <joffrey.demetz@gmail.com>
 */
interface SearcherInterface
{
  public function setDbo(DatabaseInterface $dbo): static;
  public function setName(string $name): static;
  public function setTitle(string $title): static;
  public function setFields(array $fields): static;
  public function setType(string $type): static;
  public function setTerm(?string $term): static;
  public function getName(): string;
  public function getTitle(): string;
  public function getFilterOption(): \stdClass;
  public function makeQuery(SelectQuery $query): void;
  public function parseResults(array &$rows): void;
}
